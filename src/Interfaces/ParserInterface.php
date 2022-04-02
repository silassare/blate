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

/**
 * Interface ParserInterface.
 */
interface ParserInterface
{
	/**
	 * Returns lexer.
	 */
	public function getLexer(): LexerInterface;

	/**
	 * Writes to output.
	 *
	 * @return $this
	 */
	public function write(TokenInterface|string $str): static;

	/**
	 * Gets output string.
	 */
	public function getOutput(): string;

	/**
	 * Parse.
	 *
	 * @return $this
	 */
	public function parse(?callable $while_true = null, ?array $options = []): static;
}
