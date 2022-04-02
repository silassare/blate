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
 * Interface ChunkInterface.
 */
interface ChunkInterface extends ArrayCapableInterface, \Stringable
{
	/**
	 * Gets expected value.
	 */
	public function getExpected(): mixed;

	/**
	 * Sets expected value.
	 *
	 * @param string $expected
	 *
	 * @return $this
	 */
	public function setExpected(mixed $expected): static;

	/**
	 * Gets unexpected value.
	 */
	public function getUnexpected(): mixed;

	/**
	 * Sets unexpected value.
	 */
	public function setUnexpected(mixed $unexpected): static;

	/**
	 * Checks if we reach the end of file.
	 */
	public function eof(): bool;

	/**
	 * Sets chunk value.
	 *
	 * @return $this
	 */
	public function setValue(mixed $value): static;

	/**
	 * Gets chunk value.
	 */
	public function getValue(): mixed;

	/**
	 * Gets token location.
	 */
	public function getLocation(): array;

	/**
	 * Gets token location as string.
	 */
	public function getLocationString(bool $full = false): string;
}
