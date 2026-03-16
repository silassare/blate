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
use Override;

/**
 * Class BlockRaw.
 *
 * Implements the {@raw}...{/raw} literal-output block.
 *
 * Everything between the opening {@raw} tag and the matching {/raw} close
 * is written verbatim to the output without any template processing.  Useful
 * for including snippet that contain brace characters that would otherwise
 * be interpreted as template tags.
 *
 * The close sentinel is scanned directly on the StringReader rather than
 * through the token tree, so nested brace pairs are not interpreted.
 */
class BlockRaw extends Block
{
	public const NAME = 'raw';

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
	#[Override]
	public function requireClose(): bool
	{
		return true;
	}
}
