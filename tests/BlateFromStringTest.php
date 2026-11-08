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
use Blate\BlateTemplateParsed;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A template that has no file: what a language server checks, and what `fromString()` compiles.
 *
 * Having a path and being read from one are one fact, and the class used to carry both a flag and
 * the path: these are the two places that asked the flag and then used the path.
 *
 * @internal
 */
#[CoversClass(Blate::class)]
final class BlateFromStringTest extends TestCase
{
	private string $dir;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . \uniqid('blate_string_', true);

		\mkdir($this->dir, 0o775, true);
		Blate::setCacheDir($this->dir);
	}

	#[Override]
	protected function tearDown(): void
	{
		Blate::setCacheDir(null);
		\exec('rm -rf ' . \escapeshellarg($this->dir));

		parent::tearDown();
	}

	public function testATemplateFromAStringHasNothingToNameOnARenderStack(): void
	{
		self::assertNull(Blate::fromString('hi')->renderKey());
	}

	public function testATemplateFromAFileIsNamedByItsPath(): void
	{
		$path = $this->dir . \DIRECTORY_SEPARATOR . 'page.blate';

		\file_put_contents($path, 'hi');

		self::assertSame(Blate::pathKey($path), Blate::fromPath($path)->renderKey());
	}

	/**
	 * The compiled file is replaced and the template compiled once more, from where it came from:
	 * a string template has only its own source to go back to.
	 */
	public function testATemplateFromAStringIsCompiledAgainFromItsOwnSource(): void
	{
		// Never parsed, so the class was never loaded and the retry is the only way to one: a
		// template that was parsed in this process answers from the class already in memory.
		$blate = Blate::fromString('[{name}]');
		$dest  = $blate->getDestPath();

		if (!\is_dir($dir = \dirname($dest))) {
			\mkdir($dir, 0o775, true);
		}

		// A compiled file that declares no class: what a half-written cache file looks like. It is
		// replaced, and what the template is compiled from the second time is its own string.
		\file_put_contents($dest, '<?php // nothing here' . \PHP_EOL);

		self::assertInstanceOf(BlateTemplateParsed::class, $blate->getParsedInstance());

		// What it was compiled from, read back through the class the retry declared.
		self::assertSame('[world]', $blate->runGet(['name' => 'world']));
	}
}
