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

use Blate\Exceptions\BlateParserException;
use Blate\Expressions\Grammar\AtomicValue;
use Blate\Expressions\Grammar\Dot;
use Blate\Expressions\Grammar\Operator;
use Blate\Expressions\Grammar\Parenthesis;
use Blate\Expressions\Grammar\SquareBracket;
use Blate\Expressions\Grammar\VarName;
use Blate\Interfaces\LexerInterface;
use Blate\Interfaces\ParserInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use Blate\Token;

/**
 * Class ExpressionParser.
 */
class ExpressionParser implements ParserInterface
{
	public const IN_FUNC_CALL_ARGS = 1;
	public const ALLOW_EMPTY       = 2;

	protected string $output = '';

	/**
	 * ExpressionParser constructor.
	 *
	 * @param LexerInterface $lexer
	 */
	public function __construct(protected LexerInterface $lexer) {}

	/**
	 * {@inheritDoc}
	 */
	public function getLexer(): LexerInterface
	{
		return $this->lexer;
	}

	/**
	 * {@inheritDoc}
	 */
	public function write(string|TokenInterface $str): static
	{
		if ($str instanceof TokenInterface) {
			$this->output .= $str->getValue();
		} else {
			$this->output .= $str;
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getOutput(): string
	{
		return $this->output;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException
	 */
	public function parse(?callable $while_true = null, ?array $options = []): static
	{
		/** @var TokenInterface[] $close_stack */
		$close_stack             = [];
		$has_val                 = false;
		$first_significant_token = null;
		$in_args                 = (bool) ($options[self::IN_FUNC_CALL_ARGS] ?? false);
		$allow_empty             = (bool) ($options[self::ALLOW_EMPTY] ?? false);

		while (($current = $this->lexer->current()) !== null) {
			if ($while_true && false === $while_true($current, $this)) {
				break;
			}

			$t_type                  = $current->getType();

			if (Token::T_WHITESPACE === $t_type) {
				$this->write($current);
				$this->lexer->move();

				continue;
			}

			$is_head = null === $first_significant_token;

			if (!$first_significant_token) {
				$first_significant_token = $current;
			}

			if (!$in_args && Token::T_COMMA === $t_type) {
				throw BlateParserException::withToken(Message::UNEXPECTED, $current);
			}

			switch ($t_type) {
				case Token::T_PAREN_CLOSE:
				case Token::T_SQUARE_BRACKET_CLOSE:
					$to_close = \array_pop($close_stack);
					if ($to_close && $current->isGroupCloserOf($to_close)) {
						if (Utils::getActiveChain($current)) {
							$this->write(')');
							$next = $this->lexer->lookForward(true);
							if (!$next || $next->isComparator() || $next->isLogicalCondition() || $next->isOperator() || $next->isGroupCloser()) {
								Utils::setActiveChain($current, null);
								$this->write('->val()');
							}
						} else {
							$this->write($current);
						}

						$this->lexer->move();
					} else {
						throw BlateParserException::withToken(Message::UNEXPECTED, $current);
					}

					break;

				case Token::T_PAREN_OPEN:
					$close_stack[] = $current;
					$handler       = new Parenthesis();
					$handler->handle($this, $current, $is_head);
					$has_val = true;

					break;

				case Token::T_SQUARE_BRACKET_OPEN:
					$close_stack[] = $current;
					$handler       = new SquareBracket();
					$handler->handle($this, $current, $is_head);

					break;

				case Token::T_NAME:
					$handler = new VarName();
					$handler->handle($this, $current, $is_head);
					$has_val = true;

					break;

				case Token::T_DNUMBER:
				case Token::T_STRING:
					$handler = new AtomicValue();
					$handler->handle($this, $current, $is_head);
					$has_val = true;

					break;

				case Token::T_DOT:
					$handler = new Dot();
					$handler->handle($this, $current, $is_head);

					break;

				default:
					if ($current->isOperator()) {
						$handler = new Operator();
						$handler->handle($this, $current, $is_head);
					} elseif ($current->isLogicalCondition() || $current->isComparator()) {
						$prev      = $this->lexer->lookBackward(true);
						$prev_type = $prev?->getType();
						if ($prev && ($prev->isGroupCloser() || Token::T_DNUMBER === $prev_type || Token::T_STRING === $prev_type || Token::T_NAME === $prev_type)) {
							$this->write($current);
							$this->lexer->move();
						} else {
							throw BlateParserException::withToken(Message::UNEXPECTED, $current);
						}
					} else {
						throw BlateParserException::withToken(Message::UNEXPECTED, $current);
					}
			}

			if ($current->getChunk()
				->eof()) {
				break;
			}
		}

		if (($latest_expression_token = $this->lexer->lookBackward(true))
			&& ($latest_expression_token->isOperator()
				|| $latest_expression_token->isLogicalCondition()
				|| $current->isComparator())) {
			throw BlateParserException::withToken(Message::UNEXPECTED_END_OF_EXPRESSION, $latest_expression_token);
		}

		$last_unclosed_group = \array_pop($close_stack);

		if ($last_unclosed_group) {
			throw BlateParserException::withToken(Message::GROUP_NEVER_CLOSED_IN_EXPRESSION, $last_unclosed_group);
		}

		if (!$has_val && !$allow_empty) {
			$unexpected = $current ?? $this->lexer->lookBackward(true);

			if ($unexpected) {
				throw BlateParserException::withToken(Message::UNEXPECTED_WHILE_EXPECTING_EXPRESSION, $unexpected);
			}

			throw new BlateParserException(Message::UNEXPECTED_END_OF_EXPRESSION);
		}

		return $this;
	}
}
