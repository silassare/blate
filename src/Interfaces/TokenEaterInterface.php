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

namespace Blate\Interfaces;

use Blate\StringChunk;

/**
 * Interface TokenEaterInterface.
 *
 * Implemented by parsers that consume a sequence of raw characters from
 * a LexerInterface and return the matched portion as a StringChunk.
 */
interface TokenEaterInterface
{
	/**
	 * Consumes characters from $lexer and returns the matched StringChunk.
	 *
	 * @param LexerInterface $lexer the active lexer positioned at the start of the sequence
	 *
	 * @return StringChunk the consumed text
	 */
	public function get(LexerInterface $lexer): StringChunk;
}
