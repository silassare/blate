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
use Blate\Expressions\ExpressionParser;
use Blate\Interfaces\ParserInterface;
use Blate\Interfaces\TokenHandlerInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use Blate\Token;
use Override;

/**
 * Class Parenthesis.
 *
 * Handles the opening parenthesis (() in expressions.
 *
 * Three distinct roles:
 *   1. Expression grouping at head or after an operator:    (a + b) * c
 *   2. Function call on a variable name or subscript:       fn(a, b), arr[0](a)
 *   3. Nested grouping inside another open paren:           ((a + b))
 *
 * For function calls (case 2), ->call( is emitted and arguments are parsed
 * with IN_FUNC_CALL_ARGS=true so commas are allowed, and with ALLOW_EMPTY=true
 * so zero-argument calls are valid.
 */
class Parenthesis implements TokenHandlerInterface
{
	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException when ( appears in an invalid position
	 */
	#[Override]
	public function handle(ParserInterface $parser, TokenInterface $token, bool $is_head): void
	{
		$current   = $token;
		$lexer     = $parser->getLexer();
		$prev      = $lexer->lookBackward(true);
		$prev_type = $prev?->getType();

		if ($is_head) { // expression start
			$parser->write($current);
			$lexer->move();
			$parser->parse($parser->whileInChildrenOf($current));
		} else {
			switch ($prev_type) {
				case Token::T_PAREN_OPEN: // ((expression))
					$parser->write($current);
					$lexer->move();
					$parser->parse($parser->whileInChildrenOf($current));

					break;

				case Token::T_NAME: // var_name(arg1, arg2)
				case Token::T_SQUARE_BRACKET_CLOSE: // var_name[expression](arg1, arg2)
					if (!$parser->getActiveChain($current)) {
						throw BlateParserException::withToken(Message::UNEXPECTED, $current);
					}

					$call_loc     = $current->getChunk()->getLocation();
					$call_loc_str = $call_loc['line'] . ':' . $call_loc['index'];

					$lexer->move(); // consume (

					// Peek at the first significant token inside () to decide whether
					// args follow, so we can conditionally emit ', ' after the location.
					$peek = $lexer->current();

					if (null !== $peek && Token::T_WHITESPACE === $peek->getType()) {
						$peek = $lexer->lookForward(true);
					}

					$has_args = null !== $peek && Token::T_PAREN_CLOSE !== $peek->getType();

					if ($has_args) {
						$parser->write('->call(\'' . $call_loc_str . '\', ');
					} else {
						$parser->write('->call(\'' . $call_loc_str . '\'');
					}

					$parser->parse($parser->whileInChildrenOf($current), [
						ExpressionParser::IN_FUNC_CALL_ARGS => true,
						ExpressionParser::ALLOW_EMPTY       => true,
					]);

					break;

				default:
					if ($prev && ($prev->isOperator() || $prev->isLogicalCondition() || $prev->isComparator())) {
						$parser->write($current);
						$lexer->move();
						$parser->parse($parser->whileInChildrenOf($current));
					} else {
						throw BlateParserException::withToken(Message::UNEXPECTED, $current);
					}
			}
		}
	}
}
