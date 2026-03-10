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

namespace Blate;

use Blate\Exceptions\BlateException;
use Blate\Exceptions\BlateParserException;
use Blate\Exceptions\BlateRuntimeException;
use Blate\Interfaces\BlockInterface;
use Blate\Interfaces\TokenInterface;
use PHPUtils\FS\PathUtils;
use PHPUtils\Str;
use Throwable;

\define('BLATE_ASSETS_DIR', __DIR__ . \DIRECTORY_SEPARATOR . 'assets' . \DIRECTORY_SEPARATOR);
\define('BLATE_TEMPLATE_RESOLVE_DIR', __DIR__ . \DIRECTORY_SEPARATOR);

/**
 * Class Blate.
 */
final class Blate
{
	public const VERSION = '1.1.0';

	public const VERSION_NAME = 'Blate ' . self::VERSION;

	public const COMPILE_DIR_NAME = 'blate_cache' . \DIRECTORY_SEPARATOR . self::VERSION;

	public const TAG_OPENER = '{';

	public const TAG_CLOSER = '}';

	public const BLOCK_OPEN = '@';

	public const BLOCK_BREAKPOINT = ':';

	public const BLOCK_CLOSE = '/';

	public const BLOCK_COMMENT = '#';

	public const BLOCK_PHP = '~';

	public const BLOCK_SAFE_ECHO = '=';

	public const DATA_CONTEXT_VAR = '$context';

	public const DATA_CONTEXT_REF = '$$';

	public const HELPER_PREFIX_CHAR = '$';

	public const SLOT_METHOD_PREFIX = 'slot_';

	public const BLOCK_NAME_PATTERN = '~^[a-z_][a-z0-9_]*$~i';

	public const HELPER_NAME_PATTERN = '~^[a-z_][a-z0-9$_]*$~i';

	public const VAR_NAME_PATTERN = '~^[a-z_][a-z0-9$_]*$~i';

	private static int $var_counter = 0;

	private static array $checked_classes = [];

	private static ?string $cache_dir = null;

	/**
	 * @var array<string, class-string<BlockInterface>>
	 */
	private static array $blocks = [];

	/**
	 * @var array<string, callable>
	 */
	private static array $helpers = [];

	/**
	 * @var array<string, mixed>
	 */
	private static array $GLOBAL_VARS = [];

	/**
	 * @var array<string, true>
	 */
	private static array $GLOBAL_VARS_CONSTANT = [];

	/**
	 * @var array<string, true>
	 */
	private static array $disabled_blocks = [];

	/**
	 * @var array<string, true>
	 */
	private static array $disabled_helpers = [];

	private string $input;

	private string $output = '';

	private ?string $src_path = null;

	private string $dst_path;

	private string $class_name;

	private string $class_fqn;

	/**
	 * Blate constructor.
	 *
	 * @throws BlateException
	 */
	private function __construct(private string $template, private bool $is_url = true, bool $timed_class_name = false)
	{
		if ($this->is_url) {
			$this->template = PathUtils::resolve(BLATE_TEMPLATE_RESOLVE_DIR, $this->template);
			$this->input    = self::loadFile($this->template);
			$this->src_path = $this->template;

			$path_info = \pathinfo($this->template);

			// change only if:
			// - file content change
			// - or file path change
			// - or blate version change
			$out_file_name = $path_info['filename'] . '_' . ($hash = \md5($this->template . \md5_file($this->template) . self::VERSION));
		} else {
			$this->input = $this->template;

			$out_file_name = 'blate_' . ($hash = \md5($this->template . self::VERSION));
		}

		$sub1 = \substr($hash, 0, 2);
		$sub2 = \substr($hash, 2, 2);

		// When a global cache dir is configured, all compiled files go there.
		// Otherwise fall back to a blate_cache/ sibling of the template file.
		if (null !== self::$cache_dir) {
			$dst_dir = self::$cache_dir;
		} elseif ($this->is_url) {
			$dst_dir = $path_info['dirname'];
		} else {
			$dst_dir = BLATE_TEMPLATE_RESOLVE_DIR;
		}

		$dst_dir .= \DIRECTORY_SEPARATOR . self::COMPILE_DIR_NAME . \DIRECTORY_SEPARATOR . $sub1 . \DIRECTORY_SEPARATOR . $sub2;

		$this->dst_path = $dst_dir . \DIRECTORY_SEPARATOR . $out_file_name . '.php';

		if (!$timed_class_name) {
			$this->class_name = 'blate_tpl_' . \md5($out_file_name);
		} else {
			$this->class_name = 'blate_tpl_' . \md5($out_file_name . \microtime());
		}

		$this->class_fqn = '\Blate\\' . $this->class_name;
	}

	/**
	 * Set the global cache directory.
	 *
	 * When set, all compiled templates are stored under this directory
	 * (instead of a blate_cache/ folder next to each template file).
	 * Pass null to revert to the default per-template-directory behaviour.
	 *
	 * @param null|string $dir Absolute path to the desired cache root, or null to reset
	 */
	public static function setCacheDir(?string $dir): void
	{
		self::$cache_dir = null !== $dir ? \rtrim($dir, '/\\') : null;
	}

	/**
	 * Get the configured global cache directory, or null if none is set.
	 *
	 * @return null|string
	 */
	public static function getCacheDir(): ?string
	{
		return self::$cache_dir;
	}

	/**
	 * Get Blate instance from a file path.
	 *
	 * @throws BlateException
	 */
	public static function fromPath(string $path, bool $timed_class_name = false): self
	{
		return new self($path, true, $timed_class_name);
	}

	/**
	 * Get Blate instance from a string.
	 *
	 * @throws BlateException
	 */
	public static function fromString(string $template, bool $timed_class_name = false): self
	{
		return new self($template, false, $timed_class_name);
	}

	/**
	 * Get the input.
	 *
	 * @return string
	 */
	public function getInput(): string
	{
		return $this->input;
	}

	/**
	 * Parse the template.
	 *
	 * @return $this
	 *
	 * @throws BlateException|BlateParserException
	 */
	public function parse(bool $force_new_compile = false): self
	{
		try {
			if ($force_new_compile || !\file_exists($this->dst_path)) {
				$parser       = new Parser($this);
				$this->output = $parser->parse()
					->getClassBody();
				$this->save();
			}
		} catch (BlateException|BlateRuntimeException $t) {
			throw $t->templateSource($this->template);
		}

		return $this;
	}

	/**
	 * Load a file.
	 *
	 * @throws BlateException
	 */
	public static function loadFile(string $src): string
	{
		if (empty($src) || !\file_exists($src) || !\is_file($src) || !\is_readable($src)) {
			throw new BlateException(\sprintf(Message::FILE_IS_NOT_READABLE, $src));
		}

		return \file_get_contents($src);
	}

	/**
	 * Get the source path.
	 *
	 * @return null|string
	 */
	public function getSrcPath(): ?string
	{
		return $this->src_path;
	}

	/**
	 * Get the source directory.
	 *
	 * @return string
	 */
	public function getSrcDir(): string
	{
		return $this->src_path ? \pathinfo($this->src_path, \PATHINFO_DIRNAME) : BLATE_TEMPLATE_RESOLVE_DIR;
	}

	/**
	 * Get the destination path.
	 *
	 * @return string
	 */
	public function getDestPath(): string
	{
		return $this->dst_path;
	}

	/**
	 * Get a parsed template instance.
	 *
	 * @throws BlateException
	 */
	public function getParsedInstance(): TemplateParsed
	{
		$f_exists = false;
		if (\file_exists($this->dst_path)) {
			$f_exists = true;

			include $this->dst_path;
		}

		$fqn = $this->class_fqn;

		if (isset(self::$checked_classes[$fqn]) && \class_exists($fqn)) {
			return new $fqn();
		}

		unset(self::$checked_classes[$fqn]);

		$f_exists && @\unlink($this->dst_path);

		// let's parse
		$o = $this->is_url ? self::fromPath($this->src_path) : self::fromString($this->input);
		$o->parse();

		return $o->getParsedInstance();
	}

	/**
	 * Run the template with the given data.
	 *
	 * @throws BlateException
	 */
	public function runGet(array|object $data): string
	{
		$instance = $this->getParsedInstance();

		\ob_start();

		try {
			$instance->build(new DataContext($data, $this));
		} catch (Throwable $e) {
			\ob_end_clean();

			throw $e;
		}

		return \ob_get_clean();
	}

	/**
	 * @throws BlateException
	 */
	public function runSave(array $data, string $to): void
	{
		$to = PathUtils::resolve(__DIR__, $to);

		$this->writeFile($to, $this->runGet($data));
	}

	/**
	 * Register a parsed template.
	 */
	public static function register(array $desc): bool
	{
		if (isset($desc['class_fqn'], $desc['version']) && self::VERSION === $desc['version']) {
			$fqn                         = $desc['class_fqn'];
			self::$checked_classes[$fqn] = true;

			// make sure the class is not already defined
			return !\class_exists($fqn);
		}

		return false;
	}

	/**
	 * Create a new variable name using a process-level static counter.
	 *
	 * NOTE: all built-in block implementations now call Parser::createVar()
	 * instead, which uses a per-instance counter safe for concurrent use in
	 * persistent runtimes (Swoole, RoadRunner, PHP Fibers).  This static
	 * method is kept for backward compatibility with external callers.
	 *
	 * @return string
	 */
	public static function createVar(): string
	{
		return '$_bv_' . (self::$var_counter++);
	}

	/**
	 * Get a slot method name.
	 *
	 * @param string $name the slot name
	 *
	 * @return string
	 */
	public static function slotMethodName(string $name): string
	{
		return Str::toMethodName(self::SLOT_METHOD_PREFIX . $name);
	}

	/**
	 * Register block.
	 *
	 * The block name must match the pattern: {@see Blate::BLOCK_NAME_PATTERN}.
	 *
	 * @param string                       $name        the block name
	 * @param class-string<BlockInterface> $block_class the block class name
	 */
	public static function registerBlock(string $name, string $block_class): void
	{
		if (!\preg_match(self::BLOCK_NAME_PATTERN, $name)) {
			throw new BlateRuntimeException(\sprintf(Message::INVALID_BLOCK_NAME, $name, self::BLOCK_NAME_PATTERN));
		}

		self::$blocks[$name] = $block_class;
	}

	/**
	 * Disable a registered block.
	 *
	 * A disabled block behaves as if it is not registered: any template that
	 * references it will fail at compile time with a BLOCK_UNDEFINED error.
	 * The block class and registration are preserved; call enableBlock() to
	 * fully restore it.
	 *
	 * @param string $name the block name
	 */
	public static function disableBlock(string $name): void
	{
		if (!isset(self::$blocks[$name])) {
			throw new BlateRuntimeException(\sprintf(Message::BLOCK_NOT_REGISTERED, $name));
		}

		self::$disabled_blocks[$name] = true;
	}

	/**
	 * Re-enable a previously disabled block.
	 *
	 * @param string $name the block name
	 */
	public static function enableBlock(string $name): void
	{
		unset(self::$disabled_blocks[$name]);
	}

	/**
	 * Returns true when the block is registered and not disabled.
	 *
	 * @param string $name the block name
	 *
	 * @return bool
	 */
	public static function isBlockEnabled(string $name): bool
	{
		return isset(self::$blocks[$name]) && !isset(self::$disabled_blocks[$name]);
	}

	/**
	 * Register a helper.
	 *
	 * The helper name must match the pattern: {@see Blate::HELPER_NAME_PATTERN}.
	 *
	 * ```
	 * Blate::registerHelper('hello', function (string $name) {
	 *    return 'Hello ' . $name;
	 * });
	 * ```
	 *
	 * The helper can be invoked from a template in several ways:
	 *
	 * ```
	 * {hello('world')}
	 * ```
	 * Resolves `hello` through the full context stack (scope layers, then user data,
	 * then global vars, then the helpers layer). A user-data key named `hello` will shadow the helper.
	 *
	 * ```
	 * {$hello('world')}
	 * ```
	 * The `$` prefix forces helper-only resolution: only the helpers layer is
	 * consulted, so user data can never shadow the helper. Use this form when
	 * the data supplied to the template comes from untrusted sources.
	 *
	 * ```
	 * {expr | hello}
	 * ```
	 * Pipe-filter names always use helper-only resolution (equivalent to `$hello`).
	 *
	 * All three forms produce the same output: `Hello world`.
	 *
	 * @param string   $name   the helper name
	 * @param callable $helper the helper callable
	 */
	public static function registerHelper(string $name, callable $helper): void
	{
		if (!\preg_match(self::HELPER_NAME_PATTERN, $name)) {
			throw new BlateRuntimeException(\sprintf(Message::INVALID_HELPER_NAME, $name, self::HELPER_NAME_PATTERN));
		}

		if (isset(self::$helpers[$name])) {
			throw (new BlateRuntimeException(\sprintf(Message::HELPER_ALREADY_REGISTERED, $name)))
				->suspectCallable(self::$helpers[$name]);
		}

		self::$helpers[$name]                            = $helper;
		self::$helpers[self::HELPER_PREFIX_CHAR . $name] = $helper;
	}

	/**
	 * Disable a registered helper.
	 *
	 * A disabled helper is excluded from the helpers layer of DataContext.
	 * Plain-name lookups ({name()}) may still resolve through user data;
	 * helper-only lookups ({$name()} and pipe filters) will fail at render
	 * time with a HELPER_NOT_FOUND error. Call enableHelper() to restore it.
	 *
	 * @param string $name the helper name (with or without the '$' prefix)
	 */
	public static function disableHelper(string $name): void
	{
		$canonical = \ltrim($name, self::HELPER_PREFIX_CHAR);

		if (!isset(self::$helpers[$canonical])) {
			throw new BlateRuntimeException(\sprintf(Message::HELPER_NOT_FOUND, $canonical));
		}

		self::$disabled_helpers[$canonical] = true;
	}

	/**
	 * Re-enable a previously disabled helper.
	 *
	 * @param string $name the helper name (with or without the '$' prefix)
	 */
	public static function enableHelper(string $name): void
	{
		unset(self::$disabled_helpers[\ltrim($name, self::HELPER_PREFIX_CHAR)]);
	}

	/**
	 * Returns true when the helper is registered and not disabled.
	 *
	 * @param string $name the helper name (with or without the '$' prefix)
	 *
	 * @return bool
	 */
	public static function isHelperEnabled(string $name): bool
	{
		$canonical = \ltrim($name, self::HELPER_PREFIX_CHAR);

		return isset(self::$helpers[$canonical]) && !isset(self::$disabled_helpers[$canonical]);
	}

	/**
	 * Get the registered helpers.
	 *
	 * Disabled helpers are excluded from the returned array.
	 *
	 * @return array<string, callable>
	 */
	public static function getHelpers(): array
	{
		if (empty(self::$disabled_helpers)) {
			return self::$helpers;
		}

		$prefix = self::HELPER_PREFIX_CHAR;

		return \array_filter(
			self::$helpers,
			static fn (string $key): bool => !isset(self::$disabled_helpers[\ltrim($key, $prefix)]),
			\ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Get the registered blocks.
	 *
	 * @return array<string, class-string<BlockInterface>>
	 */
	public static function getBlocks(): array
	{
		return self::$blocks;
	}

	/**
	 * Returns a new block instance.
	 *
	 * @param Parser         $parser
	 * @param TokenInterface $token
	 *
	 * @return null|BlockInterface
	 */
	public static function getBlockInstance(Parser $parser, TokenInterface $token): ?BlockInterface
	{
		$name = $token->getValue();

		if (isset(self::$blocks[$name]) && !isset(self::$disabled_blocks[$name])) {
			/** @var class-string<BlockInterface> $class_name */
			$class_name = self::$blocks[$name];

			return new $class_name($parser, $token);
		}

		return null;
	}

	/**
	 * Register a global variable.
	 *
	 * The name must be a valid Blate identifier matching VAR_NAME_PATTERN
	 * (`~^[a-z_][a-z0-9$_]*$~i`), i.e. a letter or underscore followed by
	 * letters, digits, `$`, or underscores.
	 *
	 * @param string $name     the variable name
	 * @param mixed  $value    the variable value
	 * @param bool   $editable whether the variable is editable (default: false)
	 *
	 * @throws BlateRuntimeException when the name is not a valid identifier (Message::INVALID_VAR_NAME)
	 * @throws BlateRuntimeException when a constant global is re-registered (Message::GLOBAL_VAR_IS_NOT_EDITABLE)
	 */
	public static function registerGlobalVar(string $name, mixed $value, bool $editable = false): void
	{
		$is_const = self::$GLOBAL_VARS_CONSTANT[$name] ?? false;

		if ($is_const) {
			throw new BlateRuntimeException(\sprintf(Message::GLOBAL_VAR_IS_NOT_EDITABLE, $name));
		}

		if (!\preg_match(self::VAR_NAME_PATTERN, $name)) {
			throw new BlateRuntimeException(\sprintf(Message::INVALID_VAR_NAME, $name, self::VAR_NAME_PATTERN));
		}

		if (!$editable) {
			self::$GLOBAL_VARS_CONSTANT[$name] = true;
		}

		self::$GLOBAL_VARS[$name] = $value;
	}

	/**
	 * Get the global variables.
	 */
	public static function getGlobalVars(): mixed
	{
		return self::$GLOBAL_VARS;
	}

	/**
	 * Save the compiled template.
	 *
	 * @throws BlateException
	 */
	private function save(): void
	{
		$path    = $this->dst_path;
		$dst_dir = \dirname($path);

		if (!\file_exists($dst_dir) && !\mkdir($dst_dir, 0775, true) && !\is_dir($dst_dir)) {
			throw new BlateException(\sprintf(Message::FILE_IS_NOT_WRITABLE, $path));
		}

		$code = self::loadFile(BLATE_ASSETS_DIR . 'output.php.sample');

		$code = \str_replace(
			[
				'{blate_version}',
				'{blate_version_name}',
				'{blate_src_path}',
				'{blate_compile_time}',
				'{blate_class_name}',
				'{blate_class_fqn}',
				'{blate_class_body}',
			],
			[
				self::VERSION,
				self::VERSION_NAME,
				$this->src_path ?? '__none__',
				\time(),
				$this->class_name,
				$this->class_fqn,
				$this->output,
			],
			$code
		);

		$this->writeFile($path, $code);
	}

	/**
	 * Write a file atomically using a temp file + rename.
	 *
	 * Writing directly to the target with fopen('w') truncates it immediately,
	 * which means a concurrent process doing include() on the same path could
	 * read a partially-written PHP file and trigger a fatal parse error.
	 * rename() is atomic on POSIX systems, so the target is either the old
	 * content or the new complete content -- never a partial write.
	 *
	 * @throws BlateException
	 */
	private function writeFile(string $path, string $content): void
	{
		$dir = \dirname($path);

		// make sure that file is writable at this location,
		if (!\file_exists($dir) || !\is_writable($dir)) {
			throw new BlateException(\sprintf(Message::FILE_IS_NOT_WRITABLE, $path));
		}

		$tmp = $path . '.' . \uniqid('', true) . '.tmp';
		$f   = \fopen($tmp, 'w');

		if (false === $f) {
			throw new BlateException(\sprintf(Message::FILE_IS_NOT_WRITABLE, $path));
		}

		\fwrite($f, $content);
		\fclose($f);
		\rename($tmp, $path);
	}
}
