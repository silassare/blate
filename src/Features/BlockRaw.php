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
use Blate\Token;

/**
 * Class BlockRaw.
 */
class BlockRaw extends Block
{
	public const NAME = 'raw';

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
		$this->lexer->nextIs(Token::T_TAG_CLOSE, null, true);
		$reader = $this->lexer->getReader();

		$chunk = $reader->whileTrue(static function () use ($reader) {
			return !$reader->isNextChunk(Blate::TAG_OPENER . Blate::BLOCK_CLOSE . self::NAME);
		});

		$this->parser->write($chunk->getValue());
	}

	/**
	 * {@inheritDoc}
	 */
	public function requireClose(): bool
	{
		return true;
	}
}
