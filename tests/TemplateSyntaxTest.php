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

namespace Blate\Tests;

use Blate\Blate;
use Blate\Exceptions\BlateException;
use Blate\Exceptions\BlateRuntimeException;
use Blate\Parser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 *
 * @coversNothing
 */
final class TemplateSyntaxTest extends TestCase
{
	// =========================================================================
	// Output / Print
	// =========================================================================

	/**
	 * {varName}   -- auto-escaped (htmlspecialchars)
	 * {= varName} -- raw/unescaped output.
	 *
	 * @throws BlateException
	 */
	public function testPrintEscaped(): void
	{
		$this->runValid('block-print');
	}

	/**
	 * {  -- literal brace via print-token fallback.
	 *
	 * @throws BlateException
	 */
	public function testPrintLiteralBrace(): void
	{
		$this->runValid('template-print-token');
	}

	// =========================================================================
	// Template-level features
	// =========================================================================

	/**
	 * Empty template produces empty output.
	 *
	 * @throws BlateException
	 */
	public function testTemplateEmpty(): void
	{
		$this->runValid('template-empty');
	}

	/**
	 * Template with no Blate tokens passes through as-is.
	 *
	 * @throws BlateException
	 */
	public function testTemplateNoToken(): void
	{
		$this->runValid('template-no-token');
	}

	/**
	 * {# comment #} -- stripped at compile time.
	 *
	 * @throws BlateException
	 */
	public function testTemplateComment(): void
	{
		$this->runValid('template-comment');
	}

	/**
	 * {@raw}...{/raw} -- literal braces inside raw block.
	 *
	 * @throws BlateException
	 */
	public function testTemplateRawBlock(): void
	{
		$this->runValid('template-raw-block');
	}

	/**
	 * {~ echo 'php'; ~} -- inline PHP code block.
	 *
	 * @throws BlateException
	 */
	public function testTemplatePhpBlock(): void
	{
		$this->runValid('template-php');
	}

	/**
	 * Full template with property access, method calls, and each loop.
	 *
	 * @throws BlateException
	 */
	public function testTemplateValid(): void
	{
		$this->runValid('template-valid');
	}

	// =========================================================================
	// Expressions
	// =========================================================================

	/**
	 * Complex expression: chains, subscripts, calls, literals, all operators.
	 * Verifies compiled PHP (class body snapshot; no inject needed).
	 *
	 * @throws BlateException
	 */
	public function testExpressionValid(): void
	{
		$this->runValid('expression-valid');
	}

	/**
	 * Unary +expr / -expr in expressions.
	 * Verifies compiled PHP (class body snapshot).
	 *
	 * @throws BlateException
	 */
	public function testExpressionUnary(): void
	{
		$this->runValid('expression-unary-valid');
	}

	/**
	 * {a ?? 'fallback'} -- null-coalesce operator.
	 *
	 * @throws BlateException
	 */
	public function testNullCoalesce(): void
	{
		$this->runValid('operator-null-coalesce');
	}

	// =========================================================================
	// Pipe filters
	// =========================================================================

	/**
	 * {expr | fn} and {expr | fn(a,b)} and chained pipes.
	 *
	 * @throws BlateException
	 */
	public function testPipeFilter(): void
	{
		$this->runValid('pipe-filter-valid');
	}

	// =========================================================================
	// Block: @if / :elseif / :else
	// =========================================================================

	/**
	 * {@if expr}...{:elseif expr}...{:else}...{/if}.
	 *
	 * @throws BlateException
	 */
	public function testBlockIf(): void
	{
		$this->runValid('block-if');
	}

	// =========================================================================
	// Block: @each
	// =========================================================================

	/**
	 * {@each value:key in map} -- value + key iteration.
	 *
	 * @throws BlateException
	 */
	public function testBlockEachKey(): void
	{
		$this->runValid('block-each-key');
	}

	/**
	 * {@each value:key:index in map} -- value + key + iteration index.
	 *
	 * @throws BlateException
	 */
	public function testBlockEachKeyIndex(): void
	{
		$this->runValid('block-each-key-index');
	}

	/**
	 * {@each value in list}...{:else}...{/each} -- empty-list branch.
	 *
	 * @throws BlateException
	 */
	public function testBlockEachElse(): void
	{
		$this->runValid('block-each-else');
	}

	// =========================================================================
	// Block: @set and @scoped
	// =========================================================================

	/**
	 * {@set x = expr; y = expr} and {@scoped}...{/scoped}.
	 *
	 * @throws BlateException
	 */
	public function testBlockSetAndScoped(): void
	{
		$this->runValid('template-set-and-scoped-block');
	}

	// =========================================================================
	// Block: @switch
	// =========================================================================

	/**
	 * {@switch expr}{:case val}...{:default}...{/switch}.
	 *
	 * @throws BlateException
	 */
	public function testBlockSwitch(): void
	{
		$this->runValid('block-switch');
	}

	// =========================================================================
	// Block: @capture
	// =========================================================================

	/**
	 * {@capture varname}...{/capture} -- buffers rendered body into a variable.
	 *
	 * @throws BlateException
	 */
	public function testBlockCapture(): void
	{
		$this->runValid('block-capture');
	}

	/**
	 * {@capture result}{@extends 'base' $$}{@slot name}...{/slot}{/extends}{/capture}
	 * -- {@extends} nested inside {@capture} buffers the full parent-template
	 * rendered output into the capture variable.
	 *
	 * @throws BlateException
	 */
	public function testCaptureExtends(): void
	{
		$this->runValid('capture-extends');
	}

	// =========================================================================
	// Block: @repeat
	// =========================================================================

	/**
	 * {@repeat n}...{/repeat} and {@repeat n as idx}.
	 *
	 * @throws BlateException
	 */
	public function testBlockRepeat(): void
	{
		$this->runValid('block-repeat');
	}

	// =========================================================================
	// Block: @extends / @slot
	// =========================================================================

	/**
	 * {@extends 'base' ctx}{@slot name}...{/slot}{/extends}
	 * with {:default} slot fallback and item-inject slot.
	 *
	 * @throws BlateException
	 */
	public function testExtendsValid(): void
	{
		$this->runValid('extends-valid');
	}

	// =========================================================================
	// Block: @import / @import_raw
	// =========================================================================

	/**
	 * {@import 'path' ctx} -- renders an external template inline.
	 * Also tests {@import 'path' $$} -- pass raw DataContext.
	 *
	 * @throws BlateException
	 */
	public function testImportValid(): void
	{
		$this->runValid('import-valid');
	}

	/**
	 * {@import_raw 'path'} -- includes the file source verbatim (no parse).
	 *
	 * @throws BlateException
	 */
	public function testImportRaw(): void
	{
		$this->runValid('template-import-raw');
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Custom Blate::registerHelper() helper.
	 * {hello(...)} full-stack lookup and {$hello(...)} helper-only lookup.
	 *
	 * @throws BlateException
	 */
	public function testHelperCustom(): void
	{
		$this->runValid('global-helper');
	}

	/**
	 * {$upper(...)} and {... | upper}: resolver uses helpers layer only,
	 * even when user data has a key 'upper' containing a malicious callable.
	 *
	 * @throws BlateException
	 */
	public function testHelperSecured(): void
	{
		$this->runValid('helper-secured');
	}

	/**
	 * String helpers: upper, lower, ucfirst, trim, truncate, substr, replace,
	 * startsWith, endsWith, contains, split, pad, repeat, sprintf, stripTags, nl2br.
	 *
	 * @throws BlateException
	 */
	public function testHelpersString(): void
	{
		$this->runValid('helpers-string');
	}

	/**
	 * Array + numeric helpers: length, first, last, join, sort, reverse,
	 * unique, slice, flatten, sum, avg, min, max, abs, round, clamp,
	 * floor, ceil, number, json.
	 *
	 * @throws BlateException
	 */
	public function testHelpersArrayAndNumeric(): void
	{
		$this->runValid('helpers-array');
	}

	/**
	 * Logic + type helpers: default, if, cast, has, type.
	 *
	 * @throws BlateException
	 */
	public function testHelpersMisc(): void
	{
		$this->runValid('helpers-misc');
	}

	/**
	 * HTML helpers: escapeHtml, escape, attrs, json.
	 *
	 * escapeHtml MUST use htmlspecialchars (not htmlentities) so that multibyte
	 * UTF-8 characters such as accented letters are preserved as-is and not
	 * corrupted into named HTML entities (e.g. e-acute -> &eacute;).  Only the
	 * five HTML-sensitive ASCII characters (<, >, &, ", ') must be encoded.
	 *
	 * attrs() default mode follows HTML boolean attribute semantics:
	 *   - false / null / '' -> omit the attribute entirely (absent = false)
	 *   - true              -> standalone attribute with no value (e.g. disabled)
	 * attrs() raw mode (pass 1 in template expressions, true in PHP) emits all
	 * values as strings for data-* / ARIA / custom attributes:
	 *   - false -> attr="false"   null -> attr=""   true -> attr="true"
	 *
	 * json() default flags include JSON_HEX_TAG and JSON_HEX_AMP so that
	 * angle brackets and ampersands are unicode-escaped, making the output safe
	 * to embed directly inside HTML <script> blocks without XSS risk.
	 *
	 * @throws BlateException
	 */
	public function testHelpersHtml(): void
	{
		$this->runValid('helpers-html');
	}

	// =========================================================================
	// Forbidden patterns: parse-time errors
	// =========================================================================

	/**
	 * {@unknown} -- block name not registered.
	 */
	public function testForbiddenBlockUnknown(): void
	{
		$this->runInvalid('block-undefined');
	}

	/**
	 * {/each _} -- close tag with unexpected suffix token.
	 */
	public function testForbiddenBlockBadCloser(): void
	{
		$this->runInvalid('block-unclosed-2');
	}

	/**
	 * {@each} opened but never closed.
	 */
	public function testForbiddenBlockEachUnclosed(): void
	{
		$this->runInvalid('block-unclosed-1');
	}

	/**
	 * {@if} opened but never closed.
	 */
	public function testForbiddenBlockIfUnclosed(): void
	{
		$this->runInvalid('block-if-unclosed');
	}

	/**
	 * {:case} appearing after {:default} inside {@switch}.
	 */
	public function testForbiddenSwitchCaseAfterDefault(): void
	{
		$this->runInvalid('block-switch-after-default');
	}

	/**
	 * Unknown breakpoint {:foo} inside {@switch}.
	 */
	public function testForbiddenSwitchBadBreakpoint(): void
	{
		$this->runInvalid('block-switch-bad-breakpoint');
	}

	/**
	 * Unknown breakpoint {:foo} inside {@each}.
	 */
	public function testForbiddenEachBadBreakpoint(): void
	{
		$this->runInvalid('block-each-bad-breakpoint');
	}

	/**
	 * Expression starting with an invalid character (%).
	 */
	public function testForbiddenExpressionInvalid1(): void
	{
		$this->runInvalid('expression-invalid-1');
	}

	/**
	 * Extra closing brace }{} with no matching opener.
	 */
	public function testForbiddenExpressionInvalid2(): void
	{
		$this->runInvalid('expression-invalid-2');
	}

	/**
	 * Malformed float literal (4.25.8 -- two dots).
	 */
	public function testForbiddenExpressionInvalid3(): void
	{
		$this->runInvalid('expression-invalid-3');
	}

	/**
	 * Malformed number (4.m258 -- letter immediately after dot).
	 */
	public function testForbiddenExpressionInvalid4(): void
	{
		$this->runInvalid('expression-invalid-4');
	}

	/**
	 * Empty subscript [].
	 */
	public function testForbiddenExpressionInvalid5(): void
	{
		$this->runInvalid('expression-invalid-5');
	}

	/**
	 * Trailing comma outside a function-call argument list.
	 */
	public function testForbiddenExpressionInvalid6(): void
	{
		$this->runInvalid('expression-invalid-6');
	}

	/**
	 * Calling a number literal: 9().
	 */
	public function testForbiddenExpressionInvalid7(): void
	{
		$this->runInvalid('expression-invalid-7');
	}

	/**
	 * Empty expression tag {  }.
	 */
	public function testForbiddenExpressionInvalid8(): void
	{
		$this->runInvalid('expression-invalid-8');
	}

	/**
	 * Trailing operator with no right operand: { 1+ }.
	 */
	public function testForbiddenExpressionInvalid9(): void
	{
		$this->runInvalid('expression-invalid-9');
	}

	/**
	 * {@slot} used outside an {@extends} block.
	 */
	public function testForbiddenSlotOutsideExtends(): void
	{
		$this->runInvalid('slot-default-outside-extends');
	}

	/**
	 * Duplicate slot name inside {@extends}.
	 */
	public function testForbiddenSlotDuplicateName(): void
	{
		$this->runInvalid('slot-name-exists');
	}

	/**
	 * {@extends} referencing its own file path (circular include).
	 */
	public function testForbiddenExtendsSamePath(): void
	{
		$this->runInvalid('extends-same-path');
	}

	/**
	 * Non-{@slot} block found as direct child of {@extends}.
	 */
	public function testForbiddenExtendsUnexpectedChildBlock(): void
	{
		$this->runInvalid('extends-unexpected-child-block');
	}

	/**
	 * Expression found as direct child of {@extends} (outside a slot).
	 */
	public function testForbiddenExtendsUnexpectedChildExpression(): void
	{
		$this->runInvalid('extends-unexpected-child-expression');
	}

	/**
	 * Raw text content found as direct child of {@extends} (outside a slot).
	 */
	public function testForbiddenExtendsUnexpectedContent(): void
	{
		$this->runInvalid('extends-unexpected-content');
	}

	/**
	 * {@import} referencing its own file path (circular include).
	 */
	public function testForbiddenImportSamePath(): void
	{
		$this->runInvalid('import-same-path');
	}

	// =========================================================================
	// Forbidden patterns: runtime errors
	// =========================================================================

	/**
	 * {$noSuchHelper(x)} -- helper-only lookup for an unregistered name
	 * throws BlateRuntimeException at render time.
	 */
	public function testForbiddenHelperNotFound(): void
	{
		$this->runInvalid('helper-not-found');
	}

	/**
	 * {x | noSuchHelper} -- pipe filter with unregistered helper name
	 * throws BlateRuntimeException at render time.
	 */
	public function testForbiddenPipeFilterNotFound(): void
	{
		$this->runInvalid('pipe-filter-not-found');
	}

	// =========================================================================
	// Infrastructure
	// =========================================================================

	protected function runValid(string $name): void
	{
		$sample_dir      = BLATE_TEST_TEMPLATES_DIR . '/' . $name;
		$template        = $sample_dir . '/template.blate';
		$output_file     = $sample_dir . '/output.txt';
		$full_error_file = $sample_dir . '/error.full.txt';
		$inject_file     = $sample_dir . '/inject.php';
		$output          = null;
		$error           = null;

		try {
			if (\file_exists($inject_file)) {
				$bl = Blate::fromPath($template)
					->parse(true);

				$inject = include $inject_file;
				$output = $bl->runGet($inject);
			} else {
				$parser = new Parser(Blate::fromPath($template));
				$parser->parse();
				$output = $parser->getClassBody();
			}
		} catch (BlateException|BlateRuntimeException $e) {
			$error = $e->describe(false, false);
			\file_put_contents($full_error_file, $e->describe(false, true));
		}

		if ($error) {
			throw new RuntimeException('Unexpected error, see details in: ' . $full_error_file);
		}

		if (\file_exists($full_error_file)) {
			\unlink($full_error_file);
		}

		if (!\file_exists($output_file)) {
			\file_put_contents($output_file, $output);
		} else {
			$expected = \file_get_contents($output_file);
			self::assertSame($expected, $output);
		}
	}

	protected function runInvalid(string $name): void
	{
		$sample_dir      = BLATE_TEST_TEMPLATES_DIR . '/' . $name;
		$template        = $sample_dir . '/template.blate';
		$error_file      = $sample_dir . '/error.txt';
		$full_error_file = $sample_dir . '/error.full.txt';
		$inject_file     = $sample_dir . '/inject.php';
		$error           = null;

		try {
			$bl = Blate::fromPath($template)
				->parse(true);

			if (\file_exists($inject_file)) {
				$inject = include $inject_file;
				$bl->runGet($inject);
			}
		} catch (BlateException|BlateRuntimeException $e) {
			$error = $e->describe(false, false);
			\file_put_contents($full_error_file, $e->describe(false, true));
		}

		if (null === $error) {
			throw new RuntimeException('Exception expected, none found.');
		}

		if (!\file_exists($error_file)) {
			\file_put_contents($error_file, $error);
		} else {
			$expected = \file_get_contents($error_file);
			self::assertSame($expected, $error);
		}
	}
}
