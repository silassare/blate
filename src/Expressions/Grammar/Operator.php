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
 */
class Operator implements TokenHandlerInterface
{
	public function handle(ParserInterface $parser, TokenInterface $token, bool $is_head): void
	{
		$current   = $token;
		$lexer     = $parser->getLexer();
		$prev      = $lexer->lookBackward(true);
		$prev_type = $prev?->getType();

		if (Token::T_NOT === $current->getType()) {
			if ($is_head // !expression
				|| $prev?->isOperator() // + !expression
			) {
				$parser->write($current);
				$lexer->move();
			} else {
				throw BlateParserException::withToken(Message::UNEXPECTED, $current);
			}
		} elseif ($prev && ($prev->isGroupCloser() || Token::T_DNUMBER === $prev_type || Token::T_STRING === $prev_type || Token::T_NAME === $prev_type)) {
			$parser->write($current);
			$lexer->move();
		} else {
			throw BlateParserException::withToken(Message::UNEXPECTED, $current);
		}
	}
}
