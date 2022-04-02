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

use PHPUtils\Interfaces\ArrayCapableInterface;

/**
 * Interface TokenInterface.
 */
interface TokenInterface extends ArrayCapableInterface, \Stringable
{
	/**
	 * TokenInterface constructor.
	 */
	public function __construct(ChunkInterface $chunk, int $type, ?self $parent = null);

	/**
	 * Returns token unique reference.
	 */
	public function getRef(): string;

	/**
	 * Returns token type.
	 */
	public function getType(): int;

	/**
	 * Returns token chunk.
	 */
	public function getChunk(): ChunkInterface;

	/**
	 * Returns token value.
	 */
	public function getValue(): string;

	/**
	 * Checks if the current token is a group opener.
	 */
	public function isGroupOpener(): bool;

	/**
	 * Check if the current token is a group closer.
	 */
	public function isGroupCloser(): bool;

	/**
	 * Check if the current token is group closer of the given token.
	 */
	public function isGroupCloserOf(self $opener): bool;

	/**
	 * Check if the current token is an operator.
	 */
	public function isOperator(): bool;

	/**
	 * Check if the current token is a comparator.
	 */
	public function isComparator(): bool;

	/**
	 * Check if the current token is a logical condition.
	 */
	public function isLogicalCondition(): bool;

	/**
	 * Gets token children.
	 *
	 * @return TokenInterface[]
	 */
	public function getChildren(): array;

	/**
	 * Adds child to token.
	 *
	 * @return $this
	 */
	public function addChild(self $token): static;

	/**
	 * Gets token parent.
	 */
	public function getParent(): ?self;

	/**
	 * Sets token parent.
	 *
	 * @param null|\Blate\Interfaces\TokenInterface $parent
	 *
	 * @return $this
	 */
	public function setParent(?self $parent): static;

	/**
	 * Sets token attribute.
	 *
	 * @return $this
	 */
	public function setAttribute(string $name, mixed $value): static;

	/**
	 * Gets token attribute.
	 */
	public function getAttribute(string $name): mixed;
}
