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

/**
 * Class BlockScoped.
 */
class BlockScoped extends Block
{
	public const NAME = 'scoped';

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
		$this->parser->newDataContext();
		$this->lexer->nextIs(Token::T_TAG_CLOSE, null, true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function onClose(): void
	{
		$this->parser->popDataContext();
	}

	/**
	 * {@inheritDoc}
	 */
	public function requireClose(): bool
	{
		return true;
	}
}
