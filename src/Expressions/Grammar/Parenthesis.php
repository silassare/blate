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
use Blate\Expressions\Utils;
use Blate\Interfaces\ParserInterface;
use Blate\Interfaces\TokenHandlerInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use Blate\Token;

/**
 * Class Parenthesis.
 */
class Parenthesis implements TokenHandlerInterface
{
	public function handle(ParserInterface $parser, TokenInterface $token, bool $is_head): void
	{
		$current   = $token;
		$lexer     = $parser->getLexer();
		$prev      = $lexer->lookBackward(true);
		$prev_type = $prev?->getType();

		if ($is_head) {// expression start
			$parser->write($current);
			$lexer->move();
			$parser->parse(Utils::whileInChildrenOf($current));
		} else {
			switch ($prev_type) {
				case Token::T_PAREN_OPEN: // ((expression))
					$parser->write($current);
					$lexer->move();
					$parser->parse(Utils::whileInChildrenOf($current));

					break;

				case Token::T_NAME: // var_name(arg1, arg2)
				case Token::T_SQUARE_BRACKET_CLOSE: // var_name[expression](arg1, arg2)
					if (!Utils::getActiveChain($current)) {
						throw BlateParserException::withToken(Message::UNEXPECTED, $current);
					}

					$parser->write('->call(');
					$lexer->move();
					$parser->parse(Utils::whileInChildrenOf($current), [
						ExpressionParser::IN_FUNC_CALL_ARGS => true,
						ExpressionParser::ALLOW_EMPTY       => true,
					]);

					break;

				default:
					if ($prev && ($prev->isOperator() || $prev->isLogicalCondition() || $prev->isComparator())) {
						$parser->write($current);
						$lexer->move();
						$parser->parse(Utils::whileInChildrenOf($current));
					} else {
						throw BlateParserException::withToken(Message::UNEXPECTED, $current);
					}
			}
		}
	}
}
