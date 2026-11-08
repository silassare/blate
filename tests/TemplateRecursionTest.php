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
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A template that renders itself, and the depth that is the only thing bounding it.
 *
 * The templates are written here rather than kept under `tests/samples/`, because what has to be
 * expressed is a path: an absolute one, one spelled with a doubled separator, one reached through a
 * directory symlink. A sample directory cannot know where it will live.
 *
 * Why the depth and not the template's identity: `{@import}` does not compile what it names. It
 * emits `Blate::fromPath(...)->getParsedInstance()->run(...)` into the compiled class, so compiling
 * A never opens B and the chain exists only while the templates run -- and the same template is on
 * that chain twice both when a tree is rendered and when an import does not end. What differs is
 * the data, which the chain cannot see.
 *
 * @internal
 */
#[CoversClass(Blate::class)]
final class TemplateRecursionTest extends TestCase
{
	private string $dir;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . \uniqid('blate_recursion_', true);

		\mkdir($this->dir . \DIRECTORY_SEPARATOR . 'cache', 0o775, true);

		// Compiled files go where this test can reach them, and not beside the templates.
		Blate::setCacheDir($this->dir . \DIRECTORY_SEPARATOR . 'cache');
	}

	#[Override]
	protected function tearDown(): void
	{
		Blate::setCacheDir(null);
		Blate::setMaxRenderDepth(null);

		// Writable again first: one test takes the write bit off the cache directory on purpose.
		@\chmod($this->dir . \DIRECTORY_SEPARATOR . 'cache', 0o775);
		\exec('rm -rf ' . \escapeshellarg($this->dir));

		parent::tearDown();
	}

	/**
	 * Two partials rendering a tree through each other, which is the shape a tree had to take while
	 * a template was refused the right to import itself.
	 */
	public function testTwoPartialsRenderingATreeThroughEachOther(): void
	{
		$tree = $this->write(
			'tree.blate',
			"<ul>{@each node in nodes}{@import './branch.blate' \$map('node', node)}{/each}</ul>"
		);
		$this->write(
			'branch.blate',
			"<li>{node.label}{@if node.children}{@import './tree.blate' \$map('nodes', node.children)}{/if}</li>"
		);

		$out = Blate::fromPath($tree)
			->parse(true)
			->runGet(['nodes' => [
				['label' => 'a', 'children' => [['label' => 'a1', 'children' => null]]],
				['label' => 'b', 'children' => null],
			]]);

		self::assertSame('<ul><li>a<ul><li>a1</li></ul></li><li>b</li></ul>', $out);
	}

	/**
	 * The same tree in one file, which is what lifting the parse-time refusal allows.
	 */
	public function testOnePartialImportingItselfRendersATree(): void
	{
		$tree = $this->write(
			'self-tree.blate',
			'<ul>{@each node in nodes}<li>{node.label}'
				. "{@if node.children}{@import './self-tree.blate' \$map('nodes', node.children)}{/if}"
				. '</li>{/each}</ul>'
		);

		$out = Blate::fromPath($tree)
			->parse(true)
			->runGet(['nodes' => [
				['label' => 'a', 'children' => [
					['label' => 'a1', 'children' => [['label' => 'a2', 'children' => null]]],
				]],
				['label' => 'b', 'children' => null],
			]]);

		self::assertSame(
			'<ul><li>a<ul><li>a1<ul><li>a2</li></ul></li></ul></li><li>b</li></ul>',
			$out
		);
	}

	/**
	 * A tree deeper than the default depth renders once the project says how deep its model goes.
	 */
	public function testARaisedDepthRendersADeeperTree(): void
	{
		$tree = $this->write(
			'deep.blate',
			'{node.label}'
				. "{@if node.child}{@import './deep.blate' \$map('node', node.child)}{/if}"
		);

		$depth = 80;
		$data  = ['label' => (string) $depth, 'child' => null];

		for ($i = $depth - 1; $i > 0; --$i) {
			$data = ['label' => (string) $i, 'child' => $data];
		}

		$expected = \implode('', \range(1, $depth));

		Blate::setMaxRenderDepth($depth + 1);

		$out = Blate::fromPath($tree)
			->parse(true)
			->runGet(['node' => $data]);

		self::assertSame($expected, $out);
	}

	public function testAnImportThatNeverEndsIsRefusedAtTheDepth(): void
	{
		// The unbounded shape: the same template with the same data, so nothing shrinks.
		$runaway = $this->write('runaway.blate', "x{@import './runaway.blate' \$\$}");

		try {
			Blate::fromPath($runaway)
				->parse(true)
				->runGet([]);

			self::fail('An import that does not end must be refused.');
		} catch (BlateRuntimeException $e) {
			self::assertStringContainsString('more than 64 levels deep', $e->getMessage());
			self::assertStringContainsString(Blate::pathKey($runaway), $e->getMessage());
		}
	}

	public function testTwoTemplatesImportingEachOtherWithUnchangedDataAreRefused(): void
	{
		$a = $this->write('a.blate', "A{@import './b.blate' \$\$}");
		$b = $this->write('b.blate', "B{@import './a.blate' \$\$}");

		try {
			Blate::fromPath($a)
				->parse(true)
				->runGet([]);

			self::fail('A pair of imports that does not end must be refused.');
		} catch (BlateRuntimeException $e) {
			self::assertStringContainsString('more than 64 levels deep', $e->getMessage());

			// The innermost frames, so the author sees which templates repeat.
			self::assertStringContainsString(
				Blate::pathKey($a) . ' -> ' . Blate::pathKey($b),
				$e->getMessage()
			);
		}
	}

	public function testTwoTemplatesExtendingEachOtherAreRefused(): void
	{
		$a = $this->write('a.blate', "{@extends './b.blate' \$\$}\n{@slot title} A {/slot}\n{/extends}");
		$this->write('b.blate', "{@extends './a.blate' \$\$}\n{@slot title} B {/slot}\n{/extends}");

		try {
			Blate::fromPath($a)
				->parse(true)
				->runGet([]);

			self::fail('A pair of extends that does not end must be refused.');
		} catch (BlateRuntimeException $e) {
			self::assertStringContainsString('levels deep', $e->getMessage());
		}
	}

	/**
	 * A depth is counted on the render stack, so this is one level however many nodes it holds.
	 */
	public function testManySiblingImportsAreOneLevel(): void
	{
		$this->write('partial.blate', 'P');
		$page = $this->write(
			'page.blate',
			"{@each i in items}{@import './partial.blate' \$\$}{/each}"
		);

		Blate::setMaxRenderDepth(2);

		$out = Blate::fromPath($page)
			->parse(true)
			->runGet(['items' => \range(1, 200)]);

		self::assertSame(\str_repeat('P', 200), $out);
	}

	public function testADepthBelowOneIsRefused(): void
	{
		$this->expectException(BlateRuntimeException::class);
		$this->expectExceptionMessage('allows no template to render at all');

		Blate::setMaxRenderDepth(0);
	}

	public function testATemplateImportingItselfByAnotherSpellingOfItsPathRenders(): void
	{
		// A doubled separator names the same file, and now that a template may import itself there
		// is nothing to refuse: it renders, and the depth is what would stop it if it did not end.
		$doubled = $this->dir . \DIRECTORY_SEPARATOR . \DIRECTORY_SEPARATOR . 'self.blate';
		$self    = $this->write(
			'self.blate',
			\sprintf("s{@if go}{@import '%s' \$map('go', false)}{/if}", $doubled)
		);

		$out = Blate::fromPath($self)
			->parse(true)
			->runGet(['go' => true]);

		self::assertSame('ss', $out);
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

	/**
	 * What a language server compiles: the buffer, as a string, with no path of its own.
	 */
	public function testATemplateCompiledFromAStringExtendsAFile(): void
	{
		$base = $this->write('base.blate', '<p>{@slot title}none{/slot}</p>');

		$out = Blate::fromString("{@extends '" . $base . "' \$\$}\n{@slot title}A{/slot}\n{/extends}")
			->parse(true)
			->runGet([]);

		self::assertSame('<p>A</p>', \trim($out));
	}

	private function write(string $name, string $content): string
	{
		$path = $this->dir . \DIRECTORY_SEPARATOR . $name;

		\file_put_contents($path, $content);

		return $path;
	}
}
