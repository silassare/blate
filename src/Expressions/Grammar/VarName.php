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
use Blate\Interfaces\ParserInterface;
use Blate\Interfaces\TokenHandlerInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use Blate\Token;

/**
 * Class VarName.
 *
 * Handles T_NAME tokens in expressions.
 *
 * Two distinct roles depending on context:
 *
 *   Chain head (when token is at expression start or after an operator/comparator/logical/paren/bracket):
 *     Emits $context->chain()->get('name') and marks the active chain head via $parser->setActiveChain().
 *     At the end of the chain (no further property access) the ->val() terminator is appended.
 *
 *   Chain continuation (when preceded by a dot):
 *     Emits ->get('name') to extend the current chain.
 *     The dot handler already consumed the dot token; here we just append the property.
 *
 *   Special case: $$ (DATA_CONTEXT_REF) is the raw DataContext reference and
 *   does not start a chain -- it emits $context directly without ->val().
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
			$dot_loc     = $current->getChunk()->getLocation();
			$dot_loc_str = $dot_loc['line'] . ':' . $dot_loc['index'];
			$parser->write('->get(\'' . $dot_loc_str . '\', \'');
			$parser->write($current->getValue());
			$parser->write('\')');
			$next = $lexer->lookForward(true);
			$lexer->move();
		} elseif (
			$is_head // expression start: var_name
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
			if ($parser->getActiveChain($current)) {
				throw BlateParserException::withToken(Message::UNEXPECTED, $current);
			}

			$parser->setActiveChain($current, $current);

			$var_name            = $current->getValue();
			$is_ref              = (Blate::DATA_CONTEXT_REF === $var_name);

			if ($is_ref) {
				$parser->write(Blate::DATA_CONTEXT_VAR);
			} else {
				$head_loc     = $current->getChunk()->getLocation();
				$head_loc_str = $head_loc['line'] . ':' . $head_loc['index'];
				$parser->write(Blate::DATA_CONTEXT_VAR . '->chain(\'' . $head_loc_str . '\')->get(\'' . $head_loc_str . '\', \'');
				$parser->write($var_name);
				$parser->write('\')');
			}

			$next = $lexer->lookForward(true);
			$lexer->move();
		} else {
			throw BlateParserException::withToken(Message::UNEXPECTED, $current);
		}

		if ($is_ref) {
			$parser->setActiveChain($current, null);
		} elseif (
			!$next
			|| $next->isComparator()
			|| $next->isLogicalCondition()
			|| $next->isOperator()
			|| $next->isGroupCloser()
			|| Token::T_PIPE === $next->getType() // pipe filter terminates the chain
		) {
			$parser->setActiveChain($current, null);
			$parser->write('->val()');
		}
	}
}
