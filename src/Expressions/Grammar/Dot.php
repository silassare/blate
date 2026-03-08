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
use Blate\Expressions\Utils;
use Blate\Interfaces\ParserInterface;
use Blate\Interfaces\TokenHandlerInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use Blate\Token;

/**
 * Class Dot.
 *
 * Handles the dot (.) property-access operator in expressions.
 *
 * The dot itself is NOT written to output.  Its presence signals the VarName
 * handler (which fires next) to emit ->get('name') instead of starting a
 * fresh chain with $context->chain()->get('name').
 *
 * Valid contexts:
 *   foo.bar         -- after T_NAME
 *   foo().bar       -- after T_PAREN_CLOSE
 *   foo['x'].bar    -- after T_SQUARE_BRACKET_CLOSE
 */
class Dot implements TokenHandlerInterface
{
	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException when dot appears outside a valid chain context
	 */
	public function handle(ParserInterface $parser, TokenInterface $token, bool $is_head): void
	{
		$current = $token;

		if (!Utils::getActiveChain($current)) {
			throw BlateParserException::withToken(Message::UNEXPECTED, $current);
		}

		$lexer     = $parser->getLexer();
		$prev      = $lexer->lookBackward(true);
		$prev_type = $prev?->getType();
		if (Token::T_NAME !== $prev_type && Token::T_PAREN_CLOSE !== $prev_type && Token::T_SQUARE_BRACKET_CLOSE !== $prev_type) {
			throw BlateParserException::withToken(Message::UNEXPECTED, $current);
		}

		$next = $lexer->lookForward(true);

		if (!$next) {
			throw new BlateParserException(Message::UNEXPECTED_END_OF_EXPRESSION);
		}

		$next_type = $next->getType();
		if (Token::T_NAME !== $next_type) {
			throw BlateParserException::withToken(Message::UNEXPECTED, $next);
		}

		$lexer->move();
	}
}
