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

use Blate\Exceptions\BlateException;
use Blate\Lexer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class LexerTest extends TestCase
{
	/**
	 * @throws BlateException
	 */
	public function testTokenize(): void
	{
		$dir           = BLATE_TEST_TEMPLATES_DIR . '/lexer';
		$template_file = $dir . '/template.blate';
		$tokens_file   = $dir . '/tokens.json';
		$template      = \file_get_contents($template_file);

		$lexer = new Lexer($template);

		$tokens = $lexer->tokenize();
		$output = \json_encode($tokens, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);

		if (\file_exists($tokens_file)) {
			$expected = \file_get_contents($tokens_file);
			self::assertSame($expected, $output);
		} else {
			\file_put_contents($tokens_file, $output);
		}
	}
}
