<?php

/**
 * Copyright (c) 2021-present, Emile Silas Sare
 *
 * This file is part of Blate package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Blate\Lsp;

use Blate\Blate;
use Blate\Exceptions\BlateParserException;
use Blate\Exceptions\BlateRuntimeException;
use Blate\Helpers\Helpers;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Class BlateLspServer.
 *
 * Language Server Protocol (LSP) server for Blate template files.
 *
 * Features:
 *  - textDocument/publishDiagnostics  parse errors with exact source positions
 *  - textDocument/completion          block names, helper names, in-scope vars
 *  - textDocument/hover               docblock for helpers, blocks, and global variables
 *  - textDocument/rename              simple variable rename in one document
 *
 * Wire format: JSON-RPC 2.0 over stdin/stdout with Content-Length framing.
 */
class BlateLspServer
{
	private bool $running = true;

	/** @var array<string, string> uri -> full text content */
	private array $docs = [];

	public function __construct()
	{
		// Dedicated temp dir keeps LSP cache files away from the project tree.
		Blate::setCacheDir(
			\sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'blate-lsp-cache'
		);
	}

	// =========================================================================
	// Main loop
	// =========================================================================

	public function run(): void
	{
		\set_error_handler(static function (int $errno, string $errstr): bool {
			\fwrite(\STDERR, '[blate-lsp] PHP error ' . $errno . ': ' . $errstr . "\n");

			return true;
		});

		while ($this->running) {
			$msg = $this->readMessage();

			if (null === $msg) {
				break;
			}

			$this->dispatch($msg);
		}

		\restore_error_handler();

		exit(0);
	}

	// =========================================================================
	// Wire protocol
	// =========================================================================

	/**
	 * Reads one JSON-RPC message from STDIN.
	 *
	 * @return null|array<string, mixed>
	 */
	private function readMessage(): ?array
	{
		$headers = [];

		// Read header lines until the blank separator line.
		while (true) {
			$line = \fgets(\STDIN);

			if (false === $line) {
				return null;
			}

			$line = \rtrim($line, "\r\n");

			if ('' === $line) {
				break; // blank line ends the header block
			}

			$pos = \strpos($line, ': ');

			if (false !== $pos) {
				$headers[\strtolower(\substr($line, 0, $pos))] = \substr($line, $pos + 2);
			}
		}

		$length = isset($headers['content-length']) ? (int) $headers['content-length'] : 0;

		if ($length <= 0) {
			return null;
		}

		$body = '';

		while (\strlen($body) < $length) {
			$chunk = \fread(\STDIN, $length - \strlen($body));

			if (false === $chunk || '' === $chunk) {
				return null;
			}

			$body .= $chunk;
		}

		$decoded = \json_decode($body, true);

		return \is_array($decoded) ? $decoded : null;
	}

	/**
	 * Writes a JSON-RPC message to STDOUT using Content-Length framing.
	 *
	 * @param array<string, mixed> $message
	 */
	private function send(array $message): void
	{
		$json = (string) \json_encode($message, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
		\fwrite(\STDOUT, 'Content-Length: ' . \strlen($json) . "\r\n\r\n" . $json);
	}

	/**
	 * Sends a successful JSON-RPC response.
	 */
	private function respond(mixed $id, mixed $result): void
	{
		$this->send(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
	}

	/**
	 * Sends a JSON-RPC error response.
	 */
	private function respondError(mixed $id, int $code, string $message): void
	{
		$this->send([
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => ['code' => $code, 'message' => $message],
		]);
	}

	/**
	 * Sends a JSON-RPC notification (no id, no reply expected).
	 *
	 * @param array<string, mixed> $params
	 */
	private function notify(string $method, array $params): void
	{
		$this->send(['jsonrpc' => '2.0', 'method' => $method, 'params' => $params]);
	}

	// =========================================================================
	// Dispatcher
	// =========================================================================

	/**
	 * Routes an incoming JSON-RPC message to the appropriate handler.
	 *
	 * @param array<string, mixed> $msg
	 */
	private function dispatch(array $msg): void
	{
		$method = (string) ($msg['method'] ?? '');
		$id     = $msg['id'] ?? null;
		$params = $msg['params'] ?? [];

		if (!\is_array($params)) {
			$params = [];
		}

		try {
			switch ($method) {
				case 'initialize':
					$this->handleInitialize($id);

					break;

				case 'initialized':
					// no-op: client acknowledges our capabilities
					break;

				case 'shutdown':
					$this->running = false;
					$this->respond($id, null);

					break;

				case 'exit':
					$this->running = false;

					break;

				case 'textDocument/didOpen':
					$this->handleDidOpen($params);

					break;

				case 'textDocument/didChange':
					$this->handleDidChange($params);

					break;

				case 'textDocument/didClose':
					$this->handleDidClose($params);

					break;

				case 'textDocument/completion':
					$this->handleCompletion($id, $params);

					break;

				case 'textDocument/hover':
					$this->handleHover($id, $params);

					break;

				case 'textDocument/rename':
					$this->handleRename($id, $params);

					break;

				default:
					if (null !== $id) {
						$this->respondError($id, -32601, 'Method not found: ' . $method);
					}
			}
		} catch (Throwable $e) {
			\fwrite(\STDERR, '[blate-lsp] Unhandled error in ' . $method . ': ' . $e->getMessage() . "\n");

			if (null !== $id) {
				$this->respondError($id, -32603, 'Internal error: ' . $e->getMessage());
			}
		}
	}

	// =========================================================================
	// LSP request / notification handlers
	// =========================================================================

	private function handleInitialize(mixed $id): void
	{
		$this->respond($id, [
			'capabilities' => [
				'textDocumentSync' => [
					'openClose' => true,
					'change'    => 1,    // full document sync on every change
					'save'      => true,
				],
				'completionProvider' => [
					'triggerCharacters' => ['{', '@', ':', '|', '/'],
					'resolveProvider'   => false,
				],
				'hoverProvider'  => true,
				'renameProvider' => true,
			],
			'serverInfo' => [
				'name'    => 'blate-lsp',
				'version' => Blate::VERSION,
			],
		]);
	}

	/** @param array<string, mixed> $params */
	private function handleDidOpen(array $params): void
	{
		$uri              = (string) ($params['textDocument']['uri'] ?? '');
		$content          = (string) ($params['textDocument']['text'] ?? '');
		$this->docs[$uri] = $content;
		$this->diagnose($uri, $content);
	}

	/** @param array<string, mixed> $params */
	private function handleDidChange(array $params): void
	{
		$uri     = (string) ($params['textDocument']['uri'] ?? '');
		$changes = $params['contentChanges'] ?? [];
		// Full sync (change mode 1): the last entry contains the complete text.
		$content          = (string) (\end($changes)['text'] ?? '');
		$this->docs[$uri] = $content;
		$this->diagnose($uri, $content);
	}

	/** @param array<string, mixed> $params */
	private function handleDidClose(array $params): void
	{
		$uri = (string) ($params['textDocument']['uri'] ?? '');
		unset($this->docs[$uri]);
		// Clear any previously published diagnostics for this file.
		$this->notify('textDocument/publishDiagnostics', ['uri' => $uri, 'diagnostics' => []]);
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function handleCompletion(mixed $id, array $params): void
	{
		$uri     = (string) ($params['textDocument']['uri'] ?? '');
		$line    = (int) ($params['position']['line'] ?? 0);
		$col     = (int) ($params['position']['character'] ?? 0);
		$content = $this->docs[$uri] ?? '';
		$offset  = $this->positionToOffset($content, $line, $col);
		$items   = $this->buildCompletionItems($content, $offset);

		$this->respond($id, ['isIncomplete' => false, 'items' => $items]);
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function handleHover(mixed $id, array $params): void
	{
		$uri     = (string) ($params['textDocument']['uri'] ?? '');
		$line    = (int) ($params['position']['line'] ?? 0);
		$col     = (int) ($params['position']['character'] ?? 0);
		$content = $this->docs[$uri] ?? '';
		$offset  = $this->positionToOffset($content, $line, $col);
		$hover   = $this->buildHover($content, $offset);

		$this->respond($id, $hover);
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function handleRename(mixed $id, array $params): void
	{
		$uri     = (string) ($params['textDocument']['uri'] ?? '');
		$line    = (int) ($params['position']['line'] ?? 0);
		$col     = (int) ($params['position']['character'] ?? 0);
		$newName = (string) ($params['newName'] ?? '');
		$content = $this->docs[$uri] ?? '';
		$offset  = $this->positionToOffset($content, $line, $col);
		$edits   = $this->buildRenameEdits($content, $offset, $newName);

		if (null === $edits) {
			$this->respondError($id, -32600, 'No renameable symbol at cursor position');

			return;
		}

		$this->respond($id, ['changes' => [$uri => $edits]]);
	}

	// =========================================================================
	// Diagnostics
	// =========================================================================

	private function diagnose(string $uri, string $content): void
	{
		try {
			// parse(false): only re-compiles when the content hash has changed.
			Blate::fromString($content)->parse(false);
			$this->notify('textDocument/publishDiagnostics', [
				'uri'         => $uri,
				'diagnostics' => $this->buildHelperShadowHints($content),
			]);
		} catch (BlateParserException|BlateRuntimeException $e) {
			$chunk = $e->getChunk();

			if (null !== $chunk) {
				$loc = $chunk->getLocation();
				$sl  = \max(0, $loc['start_line_number'] - 1);
				$sc  = \max(0, $loc['start_line_index'] - 1);
				$el  = \max(0, $loc['end_line_number'] - 1);
				$ec  = \max(0, $loc['end_line_index'] - 1);
				// Guarantee a non-empty range so the squiggle is visible.
				if ($el < $sl || ($el === $sl && $ec <= $sc)) {
					$el = $sl;
					$ec = $sc + 1;
				}
			} else {
				$sl = $sc = $el = 0;
				$ec = 1;
			}

			$this->notify('textDocument/publishDiagnostics', [
				'uri'         => $uri,
				'diagnostics' => [[
					'range'    => [
						'start' => ['line' => $sl, 'character' => $sc],
						'end'   => ['line' => $el, 'character' => $ec],
					],
					'severity' => 1,       // DiagnosticSeverity.Error
					'source'   => 'blate',
					'message'  => $e->getMessage(),
				]],
			]);
		} catch (Throwable $e) {
			// Non-parser failures (e.g. unresolvable @import paths) are warnings.
			$this->notify('textDocument/publishDiagnostics', [
				'uri'         => $uri,
				'diagnostics' => [[
					'range'    => [
						'start' => ['line' => 0, 'character' => 0],
						'end'   => ['line' => 0, 'character' => 1],
					],
					'severity' => 2,       // DiagnosticSeverity.Warning
					'source'   => 'blate',
					'message'  => $e->getMessage(),
				]],
			]);
		}
	}

	// =========================================================================
	// Completion
	// =========================================================================

	/**
	 * Scans the template for unqualified helper calls that may be shadowed by
	 * user-data keys at runtime. Returns Hint-level diagnostics.
	 *
	 * A call like {upper(name)} resolves through the full scope stack: if the
	 * render data contains a key 'upper', it will shadow the registered helper.
	 * Use {$upper(name)} to guarantee the registered helper is always resolved.
	 *
	 * Pipe-filter positions (| name) are skipped because they already use
	 * helper-only lookup internally and are never subject to shadowing.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function buildHelperShadowHints(string $content): array
	{
		$helperSet = [];

		foreach (\array_keys(Blate::getHelpers()) as $name) {
			if (!\str_starts_with($name, '$')) {
				$helperSet[$name] = true;
			}
		}

		if (empty($helperSet)) {
			return [];
		}

		// Match unqualified identifier calls: word( not preceded by $
		if (!\preg_match_all('/(?<!\$)\b([a-zA-Z_]\w*)\s*\(/', $content, $matches, \PREG_OFFSET_CAPTURE)) {
			return [];
		}

		$hints = [];

		foreach ($matches[1] as [$name, $byteOffset]) {
			if (!isset($helperSet[$name])) {
				continue;
			}

			// Pipe-filter position: | (whitespace*) name( -- already helper-only, skip.
			$before = \rtrim(\substr($content, 0, $byteOffset));

			if (\str_ends_with($before, '|')) {
				continue;
			}

			$end      = $byteOffset + \strlen($name);
			$hints[]  = [
				'range'    => $this->byteRangeToLspRange($content, $byteOffset, $end),
				'severity' => 4,   // DiagnosticSeverity.Hint
				'source'   => 'blate',
				'message'  => '"' . $name . '" is a registered helper. '
					. 'If render data contains a key "' . $name . '", it will shadow this helper at runtime. '
					. 'Use $' . $name . '(...) to always resolve the registered helper.',
			];
		}

		return $hints;
	}

	// =========================================================================
	// Completion
	// =========================================================================

	/**
	 * Builds completion items for the given cursor position.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function buildCompletionItems(string $content, int $offset): array
	{
		$ctx = $this->detectContext($content, $offset);

		switch ($ctx) {
			case 'block-open':
				return $this->blockCompletions();

			case 'block-close':
				return $this->blockCloseCompletions();

			case 'breakpoint':
				return $this->breakpointCompletions();

			case 'pipe':
				return $this->helperCompletions();

			default:
				// General expression context: variables + helpers + global vars.
				return \array_merge(
					$this->helperCompletions(),
					$this->globalVarCompletions(),
					$this->variableCompletions($content)
				);
		}
	}

	/**
	 * Detects the syntactic context at the cursor by scanning backwards.
	 *
	 * Returns one of: 'block-open', 'block-close', 'breakpoint', 'pipe', 'general'.
	 */
	private function detectContext(string $content, int $offset): string
	{
		$before = \substr($content, 0, $offset);

		// Find the most recent opening brace that is not yet closed.
		$tagStart  = \strrpos($before, '{');
		$lastClose = \strrpos($before, '}');

		if (false === $tagStart || (false !== $lastClose && $lastClose > $tagStart)) {
			return 'general';
		}

		$afterBrace = \substr($before, $tagStart + 1);

		if (\str_starts_with($afterBrace, '@')) {
			return 'block-open';
		}

		if (\str_starts_with($afterBrace, '/')) {
			return 'block-close';
		}

		if (\str_starts_with($afterBrace, ':')) {
			return 'breakpoint';
		}

		if (\str_contains($afterBrace, '|')) {
			return 'pipe';
		}

		return 'general';
	}

	/**
	 * Completion items for block-open context ({@ ...).
	 *
	 * @return list<array<string, mixed>>
	 */
	private function blockCompletions(): array
	{
		// 'comment' uses {# #} syntax and 'php' uses {~ ~} syntax;
		// neither is accessed via {@ ... }.
		$skip = ['comment', 'php'];

		$snippets = [
			'capture'    => "{@capture \${1:name}}\n\t\$0\n{/capture}",
			'each'       => "{@each \${1:item} in \${2:list}}\n\t\$0\n{/each}",
			'extends'    => "{@extends '\${1:path}'}\n{@slot \${2:name}}\$0{/slot}\n{/extends}",
			'if'         => "{@if \${1:condition}}\n\t\$0\n{/if}",
			'import'     => "{@import '\${1:path}'}",
			'import_raw' => "{@import_raw '\${1:path}'}",
			'raw'        => "{@raw}\n\t\$0\n{/raw}",
			'repeat'     => "{@repeat \${1:count} as \${2:i}}\n\t\$0\n{/repeat}",
			'scoped'     => "{@scoped}\n\t\$0\n{/scoped}",
			'set'        => '{@set ${1:name} = ${2:value}}',
			'slot'       => '{@slot ${1:name}}$0{/slot}',
			'switch'     => "{@switch \${1:expr}}\n{:case \${2:val}}\n\t\$0\n{/switch}",
		];

		$items = [];

		foreach (\array_keys(Blate::getBlocks()) as $name) {
			if (\in_array($name, $skip, true)) {
				continue;
			}

			$item = [
				'label'  => $name,
				'kind'   => 14,   // CompletionItemKind.Keyword
				'detail' => 'Blate @' . $name . ' block',
			];

			if (isset($snippets[$name])) {
				$item['insertTextFormat'] = 2;  // InsertTextFormat.Snippet
				$item['insertText']       = $snippets[$name];
			} else {
				$item['insertText'] = $name;
			}

			$items[] = $item;
		}

		return $items;
	}

	/**
	 * Completion items for block-close context ({/ ...).
	 *
	 * @return list<array<string, mixed>>
	 */
	private function blockCloseCompletions(): array
	{
		$closeables = ['capture', 'each', 'extends', 'if', 'raw', 'repeat', 'scoped', 'slot', 'switch'];
		$items      = [];

		foreach ($closeables as $name) {
			$items[] = [
				'label'      => $name,
				'kind'       => 14,
				'insertText' => $name . '}',
				'detail'     => 'Close ' . $name,
			];
		}

		return $items;
	}

	/**
	 * Completion items for breakpoint context ({: ...).
	 *
	 * @return list<array<string, mixed>>
	 */
	private function breakpointCompletions(): array
	{
		$bps = [
			'else'    => 'else}',
			'elseif'  => 'elseif ${1:condition}}',
			'case'    => 'case ${1:value}}',
			'default' => 'default}',
		];

		$items = [];

		foreach ($bps as $label => $snippet) {
			$items[] = [
				'label'            => $label,
				'kind'             => 14,
				'insertText'       => $snippet,
				'insertTextFormat' => 2,
				'detail'           => 'Block breakpoint',
			];
		}

		return $items;
	}

	/**
	 * Completion items for registered global variables.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function globalVarCompletions(): array
	{
		$items = [];

		foreach (\array_keys(Blate::getGlobalVars()) as $name) {
			$items[] = [
				'label'      => $name,
				'kind'       => 21,  // CompletionItemKind.Constant
				'detail'     => 'Blate global variable',
				'insertText' => $name,
			];
		}

		return $items;
	}

	/**
	 * Completion items for registered helpers.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function helperCompletions(): array
	{
		$items = [];

		foreach (\array_keys(Blate::getHelpers()) as $name) {
			// Skip the $-prefixed duplicates used for force-helper lookup syntax.
			if (\str_starts_with($name, '$')) {
				continue;
			}

			$items[] = [
				'label'      => $name,
				'kind'       => 3,   // CompletionItemKind.Function
				'detail'     => 'Blate helper',
				'insertText' => $name,
			];
		}

		return $items;
	}

	/**
	 * Completion items for variables found in the template.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function variableCompletions(string $content): array
	{
		$items = [];

		foreach ($this->scanVariables($content) as $name) {
			$items[] = [
				'label'      => $name,
				'kind'       => 6,   // CompletionItemKind.Variable
				'detail'     => 'Template variable',
				'insertText' => $name,
			];
		}

		return $items;
	}

	/**
	 * Scans the template for variable names introduced by @set, @each, @repeat, @capture.
	 *
	 * @return list<string>
	 */
	private function scanVariables(string $content): array
	{
		$vars = [];

		// {@set varName = ...} and multi-assign {@set a = 1; b = 2}
		if (\preg_match_all('/\{@set\s+(\w+)\s*=/', $content, $m)) {
			$vars = \array_merge($vars, $m[1]);
		}

		// {@each item:key:idx in list} - item, key, idx are all optional names
		if (\preg_match_all('/\{@each\s+(\w+)(?::(\w+))?(?::(\w+))?\s+in\b/', $content, $m)) {
			$vars = \array_merge(
				$vars,
				\array_filter($m[1]),
				\array_filter($m[2]),
				\array_filter($m[3])
			);
		}

		// {@repeat count as i}
		if (\preg_match_all('/\{@repeat\b[^}]+\bas\s+(\w+)\}/', $content, $m)) {
			$vars = \array_merge($vars, $m[1]);
		}

		// {@capture name}
		if (\preg_match_all('/\{@capture\s+(\w+)\}/', $content, $m)) {
			$vars = \array_merge($vars, $m[1]);
		}

		return \array_values(\array_unique(\array_filter($vars)));
	}

	// =========================================================================
	// Hover
	// =========================================================================

	/**
	 * Builds a Hover response for the symbol at the given byte offset.
	 *
	 * @return null|array<string, mixed>
	 */
	private function buildHover(string $content, int $offset): ?array
	{
		$word = $this->wordAt($content, $offset);

		if ('' === $word) {
			return null;
		}

		// Provide hover for registered global variables.
		$globals = Blate::getGlobalVars();

		if (\array_key_exists($word, $globals)) {
			$value = $globals[$word];
			$repr  = \is_string($value) ? '"' . \addslashes($value) . '"' : \json_encode($value);

			return [
				'contents' => [
					'kind'  => 'markdown',
					'value' => '**' . $word . '** _(Blate global variable)_' . "\n\n" . '`' . $repr . '`',
				],
			];
		}

		// Detect {@word or {/word context to provide block hover.
		$wordStart = $offset;

		while ($wordStart > 0 && \preg_match('/\w/', $content[$wordStart - 1])) {
			--$wordStart;
		}

		$prevChar  = $wordStart > 0 ? $content[$wordStart - 1] : '';
		$prev2Char = $wordStart > 1 ? $content[$wordStart - 2] : '';

		if (('@' === $prevChar || '/' === $prevChar) && '{' === $prev2Char
			&& \array_key_exists($word, Blate::getBlocks())
		) {
			return [
				'contents' => ['kind' => 'markdown', 'value' => $this->resolveBlockDoc($word)],
			];
		}

		// Provide hover for registered helpers.
		if (!\array_key_exists($word, Blate::getHelpers())) {
			return null;
		}

		return [
			'contents' => ['kind' => 'markdown', 'value' => $this->resolveHelperDoc($word)],
		];
	}

	private function resolveHelperDoc(string $name): string
	{
		try {
			$ref = new ReflectionClass(Helpers::class);

			if ($ref->hasMethod($name)) {
				$raw = $ref->getMethod($name)->getDocComment();

				if (false !== $raw) {
					return $this->formatDocblock('**' . $name . '** _(Blate helper)_', $raw);
				}
			}
		} catch (ReflectionException) {
			// ignore - fall through to default
		}

		return '**' . $name . '** _(Blate helper)_';
	}

	private function resolveBlockDoc(string $name): string
	{
		$blocks = Blate::getBlocks();

		if (!isset($blocks[$name])) {
			return '**@' . $name . '** _(Blate block)_';
		}

		try {
			$ref = new ReflectionClass($blocks[$name]);
			$raw = $ref->getDocComment();

			if (false !== $raw) {
				return $this->formatDocblock('**@' . $name . '** _(Blate block)_', $raw, true);
			}
		} catch (ReflectionException) {
			// ignore
		}

		return '**@' . $name . '** _(Blate block)_';
	}

	/**
	 * Converts a raw PHP docblock into a markdown hover string.
	 *
	 * Strips the delimiters and leading `*` per line, then groups content into:
	 *   - A description section (paragraphs separated by blank lines)
	 *   - A Parameters section (from `@param` tags)
	 *   - A Returns line (from `@return` tag)
	 *
	 * Other tags (`@throws`, `@see`, etc.) are omitted.
	 *
	 * @param string $heading       markdown heading line (bold name + type badge)
	 * @param string $raw           raw docblock string from ReflectionMethod/ReflectionClass
	 * @param bool   $skipClassLine when true, drops the leading "Class Foo." summary line
	 *                              that class-level docblocks contain
	 */
	private function formatDocblock(string $heading, string $raw, bool $skipClassLine = false): string
	{
		$lines = \explode("\n", $raw);
		$plain = [];

		foreach ($lines as $line) {
			$line    = \ltrim($line);       // strip indent
			$line    = \ltrim($line, '*');   // strip leading *
			$plain[] = \trim($line);        // strip trailing whitespace
		}

		// Drop the opening /** line.
		while (!empty($plain) && ('' === $plain[0] || \str_starts_with($plain[0], '/'))) {
			\array_shift($plain);
		}

		// Drop trailing empty lines and the closing */ line (becomes '/' after stripping).
		while (!empty($plain) && ('' === \end($plain) || '/' === \end($plain))) {
			\array_pop($plain);
		}

		// For class-level docblocks, drop the "Class BlockXxx." first line and any
		// blank lines that immediately follow it.
		if ($skipClassLine && !empty($plain) && \preg_match('/^Class\s+\w+\./', $plain[0])) {
			\array_shift($plain);

			while (!empty($plain) && '' === $plain[0]) {
				\array_shift($plain);
			}
		}

		$descLines  = [];
		$paramLines = [];
		$returnLine = '';

		foreach ($plain as $line) {
			if (\str_starts_with($line, '@param')) {
				if (\preg_match('/^@param\s+(\S+)\s+(\$\w+)\s*(.*)/s', $line, $m)) {
					$pdesc        = \trim($m[3]);
					$paramLines[] = '- `' . $m[2] . '` `' . $m[1] . '`' . ('' !== $pdesc ? ' - ' . $pdesc : '');
				} else {
					$paramLines[] = '- ' . \trim(\substr($line, 7));
				}
			} elseif (\str_starts_with($line, '@return')) {
				$type       = \trim(\substr($line, 7));
				$returnLine = '' !== $type ? '`' . $type . '`' : '';
			} elseif (\str_starts_with($line, '@')) {
				// Drop other tags (@throws, @see, @var, etc.).
			} else {
				$descLines[] = $line;
			}
		}

		// Trim trailing blank lines from description.
		while (!empty($descLines) && '' === \end($descLines)) {
			\array_pop($descLines);
		}

		// Build description: consecutive non-blank lines form a paragraph;
		// blank lines separate paragraphs.
		$paragraphs = [];
		$current    = [];

		foreach ($descLines as $line) {
			if ('' === $line) {
				if (!empty($current)) {
					$paragraphs[] = \implode("\n", $current);
					$current      = [];
				}
			} else {
				$current[] = $line;
			}
		}

		if (!empty($current)) {
			$paragraphs[] = \implode("\n", $current);
		}

		$md = $heading;

		if (!empty($paragraphs)) {
			$md .= "\n\n" . \implode("\n\n", $paragraphs);
		}

		if (!empty($paramLines)) {
			$md .= "\n\n**Parameters:**\n" . \implode("\n", $paramLines);
		}

		if ('' !== $returnLine) {
			$md .= "\n\n**Returns:** " . $returnLine;
		}

		return \rtrim($md);
	}

	// =========================================================================
	// Rename
	// =========================================================================

	/**
	 * Builds TextEdit list for renaming all occurrences of the symbol at offset.
	 *
	 * @return null|list<array{range: array<string, mixed>, newText: string}>
	 */
	private function buildRenameEdits(string $content, int $offset, string $newName): ?array
	{
		$word = $this->wordAt($content, $offset);

		if ('' === $word) {
			return null;
		}

		$pattern = '/\b' . \preg_quote($word, '/') . '\b/';

		if (!\preg_match_all($pattern, $content, $matches, \PREG_OFFSET_CAPTURE)) {
			return null;
		}

		$edits = [];

		foreach ($matches[0] as $match) {
			$byteOffset = $match[1];
			$edits[]    = [
				'range'   => $this->byteRangeToLspRange($content, $byteOffset, $byteOffset + \strlen($word)),
				'newText' => $newName,
			];
		}

		return $edits ?: null;
	}

	// =========================================================================
	// Utilities
	// =========================================================================

	/**
	 * Converts a 0-based LSP line/character position to a byte offset.
	 *
	 * Note: 'character' is treated as a byte offset within the line, which is
	 * exact for ASCII content and a close approximation for UTF-8 templates.
	 */
	private function positionToOffset(string $text, int $line, int $character): int
	{
		$offset      = 0;
		$currentLine = 0;
		$len         = \strlen($text);

		while ($currentLine < $line && $offset < $len) {
			if ("\n" === $text[$offset]) {
				++$currentLine;
			}

			++$offset;
		}

		return $offset + $character;
	}

	/**
	 * Converts a byte offset to a 0-based LSP line/character position.
	 *
	 * @return array{line: int, character: int}
	 */
	private function offsetToLspPosition(string $text, int $offset): array
	{
		$before    = \substr($text, 0, $offset);
		$line      = \substr_count($before, "\n");
		$lastNl    = \strrpos($before, "\n");
		$character = (false === $lastNl) ? $offset : $offset - $lastNl - 1;

		return ['line' => $line, 'character' => $character];
	}

	/**
	 * @return array{start: array{line: int, character: int}, end: array{line: int, character: int}}
	 */
	private function byteRangeToLspRange(string $text, int $start, int $end): array
	{
		return [
			'start' => $this->offsetToLspPosition($text, $start),
			'end'   => $this->offsetToLspPosition($text, $end),
		];
	}

	/**
	 * Extracts the identifier word at a byte offset by expanding left and right.
	 */
	private function wordAt(string $content, int $offset): string
	{
		$len = \strlen($content);

		// Expand left over word characters.
		$start = $offset;

		while ($start > 0 && \preg_match('/\w/', $content[$start - 1])) {
			--$start;
		}

		// Expand right over word characters.
		$end = $offset;

		while ($end < $len && \preg_match('/\w/', $content[$end])) {
			++$end;
		}

		return \substr($content, $start, $end - $start);
	}
}
