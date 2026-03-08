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
 * Class BlockComment.
 *
 * Implements the {# comment #} template comment block.
 *
 * Everything between the { hash and the closing hash } is consumed and
 * discarded at compile time -- no PHP output is generated.  Useful for
 * documenting templates without leaving traces in the rendered HTML.
 */
class BlockComment extends Block
{
	public const NAME = 'comment';

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
		$reader = $this->lexer->getReader();

		$reader->whileTrue(static function () use ($reader) {
			return !$reader->isNextChunk(Blate::BLOCK_COMMENT . Blate::TAG_CLOSER);
		});

		$this->lexer->nextIs(null, Blate::BLOCK_COMMENT);
		$this->lexer->nextIs(Token::T_TAG_CLOSE);
	}

	/**
	 * {@inheritDoc}
	 */
	public function requireClose(): bool
	{
		return false;
	}
}
