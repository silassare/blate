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
use Blate\Interfaces\BlockInterface;
use Blate\Interfaces\LexerInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use Blate\Parser;
use Override;

/**
 * Class Block.
 *
 * Abstract base for all built-in block implementations.
 *
 * Provides default (no-op or pass-through) implementations of the BlockInterface
 * lifecycle hooks so that concrete blocks only need to override what they use.
 *
 * Default behavior:
 *   onChildContentFound() -- writes raw content to parser output
 *   onChildExpressionFound() -- compiles and writes expression to parser output
 *   onChildBlockFound() -- no-op (override to validate or reject child blocks)
 *   onClose()             -- no-op
 *   onBreakPoint()        -- throws BlateParserException (unexpected breakpoint)
 */
abstract class Block implements BlockInterface
{
	protected LexerInterface $lexer;

	/**
	 * @var BlockInterface[]|TokenInterface[]
	 */
	protected array $children = [];

	/**
	 * {@inheritDoc}
	 */
	public function __construct(protected Parser $parser, protected TokenInterface $token)
	{
		$this->lexer = $this->parser->getLexer();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getToken(): TokenInterface
	{
		return $this->token;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function onChildBlockFound(BlockInterface $block): void {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function onChildContentFound(TokenInterface $token): void
	{
		$this->parser->write($token->getValue());
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function onChildExpressionFound(TokenInterface $token, bool $escape): void
	{
		$this->parser->writeExpression((new Expression())->get($this->lexer), $escape);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function onClose(): void {}

	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException
	 */
	#[Override]
	public function onBreakPoint(TokenInterface $token): void
	{
		throw BlateParserException::withToken(Message::BLOCK_BREAKPOINT_UNEXPECTED, $token);
	}
}
