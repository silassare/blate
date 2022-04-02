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

namespace Blate\Expressions;

use Blate\Interfaces\LexerInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Token;

/**
 * Class Expression.
 */
class Expression
{
	public function get(LexerInterface $lexer): string
	{
		$ep = new ExpressionParser($lexer);

		return $ep->parse(static function (TokenInterface $token) {
			return Token::T_TAG_CLOSE !== $token->getType();
		})->getOutput();
	}

	public function getWhileTrue(LexerInterface $lexer, callable $whileTrue): string
	{
		$ep = new ExpressionParser($lexer);

		return $ep->parse($whileTrue)->getOutput();
	}
}
