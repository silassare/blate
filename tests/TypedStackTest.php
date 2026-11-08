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

use Blate\Interfaces\TokenInterface;
use Blate\Lexer;
use Blate\TypedStack;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The code buffers a parser writes into while a block is open.
 *
 * @internal
 */
#[CoversNothing]
final class TypedStackTest extends TestCase
{
	public function testTheCodeOfABufferThatWasOpenedIsWhatWasWrittenIntoIt(): void
	{
		$token = self::aToken();
		$stack = new TypedStack();

		$stack->start($token)
			->write('echo 1;');

		self::assertSame('echo 1;', $stack->getCode($token));
	}

	/**
	 * A token nothing was written for answers an empty buffer and not an absent one: both callers
	 * put this straight into generated code, where the absent one was a type error waiting for a
	 * slot that was declared and left empty.
	 */
	public function testTheCodeOfABufferNobodyOpenedIsEmpty(): void
	{
		self::assertSame('', new TypedStack()->getCode(self::aToken()));
	}

	private static function aToken(): TokenInterface
	{
		$tokens = new Lexer('hello')->tokenize();

		self::assertNotEmpty($tokens);

		return $tokens[0];
	}
}
