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
use Blate\Exceptions\BlateParserException;
use Blate\Lexer;
use Blate\Token;
use Blate\Tests\Utils\TestPaths;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class LexerTest extends TestCase
{
	/**
	 * @throws BlateException
	 */
	public function testTokenize(): void
	{
		$dir           = TestPaths::SAMPLES . '/lexer';
		$template_file = $dir . '/template.blate';
		$tokens_file   = $dir . '/tokens.json';
		$template      = \file_get_contents($template_file);

		$lexer = new Lexer($template);

		$tokens = $lexer->tokenize();
		$output = \json_encode($tokens, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);

		if (\file_exists($tokens_file)) {
			$expected = \file_get_contents($tokens_file);

			// A token's ref carries a counter of the process, so what this file holds depends on
			// how many tokens were made before this test ran: compared with that number blanked,
			// since it says nothing about tokenizing and everything about the order of the suite.
			self::assertSame(self::withoutTokenCounter($expected), self::withoutTokenCounter($output));
		} else {
			\file_put_contents($tokens_file, $output);
		}
	}

	private static function withoutTokenCounter(string $json): string
	{
		return \preg_replace('~"t\d+_l~', '"t_l', $json) ?? $json;
	}

	/**
	 * A call may ask for a value at any token type, and then there is no type name to print when
	 * the input ends first: asking for the name of a type that was never given raised a type error
	 * where a parser error was due.
	 */
	public function testTheEndOfTheInputNamesWhatWasExpectedWhenNoTypeWasAskedFor(): void
	{
		try {
			new Lexer('')->nextIs(null, '=');

			self::fail('The end of the input must be refused.');
		} catch (BlateParserException $e) {
			self::assertStringContainsString('"="', $e->getMessage());
		}
	}

	public function testTheEndOfTheInputNamesTheTypeWhenOneWasAskedFor(): void
	{
		try {
			new Lexer('')->nextIs(Token::T_NAME);

			self::fail('The end of the input must be refused.');
		} catch (BlateParserException $e) {
			self::assertStringContainsString('T_NAME', $e->getMessage());
		}
	}
}
