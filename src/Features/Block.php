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

/**
 * Class Block.
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
	public function getToken(): TokenInterface
	{
		return $this->token;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onChildBlockFound(BlockInterface $block): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function onChildContentFound(TokenInterface $token): void
	{
		$this->parser->write($token->getValue());
	}

	/**
	 * {@inheritDoc}
	 */
	public function onChildExpressionFound(TokenInterface $token): void
	{
		$this->parser->writeExpression((new Expression())->get($this->lexer));
	}

	/**
	 * {@inheritDoc}
	 */
	public function onClose(): void
	{
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Blate\Exceptions\BlateParserException
	 */
	public function onBreakPoint(TokenInterface $token): void
	{
		throw BlateParserException::withToken(Message::BLOCK_BREAKPOINT_UNEXPECTED, $token);
	}
}
