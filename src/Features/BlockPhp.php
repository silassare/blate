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
 * Class BlockPhp.
 */
class BlockPhp extends Block
{
	public const NAME = 'php';

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
	public function requireClose(): bool
	{
		return false;
	}
}
