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

namespace Blate;

use Blate\Exceptions\BlateParserException;
use Blate\Expressions\Expression;
use Blate\Features\BlockComment;
use Blate\Features\BlockPhp;
use Blate\Interfaces\BlockInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Traits\ParserOutputTrait;
use PHPUtils\Store\Store;

/**
 * Class Parser.
 *
 * Drives the main template compile pass.  It walks the token stream produced
 * by the Lexer and dispatches to block/expression handlers, accumulating the
 * PHP code for the compiled template class body via ParserOutputTrait.
 */
class Parser
{
	use ParserOutputTrait;

	/**
	 * @var BlockInterface[]
	 */
	protected array $block_to_close_stack = [];

	protected Lexer $lexer;

	/**
	 * @param Blate $blate
	 */
	public function __construct(protected Blate $blate)
	{
		$this->lexer = new Lexer($this->blate->getInput());

		$this->ps_store   = new Store([]);
		$this->ts_slots   = new TypedStack();
		$this->ts_extends = new TypedStack();
	}

	/**
	 * @return Blate
	 */
	public function getBlate(): Blate
	{
		return $this->blate;
	}

	/**
	 * @return Lexer
	 */
	public function getLexer(): Lexer
	{
		return $this->lexer;
	}

	/**
	 * Parses the entire token stream and accumulates PHP code.
	 *
	 * Called once per template compile.  Iterates through every token returned
	 * by the lexer and routes:  raw-data -> write(), {@ ... } -> block open/close/
	 * break, {= ... } -> unescaped expression, {expr} -> escaped expression.
	 *
	 * @return $this
	 *
	 * @throws BlateParserException on unexpected tokens or unclosed blocks
	 */
	public function parse(): static
	{
		while ($this->lexer->move() && ($token = $this->lexer->current())) {
			$type = $token->getType();

			if (Token::T_RAW_DATA === $type) {
				$this->rawData($token);
			} elseif (Token::T_TAG_OPEN === $type) {
				$next = $this->lexer->lookForward();

				if (!$next) {
					throw new BlateParserException(Message::UNEXPECTED_EOF);
				}

				$next_value = $next->getValue();
				$this->lexer->move();

				if (Blate::BLOCK_OPEN === $next_value) {
					$this->blockOpen();
				} elseif (Blate::BLOCK_BREAKPOINT === $next_value) {
					$this->blockBreakpoint();
				} elseif (Blate::BLOCK_CLOSE === $next_value) {
					$this->blockClose();
				} elseif (Blate::BLOCK_COMMENT === $next_value) {
					$this->comment();
				} elseif (Blate::BLOCK_PHP === $next_value) {
					$this->php();
				} else {
					$this->expression($token);
				}
			} else {
				throw BlateParserException::withToken(Message::UNEXPECTED, $token);
			}
		}

		if ($unclosed_block = $this->lastUnclosedBlock()) {
			throw BlateParserException::withToken(Message::BLOCK_NEVER_CLOSED, $unclosed_block->getToken());
		}

		return $this;
	}

	/**
	 * Asserts that the next (non-whitespace) token is a TAG_CLOSE (the
	 * template closing brace }) and advances past it.  Used by block handlers
	 * that have already consumed all their content.
	 */
	public function tagClose(): void
	{
		$this->lexer->nextIs(Token::T_TAG_CLOSE, Blate::TAG_CLOSER, true);
	}

	private function lastUnclosedBlock(): ?BlockInterface
	{
		$block = \end($this->block_to_close_stack);

		return $block ?: null;
	}

	private function rawData(TokenInterface $token): void
	{
		$block = $this->lastUnclosedBlock();

		if ($block) {
			$block->onChildContentFound($token);
		} else {
			$this->write($token->getValue());
		}
	}

	private function comment(): void
	{
		(new BlockComment($this, $this->lexer->current()))->onOpen();
	}

	private function php(): void
	{
		(new BlockPhp($this, $this->lexer->current()))->onOpen();
	}

	private function expression(TokenInterface $token): void
	{
		$block     = $this->lastUnclosedBlock();
		$current   = $this->lexer->current();
		$escape    = true;

		if (Blate::BLOCK_SAFE_ECHO === $current?->getValue()) {
			$escape = false;
			$this->lexer->move();
		}

		if ($block) {
			$block->onChildExpressionFound($token, $escape);
		} else {
			$this->writeExpression((new Expression())->get($this->lexer), $escape);
		}
	}

	private function blockOpen(): void
	{
		$next  = $this->lexer->nextIs(Token::T_NAME);
		$block = Blate::getBlockInstance($this, $next);

		if (null === $block) {
			throw BlateParserException::withToken(Message::BLOCK_UNDEFINED, $next);
		}

		$this->lastUnclosedBlock()
			?->onChildBlockFound($block);

		if ($block->requireClose()) {
			$this->block_to_close_stack[] = $block;
		}

		$block->onOpen();
	}

	private function blockBreakpoint(): void
	{
		$next         = $this->lexer->nextIs(Token::T_NAME);
		$parent_block = $this->lastUnclosedBlock();

		if (!$parent_block) {
			throw BlateParserException::withToken(Message::BLOCK_BREAKPOINT_UNEXPECTED, $next);
		}

		$parent_block->onBreakPoint($next);
	}

	private function blockClose(): void
	{
		$next  = $this->lexer->nextIs(Token::T_NAME);
		$name  = $next->getValue();
		$block = \array_pop($this->block_to_close_stack);

		if (!$block) {
			throw BlateParserException::withToken(Message::BLOCK_CLOSER_UNEXPECTED, $next);
		}

		$expected = $block->getName();

		if ($expected !== $name) {
			$next->getChunk()
				->setExpected($expected);

			throw BlateParserException::withToken(
				Message::BLOCK_CLOSER_UNEXPECTED_WHILE_EXPECTING,
				$next
			);
		}

		$block->onClose();
		$this->tagClose();
	}
}
