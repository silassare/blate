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
use Override;

/**
 * Class SquareBracket.
 *
 * Handles the opening square bracket ([) for subscript access.
 *
 * Valid contexts (all require an active chain):
 *   var_name[expr]           -- after T_NAME
 *   var_name()[expr]         -- after T_PAREN_CLOSE (call result subscript)
 *   var_name[expr][expr]     -- after T_SQUARE_BRACKET_CLOSE (chained subscript)
 *
 * Emits ->get( and then recursively parses the sub-expression until the
 * matching T_SQUARE_BRACKET_CLOSE is consumed by ExpressionParser.
 */
class SquareBracket implements TokenHandlerInterface
{
	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException when [ appears in an invalid position
	 */
	#[Override]
	public function handle(ParserInterface $parser, TokenInterface $token, bool $is_head): void
	{
		$current   = $token;
		$lexer     = $parser->getLexer();
		$prev      = $lexer->lookBackward(true);
		$prev_type = $prev?->getType();

		switch ($prev_type) {
			case Token::T_NAME: // var_name[expression]
			case Token::T_PAREN_CLOSE: // var_name()[expression]
			case Token::T_SQUARE_BRACKET_CLOSE: // var_name[expression][expression]
				if (!$parser->getActiveChain($current)) {
					throw BlateParserException::withToken(Message::UNEXPECTED, $current);
				}

				$sub_loc     = $current->getChunk()->getLocation();
				$sub_loc_str = $sub_loc['line'] . ':' . $sub_loc['index'];
				$parser->write('->get(\'' . $sub_loc_str . '\', ');
				$lexer->move();
				$parser->parse($parser->whileInChildrenOf($current));

				break;

			default:
				throw BlateParserException::withToken(Message::UNEXPECTED, $current);
		}
	}
}
