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

use Blate\Blate;
use Blate\Exceptions\BlateParserException;
use Blate\Expressions\Helpers;
use Blate\Interfaces\ParserInterface;
use Blate\Interfaces\TokenHandlerInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use Blate\Token;

/**
 * Class VarName.
 */
class VarName implements TokenHandlerInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function handle(ParserInterface $parser, TokenInterface $token, bool $is_head): void
	{
		$current             = $token;
		$lexer               = $parser->getLexer();
		$prev                = $lexer->lookBackward(true);
		$prev_type           = $prev?->getType();
		$is_ref              = false;

		if (Token::T_DOT === $prev_type) { // foo.bar
			$parser->write('->get(\'');
			$parser->write($current->getValue());
			$parser->write('\')');
			$next = $lexer->lookForward(true);
			$lexer->move();
		} elseif ($is_head// expression start: var_name
				  || (
				  	$prev
					  && (
					  	Token::T_PAREN_OPEN === $prev_type // (var_name)
					   || Token::T_SQUARE_BRACKET_OPEN === $prev_type // [var_name]
					   || $prev->isOperator() // operator var_name
					   || $prev->isLogicalCondition() // condition var_name
					   || $prev->isComparator() // comparator var_name
					  )
				  )
		) {
			if (Helpers::getActiveChain($current)) {
				throw BlateParserException::withToken(Message::UNEXPECTED, $current);
			}

			Helpers::setActiveChain($current, $current);

			$var_name            = $current->getValue();
			$is_ref              = (Blate::DATA_CONTEXT_REF === $var_name);

			if ($is_ref) {
				$parser->write(Blate::DATA_CONTEXT_VAR);
			} else {
				$parser->write(Blate::DATA_CONTEXT_VAR . '->chain()->get(\'');
				$parser->write($var_name);
				$parser->write('\')');
			}

			$next = $lexer->lookForward(true);
			$lexer->move();
		} else {
			throw BlateParserException::withToken(Message::UNEXPECTED, $current);
		}

		if ($is_ref) {
			Helpers::setActiveChain($current, null);
		} elseif (!$next || $next->isComparator() || $next->isLogicalCondition() || $next->isOperator() || $next->isGroupCloser()) {
			Helpers::setActiveChain($current, null);
			$parser->write('->val()');
		}
	}
}
