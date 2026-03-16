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

namespace Blate\Features;

use Blate\Blate;
use Blate\Exceptions\BlateParserException;
use Blate\Expressions\Expression;
use Blate\Interfaces\TokenInterface;
use Blate\Token;
use Override;
use PHPUtils\Str;

/**
 * Class BlockRepeat.
 *
 * Implements the {@repeat n}...{/repeat} loop block.
 *
 * Syntax forms:
 *   {@repeat n}              -- repeat n times
 *   {@repeat n as idx}       -- repeat n times, exposing the current index as idx (0-based)
 *
 * The count expression is cast to int at runtime. A new DataContext scope is
 * created for the loop body. Two built-in variables are available on every
 * iteration regardless of the syntax form used:
 *
 *   is_first  bool  true on the first iteration (index === 0)
 *   is_last   bool  true on the last iteration  (index === count - 1)
 *
 * When idx is specified it is also available as a normal template variable.
 *
 * Example:
 *   {@repeat 3}row{/repeat}              -> rowrowrow
 *   {@repeat items|length as i}{i}{/repeat}
 *
 * Compile-time output emits a for loop with set() calls for is_first, is_last,
 * and the optional index variable.
 */
class BlockRepeat extends Block
{
	public const NAME = 'repeat';

	private ?string $count_var = null;

	private ?string $index_var = null;

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException
	 */
	#[Override]
	public function onOpen(): void
	{
		$this->lexer->nextIs(Token::T_WHITESPACE);

		$count_expr = (new Expression())->getWhileTrue(
			$this->lexer,
			static function (TokenInterface $t): bool {
				return Token::T_TAG_CLOSE !== $t->getType()
					&& !(Token::T_NAME === $t->getType() && 'as' === $t->getValue());
			}
		);

		$count_expr = \trim($count_expr);

		// When the expression boundary is the 'as' keyword (T_NAME), the chain
		// resolver cannot append ->val() automatically because it only does so for
		// group-closers, operators, comparators, and T_PIPE. Append it here if
		// the compiled expression is an unterminated SimpleChain call.
		if (
			\str_ends_with($count_expr, ')')
			&& !\str_ends_with($count_expr, '->val()')
			&& \str_contains($count_expr, '->chain(')
		) {
			$count_expr .= '->val()';
		}

		$loop_idx_name = null;
		$current       = $this->lexer->current();

		if ($current && Token::T_NAME === $current->getType() && 'as' === $current->getValue()) {
			$this->lexer->move();
			$idx_token     = $this->lexer->nextIs(Token::T_NAME, null, true);
			$loop_idx_name = $idx_token->getValue();
			$this->lexer->nextIs(Token::T_TAG_CLOSE, null, true);
		}

		$this->count_var = $this->parser->createVar();
		$this->index_var = $this->parser->createVar();

		$this->parser->newDataContext();

		$code = Str::interpolate(
			'{count_var} = (int)({count_expr});' . "\n"
				. 'for ({index_var} = 0; {index_var} < {count_var}; {index_var}++) {' . "\n"
				. "{ctx}->set('is_first',{index_var} === 0)->set('is_last',{index_var} === {count_var} - 1);\n",
			[
				'count_var'  => $this->count_var,
				'count_expr' => $count_expr,
				'index_var'  => $this->index_var,
				'ctx'        => Blate::DATA_CONTEXT_VAR,
			]
		);

		if (null !== $loop_idx_name) {
			$code .= Str::interpolate(
				"{ctx}->set('{loop_idx_name}', {index_var});\n",
				[
					'ctx'           => Blate::DATA_CONTEXT_VAR,
					'loop_idx_name' => $loop_idx_name,
					'index_var'     => $this->index_var,
				]
			);
		}

		$this->parser->writeCode($code);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function onClose(): void
	{
		$this->parser->writeCode('}');
		$this->parser->popDataContext();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function requireClose(): bool
	{
		return true;
	}
}
