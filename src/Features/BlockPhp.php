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
 * Class BlockPhp.
 *
 * Implements the {~ inline PHP ~} block.
 *
 * Everything between the opening tilde and the closing tilde-brace is emitted
 * verbatim into the compiled template's PHP code.  Useful for quick one-liners
 * that do not merit a custom block implementation.
 *
 * Note: the PHP snippet is trusted developer code, not user input.
 */
class BlockPhp extends Block
{
	public const NAME = 'php';

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
		$reader = $this->lexer->getReader();

		$content = $reader->whileTrue(static function () use ($reader) {
			return !$reader->isNextChunk(Blate::BLOCK_PHP . Blate::TAG_CLOSER);
		});

		$this->parser->writeCode($content->getValue());

		$this->lexer->nextIs(null, Blate::BLOCK_PHP);
		$this->lexer->nextIs(Token::T_TAG_CLOSE);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function requireClose(): bool
	{
		return false;
	}
}
