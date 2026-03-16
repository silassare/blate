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
use Blate\Token;
use Override;

/**
 * Class BlockScoped.
 *
 * Implements the {@scoped}...{/scoped} isolated-scope block.
 *
 * Pushes a new DataContext scope layer on open and pops it on close.
 * Variables set inside the scoped block (e.g., via {@set}) do not leak
 * outside it.
 */
class BlockScoped extends Block
{
	public const NAME = 'scoped';

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
		$this->parser->newDataContext();
		$this->lexer->nextIs(Token::T_TAG_CLOSE, null, true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function onClose(): void
	{
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
