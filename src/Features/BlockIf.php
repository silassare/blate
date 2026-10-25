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
use Override;

/**
 * Class BlockIf.
 *
 * Implements the {@if expr}...{:elseif expr}...{:else}...{/if} block.
 *
 * Compile-time output:
 *   {@if expr}        -> if (expr) {
 *   {:elseif expr}    -> } elseif (expr) {
 *   {:else}           -> } else {
 *   {/if}             -> }
 *
 * At most one {:else} is allowed; a second one throws a parser error.
 */
class BlockIf extends Block
{
	public const string NAME = 'if';

	public const string BREAKPOINT_ELSE = 'else';

	public const string BREAKPOINT_ELSE_IF = 'elseif';

	private bool $else_found = false;

	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * @throws BlateParserException
	 */
	#[Override]
	public function onOpen(): void
	{
		$this->lexer->nextIs(Token::T_WHITESPACE);

		$expression = (new Expression())->get($this->lexer);

		$code = 'if (' . $expression . '){';

		$this->parser->writeCode($code);
	}

	#[Override]
	public function onBreakPoint(TokenInterface $token): void
	{
		if ($this->else_found) {
			throw BlateParserException::withToken(Message::BLOCK_BREAKPOINT_UNEXPECTED, $token);
		}

		$name = $token->getValue();

		if (self::BREAKPOINT_ELSE === $name) {
			$this->else_found = true;

			$code = '} else {';

			$this->parser->writeCode($code);
			$this->parser->tagClose();
		} elseif (self::BREAKPOINT_ELSE_IF === $name) {
			$this->lexer->nextIs(Token::T_WHITESPACE);

			$expression = (new Expression())->get($this->lexer);

			$code = '} elseif (' . $expression . '){';

			$this->parser->writeCode($code);
		} else {
			throw BlateParserException::withToken(Message::BLOCK_BREAKPOINT_UNEXPECTED, $token);
		}
	}

	#[Override]
	public function onClose(): void
	{
		$this->parser->writeCode('}');
	}

	#[Override]
	public function requireClose(): bool
	{
		return true;
	}
}
