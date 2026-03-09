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

namespace Blate\Expressions\Grammar;

use Blate\Exceptions\BlateParserException;
use Blate\Interfaces\ParserInterface;
use Blate\Interfaces\TokenHandlerInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use Blate\Token;

/**
 * Class Operator.
 *
 * Handles arithmetic and logical-not operators in expression parsing.
 *
 * Dispatch rules:
 *   T_NOT (!):
 *     - valid at expression head:               {!flag}
 *     - valid after any operator:               {a + !b}
 *     - valid after a comparator:               {a == !b}
 *     - valid after a logical condition (&&/||): {a && !b}
 *   T_OPERATOR (+, -, *, /, %, ^):
 *     Binary form: previous token produced a value (name, number, string, group-closer).
 *     Unary form (- or + only): at head, after an operator, comparator, or logical condition.
 */
class Operator implements TokenHandlerInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function handle(ParserInterface $parser, TokenInterface $token, bool $is_head): void
	{
		$current   = $token;
		$lexer     = $parser->getLexer();
		$prev      = $lexer->lookBackward(true);
		$prev_type = $prev?->getType();
		$value     = $current->getValue();

		if (Token::T_NOT === $current->getType()) {
			// T_NOT (!) valid at head or after any operator, comparator, or logical condition.
			if (
				$is_head
				|| ($prev && ($prev->isOperator() || $prev->isComparator() || $prev->isLogicalCondition()))
			) {
				$parser->write($current);
				$lexer->move();
			} else {
				throw BlateParserException::withToken(Message::UNEXPECTED, $current);
			}
		} elseif ($prev && ($prev->isGroupCloser() || Token::T_DNUMBER === $prev_type || Token::T_STRING === $prev_type || Token::T_NAME === $prev_type)) {
			// Binary operator: the previous token produced a value.
			$parser->write($current);
			$lexer->move();
		} elseif (('-' === $value || '+' === $value)
			&& ($is_head || ($prev && ($prev->isOperator() || $prev->isComparator() || $prev->isLogicalCondition())))
		) {
			// Unary + or -: at head ({-5}), after a binary operator ({a + -b}),
			// or after a comparator/logical ({a > -1}, {a && -b}).
			$parser->write($current);
			$lexer->move();
		} else {
			throw BlateParserException::withToken(Message::UNEXPECTED, $current);
		}
	}
}
