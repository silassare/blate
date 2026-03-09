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

use Blate\Exceptions\BlateParserException;
use Blate\Expressions\Expression;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use Blate\Token;

/**
 * Class BlockSwitch.
 *
 * Implements the {@switch expr}{:case val}...{:default}...{/switch} block.
 *
 * Syntax:
 *   {@switch expr}
 *     {:case val1}...
 *     {:case val2}...
 *     {:default}...
 *   {/switch}
 *
 * The switch expression is evaluated once into a PHP variable.
 * Each {:case val} branch uses strict equality (===).
 * {:default} is optional and must appear after all {:case} branches.
 * Content between {@switch} and the first {:case}/{:default} is ignored.
 *
 * Compile-time output emits a chain of if/elseif/else statements.
 */
class BlockSwitch extends Block
{
	public const NAME = 'switch';

	public const BREAKPOINT_CASE = 'case';

	public const BREAKPOINT_DEFAULT = 'default';

	private string $switch_var = '';

	private bool $has_branch = false;

	private bool $default_found = false;

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException
	 */
	public function onOpen(): void
	{
		$this->lexer->nextIs(Token::T_WHITESPACE);
		$expression        = (new Expression())->get($this->lexer);
		$this->switch_var  = $this->parser->createVar();
		$this->parser->writeCode($this->switch_var . ' = ' . $expression . ';');
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException
	 */
	public function onBreakPoint(TokenInterface $token): void
	{
		if ($this->default_found) {
			throw BlateParserException::withToken(Message::BLOCK_BREAKPOINT_UNEXPECTED, $token);
		}

		$name = $token->getValue();

		if (self::BREAKPOINT_CASE === $name) {
			$this->lexer->nextIs(Token::T_WHITESPACE);
			$expr = (new Expression())->get($this->lexer);

			if (!$this->has_branch) {
				$this->parser->writeCode('if (' . $this->switch_var . ' === ' . $expr . ') {');
				$this->has_branch = true;
			} else {
				$this->parser->writeCode('} elseif (' . $this->switch_var . ' === ' . $expr . ') {');
			}
		} elseif (self::BREAKPOINT_DEFAULT === $name) {
			$this->default_found = true;

			if (!$this->has_branch) {
				$this->parser->writeCode('{');
			} else {
				$this->parser->writeCode('} else {');
			}

			$this->parser->tagClose();
		} else {
			throw BlateParserException::withToken(Message::BLOCK_BREAKPOINT_UNEXPECTED, $token);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function onClose(): void
	{
		if ($this->has_branch || $this->default_found) {
			$this->parser->writeCode('}');
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function requireClose(): bool
	{
		return true;
	}
}
