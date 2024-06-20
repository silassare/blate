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

\define('BLATE_ASSETS_DIR', __DIR__ . \DIRECTORY_SEPARATOR . 'assets' . \DIRECTORY_SEPARATOR);
\define('BLATE_TEMPLATE_RESOLVE_DIR', __DIR__ . \DIRECTORY_SEPARATOR);

/**
 * Class Blate.
 */
final class Blate
{
	public const VERSION = '1.0.0';

	public const VERSION_NAME = 'Blate php-' . self::VERSION;

	public const COMPILE_DIR_NAME = 'blate_cache' . \DIRECTORY_SEPARATOR . self::VERSION;

	public const ESCAPE_CHAR = '\\';

	public const TAG_OPENER = '{';

	public const TAG_CLOSER = '}';

	public const BLOCK_OPEN = '@';

	public const BLOCK_BREAKPOINT = ':';

	public const BLOCK_CLOSE = '/';

	public const BLOCK_COMMENT = '#';

	public const DATA_CONTEXT_VAR = '$context';

	public const DATA_CONTEXT_REF = '$$';

	public const SLOT_METHOD_PREFIX = 'slot_';

	public const BLOCK_NAME_PATTERN = '~^[a-z_][a-z0-9_]*$~i';

	private static int $var_counter = 0;

	private static array $checked_classes = [];

	/**
	 * @var array<string, class-string<BlockInterface>>
	 */
	private static array $blocks = [];

	private string $input;

	private string $output = '';

	private ?string $src_path = null;

	private string $dst_path;

	private string $class_name;

	private string $class_fqn;

	/**
	 * @throws BlateException
	 */
	private function __construct(private string $template, protected bool $is_url = true, bool $timed_class_name = false)
	{
		if ($this->is_url) {
			$this->template = PathUtils::resolve(BLATE_TEMPLATE_RESOLVE_DIR, $this->template);
			$this->input    = self::loadFile($this->template);
			$this->src_path = $this->template;

			$path_info = \pathinfo($this->template);
			$dst_dir   = $path_info['dirname'];

			// change only if:
			// - file content change
			// - or file path change
			// - or blate version change
			$out_file_name = $path_info['filename'] . '_' . ($hash = \md5($this->template . \md5_file($this->template) . self::VERSION));
		} else {
			$this->input = $this->template;

			$dst_dir       = BLATE_TEMPLATE_RESOLVE_DIR;
			$out_file_name = 'blate_' . ($hash = \md5($this->template . self::VERSION));
		}

		$sub1    = \substr($hash, 0, 2);
		$sub2    = \substr($hash, 2, 2);
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
	 * @throws BlateException
	 */
	public static function fromPath(string $path, bool $timed_class_name = false): self
	{
		return new self($path, true, $timed_class_name);
	}

	/**
	 * @throws BlateException
	 */
	public static function fromString(string $template, bool $timed_class_name = false): self
	{
		return new self($template, false, $timed_class_name);
	}

	public function getInput(): string
	{
		return $this->input;
	}

	/**
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
	 * @throws BlateException
	 */
	public static function loadFile(string $src): string
	{
		if (empty($src) || !\file_exists($src) || !\is_file($src) || !\is_readable($src)) {
			throw new BlateException(\sprintf(Message::FILE_IS_NOT_READABLE, $src));
		}

		return \file_get_contents($src);
	}

	public function getSrcPath(): ?string
	{
		return $this->src_path;
	}

	public function getSrcDir(): string
	{
		return $this->src_path ? \pathinfo($this->src_path, \PATHINFO_DIRNAME) : BLATE_TEMPLATE_RESOLVE_DIR;
	}

	public function getDestPath(): string
	{
		return $this->dst_path;
	}

	/**
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
	 * @throws BlateException
	 */
	public function runGet(array|object $data): string
	{
		$instance = $this->getParsedInstance();

		\ob_start();
		$instance->build(new DataContext($data, $this));

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

	public static function createVar(): string
	{
		return '$bv_' . (self::$var_counter++);
	}

	public static function slotMethodName(string $name): string
	{
		return Str::toMethodName(self::SLOT_METHOD_PREFIX . $name);
	}

	/**
	 * Register block.
	 *
	 * @param string                       $name
	 * @param class-string<BlockInterface> $block_class
	 */
	public static function registerBlock(string $name, string $block_class): void
	{
		if (!\preg_match(self::BLOCK_NAME_PATTERN, $name)) {
			throw new BlateRuntimeException(\sprintf(Message::INVALID_BLOCK_NAME, $name, self::BLOCK_NAME_PATTERN));
		}

		self::$blocks[$name] = $block_class;
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

		if (isset(self::$blocks[$name])) {
			/**
			 * @var BlockInterface $class_name
			 */
			$class_name = self::$blocks[$name];

			return new $class_name($parser, $token);
		}

		return null;
	}

	public static function quote(string $str): string
	{
		// return '"' . \str_replace(['"', '$'], ['\\"', '\\$'], $str) . '"';
		return '\'' . \str_replace('\'', '\\\'', $str) . '\'';
	}

	public static function unquote(string $str): string
	{
		if (\str_starts_with($str, '\'') && \str_ends_with($str, '\'')) {
			return \substr($str, 1, -1);
		}

		if (\str_starts_with($str, '"') && \str_ends_with($str, '"')) {
			return \substr($str, 1, -1);
		}

		return $str;
	}

	/**
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
				$this->src_path,
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
	 * @throws BlateException
	 */
	private function writeFile(string $path, string $content): void
	{
		// make sure that file is writable at this location,
		if (!\file_exists(\dirname($path)) || !\is_writable(\dirname($path))) {
			throw new BlateException(\sprintf(Message::FILE_IS_NOT_WRITABLE, $path));
		}

		$f = \fopen($path, 'w');
		\fwrite($f, $content);
		\fclose($f);
	}
}
