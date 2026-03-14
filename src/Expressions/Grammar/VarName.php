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
 *     Emits $context->chain('L:I')->get('L:I', 'name') and marks the active chain head via $parser->setActiveChain().
 *     At the end of the chain (no further property access) the ->val() terminator is appended.
 *
 *   Chain continuation (when preceded by a dot):
 *     Emits ->get('L:I', 'name') to extend the current chain.
 *     The dot handler already consumed the dot token; here we just append the property.
 *
 *   Special case: $$ (DATA_CONTEXT_REF) is the raw DataContext reference and
 *   does not start a chain -- it emits $context directly without ->val().
 *
 *   PHP literals (true/false/null, case-insensitive) at chain head:
 *     Emitted verbatim as the PHP literal (always lowercase). No chain is started
 *     and no ->val() is appended. This only applies when the keyword is the head
 *     of an expression -- inside a dot-chain (foo.true.bar) it is a normal
 *     property lookup.
 */
class VarName implements TokenHandlerInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function handle(ParserInterface $parser, TokenInterface $token, bool $is_head): void
	{
		$current    = $token;
		$lexer      = $parser->getLexer();
		$prev       = $lexer->lookBackward(true);
		$prev_type  = $prev?->getType();
		$is_ref     = false;
		$is_literal = false;

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

			$var_name      = $current->getValue();
			$is_ref        = (Blate::DATA_CONTEXT_REF === $var_name);
			$is_helper_ref = !$is_ref && \str_starts_with($var_name, Blate::HELPER_PREFIX_CHAR);
			$lower_name    = \strtolower($var_name);
			$is_literal    = !$is_ref && !$is_helper_ref
				&& ('true' === $lower_name || 'false' === $lower_name || 'null' === $lower_name);

			if ($is_literal) {
				// PHP reserved literal at expression head -- emitted verbatim, no chain started.
				// foo.true and foo.null inside a dot-chain are still normal property lookups.
				$parser->write($lower_name);
			} elseif ($is_ref) {
				$parser->setActiveChain($current, $current);
				$parser->write(Blate::DATA_CONTEXT_VAR);
			} elseif ($is_helper_ref) {
				$parser->setActiveChain($current, $current);
				$actual_name  = \substr($var_name, \strlen(Blate::HELPER_PREFIX_CHAR)); // strip leading prefix
				$head_loc     = $current->getChunk()->getLocation();
				$head_loc_str = $head_loc['line'] . ':' . $head_loc['index'];
				$parser->write(Blate::DATA_CONTEXT_VAR . '->chain(\'' . $head_loc_str . '\')->getHelper(\'' . $head_loc_str . '\', \'');
				$parser->write($actual_name);
				$parser->write('\')');
			} else {
				$parser->setActiveChain($current, $current);
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
			!$is_literal
			&& (
				!$next
				|| $next->isComparator()
				|| $next->isLogicalCondition()
				|| $next->isOperator()
				|| $next->isGroupCloser()
				|| Token::T_PIPE === $next->getType() // pipe filter terminates the chain
				|| Token::T_SEMICOLON === $next->getType() // {@set} assignment separator
			)
		) {
			$parser->setActiveChain($current, null);
			$parser->write('->val()');
		}
	}
}
