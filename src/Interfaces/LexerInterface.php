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

use Blate\StringReader;

/**
 * Interface LexerInterface.
 */
interface LexerInterface
{
	/**
	 * Returns the string reader.
	 */
	public function getReader(): StringReader;

	/**
	 * Returns the cursor.
	 */
	public function getCursor(): int;

	/**
	 * Move the cursor.
	 *
	 * @return ?TokenInterface
	 */
	public function move(): ?TokenInterface;

	/**
	 * Returns current token.
	 *
	 * @return null|TokenInterface the current token or null when we reach end of file
	 */
	public function current(): ?TokenInterface;

	/**
	 * Tokenize all.
	 *
	 * @return TokenInterface[]
	 */
	public function tokenize(): array;

	/**
	 * Get token tree.
	 *
	 * @return TokenInterface[]
	 */
	public function getTokensTree(): array;

	/**
	 * Asserts and returns the next token if its type is of the given token type and value.
	 *
	 * @param ?int    $type              the expected token type
	 * @param ?string $value             the expected token value
	 * @param bool    $ignore_whitespace Should we ignore whitespace?
	 */
	public function nextIs(?int $type, ?string $value = null, bool $ignore_whitespace = false): TokenInterface;

	/**
	 * Asserts and returns the next token if its type is part of the given token type list.
	 *
	 * @param int[] $types             the expected token type list
	 * @param bool  $ignore_whitespace Should we ignore whitespace?
	 */
	public function nextIsOneOf(array $types, bool $ignore_whitespace = false): TokenInterface;

	/**
	 * Look forward and return the next token.
	 *
	 * @return null|TokenInterface the next token or null when we reach end of file
	 */
	public function lookForward(bool $ignore_whitespace = false): ?TokenInterface;

	/**
	 * Look backward and return the prev token.
	 *
	 * @return null|TokenInterface the prev token or null when we are at the beginning of the file
	 */
	public function lookBackward(bool $ignore_whitespace = false): ?TokenInterface;

	/**
	 * Save the current state.
	 *
	 * @return $this
	 */
	public function saveCurrentState(): self;

	/**
	 * Restore to the previous saved state.
	 *
	 * @return $this
	 */
	public function restorePreviousState(): self;
}
