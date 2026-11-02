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
use Blate\Exceptions\BlateParserException;
use Blate\Exceptions\BlateRuntimeException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A template that imports or extends its way back to itself.
 *
 * The templates are written here rather than kept under `tests/samples/`, because what has to be
 * expressed is a path: an absolute one, one spelled with a doubled separator, one reached through a
 * directory symlink. A sample directory cannot know where it will live.
 *
 * Why this cannot be caught when compiling: `{@import}` does not compile what it names. It emits
 * `Blate::fromPath(...)->getParsedInstance()->run(...)` into the compiled class, so compiling A
 * never opens B, and the chain exists only while the templates run.
 *
 * @internal
 */
#[CoversClass(Blate::class)]
final class TemplateCycleTest extends TestCase
{
	private string $dir;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . \uniqid('blate_cycle_', true);

		\mkdir($this->dir . \DIRECTORY_SEPARATOR . 'cache', 0o775, true);

		// Compiled files go where this test can reach them, and not beside the templates.
		Blate::setCacheDir($this->dir . \DIRECTORY_SEPARATOR . 'cache');
	}

	#[Override]
	protected function tearDown(): void
	{
		Blate::setCacheDir(null);

		// Writable again first: one test takes the write bit off the cache directory on purpose.
		@\chmod($this->dir . \DIRECTORY_SEPARATOR . 'cache', 0o775);
		\exec('rm -rf ' . \escapeshellarg($this->dir));

		parent::tearDown();
	}

	public function testTwoTemplatesImportingEachOtherAreRefusedAtRender(): void
	{
		$a = $this->write('a.blate', "A{@import './b.blate' \$\$}");
		$b = $this->write('b.blate', "B{@import './a.blate' \$\$}");

		try {
			Blate::fromPath($a)
				->parse(true)
				->runGet([]);

			self::fail('An import cycle must be refused.');
		} catch (BlateRuntimeException $e) {
			self::assertStringContainsString('is already being rendered', $e->getMessage());

			// The chain, in order, so the author knows which templates to look at.
			self::assertStringContainsString(
				self::chain($a, $b, $a),
				$e->getMessage()
			);
		}
	}

	public function testTwoTemplatesExtendingEachOtherAreRefusedAtRender(): void
	{
		$a = $this->write('a.blate', "{@extends './b.blate' \$\$}\n{@slot title} A {/slot}\n{/extends}");
		$this->write('b.blate', "{@extends './a.blate' \$\$}\n{@slot title} B {/slot}\n{/extends}");

		try {
			Blate::fromPath($a)
				->parse(true)
				->runGet([]);

			self::fail('An extends cycle must be refused.');
		} catch (BlateRuntimeException $e) {
			self::assertStringContainsString('is already being rendered', $e->getMessage());
		}
	}

	public function testAThreeTemplateCycleIsRefusedWithTheWholeChain(): void
	{
		$a = $this->write('a.blate', "{@import './b.blate' \$\$}");
		$b = $this->write('b.blate', "{@import './c.blate' \$\$}");
		$c = $this->write('c.blate', "{@import './a.blate' \$\$}");

		try {
			Blate::fromPath($a)
				->parse(true)
				->runGet([]);

			self::fail('A cycle through three templates must be refused.');
		} catch (BlateRuntimeException $e) {
			self::assertStringContainsString(self::chain($a, $b, $c, $a), $e->getMessage());
		}
	}

	public function testATemplateImportingItselfByAnotherSpellingOfItsPathIsRefused(): void
	{
		// A guard, not a proof: `PathUtils::resolve()` does leave a doubled separator alone, so the
		// comparison was expected to miss this -- and it does not, in practice, for either spelling
		// here. Kept because the refusal is the behaviour that matters, and because comparing on
		// `Blate::pathKey()` is what makes it hold for spellings nobody has tried.
		$doubled = $this->dir . \DIRECTORY_SEPARATOR . \DIRECTORY_SEPARATOR . 'self.blate';
		$self    = $this->write('self.blate', \sprintf("{@import '%s' \$\$}", $doubled));

		try {
			Blate::fromPath($self)
				->parse(true)
				->runGet([]);

			self::fail('A template importing itself must be refused.');
		} catch (BlateParserException|BlateRuntimeException $e) {
			self::assertStringContainsString('itself', $e->getMessage());
		}
	}

	public function testATemplateImportingItselfThroughADirectorySymlinkIsRefused(): void
	{
		// Also a guard rather than a proof, for the same reason as the test above.
		$mirror = $this->dir . \DIRECTORY_SEPARATOR . 'mirror';

		if (!@\symlink($this->dir, $mirror)) {
			self::markTestSkipped('This platform did not allow a directory symlink.');
		}

		$self = $this->write(
			'linked.blate',
			\sprintf("{@import '%s' \$\$}", $mirror . \DIRECTORY_SEPARATOR . 'linked.blate')
		);

		try {
			Blate::fromPath($self)
				->parse(true)
				->runGet([]);

			self::fail('A template importing itself through a symlink must be refused.');
		} catch (BlateParserException|BlateRuntimeException $e) {
			self::assertStringContainsString('itself', $e->getMessage());
		}
	}

	public function testACompiledFileThatDeclaresNoClassAndCannotBeReplacedSaysSo(): void
	{
		$template = $this->write('stale.blate', 'hello');
		$blate    = Blate::fromPath($template);

		$blate->parse(true);

		$compiled = $blate->getDestPath();

		self::assertFileExists($compiled);

		// What a crashed or truncated write leaves: the file is there and declares nothing. Then the
		// directory is made unwritable, so the stale file cannot be unlinked -- and `parse()` skips
		// compiling while a file exists, so the recovery used to call itself for ever.
		\file_put_contents($compiled, "<?php\n\n// nothing\n");
		\chmod(\dirname($compiled), 0o555);

		if (\is_writable(\dirname($compiled))) {
			self::markTestSkipped('This user can write the directory anyway (running as root?).');
		}

		try {
			Blate::fromPath($template)->getParsedInstance();

			self::fail('A compiled file that declares no class must be reported.');
		} catch (BlateException $e) {
			self::assertStringContainsString('does not declare class', $e->getMessage());
		} finally {
			\chmod(\dirname($compiled), 0o775);
		}
	}

	public function testATemplateMayBeImportedTwiceSideBySide(): void
	{
		// The guard is about a template being inside itself, not about rendering one twice: a
		// partial used in two places is ordinary, and each render ends before the next begins.
		$this->write('partial.blate', 'P');
		$page = $this->write('page.blate', "{@import './partial.blate' \$\$}{@import './partial.blate' \$\$}");

		$out = Blate::fromPath($page)
			->parse(true)
			->runGet([]);

		self::assertSame('PP', $out);
	}

	/**
	 * The chain as the message spells it: one spelling of each path, in the order they opened.
	 */
	private static function chain(string ...$paths): string
	{
		return \implode(' -> ', \array_map(static fn (string $p): string => Blate::pathKey($p), $paths));
	}

	private function write(string $name, string $content): string
	{
		$path = $this->dir . \DIRECTORY_SEPARATOR . $name;

		\file_put_contents($path, $content);

		return $path;
	}
}
