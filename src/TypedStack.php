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

namespace Blate;

use Blate\Interfaces\TokenInterface;
use LogicException;

/**
 * Class TypedStack.
 *
 * Token-keyed code buffer stack used by ParserOutputTrait.
 *
 * Records are stored per-token-ref so that the Parser can later
 * retrieve the PHP code accumulated for each slot token.
 *
 * Usage:
 *   start($token)  -- open a new buffer for $token
 *   write($code)   -- append code to the active buffer
 *   end()          -- close the buffer and add $token to $all
 *   end(true)      -- close and discard the buffer (used by BlockSlot
 *                     when emitting inline closures)
 *   getCode($token) -- retrieve the accumulated code for a finished buffer
 *   getAll()        -- all tokens whose buffers were committed
 */
class TypedStack
{
	/**
	 * @var TokenInterface[]
	 */
	private array $stack  = [];

	/**
	 * @var TokenInterface[]
	 */
	private array $all              = [];
	private ?TokenInterface $active = null;

	private array $codes = [];

	/**
	 * Appends code to the currently active token's buffer.
	 *
	 * @param string $str the PHP code to append
	 *
	 * @return $this
	 *
	 * @throws LogicException when no token is currently active
	 */
	public function write(string $str): static
	{
		if ($this->active) {
			$this->codes[$this->active->getRef()] .= $str;
		} else {
			throw new LogicException('No active token.');
		}

		return $this;
	}

	/**
	 * @return TokenInterface[]
	 */
	public function getAll(): array
	{
		return $this->all;
	}

	/**
	 * @return null|mixed
	 */
	public function getCode(TokenInterface $token): mixed
	{
		return $this->codes[$token->getRef()] ?? null;
	}

	/**
	 * Returns all accumulated code buffers indexed by token ref.
	 *
	 * @return array<string, string>
	 */
	public function getCodes(): array
	{
		return $this->codes;
	}

	/**
	 * Opens a new code buffer associated with the given token and pushes it
	 * onto the active stack.
	 *
	 * @param TokenInterface $token the token to associate with the new buffer
	 *
	 * @return $this
	 */
	public function start(TokenInterface $token): static
	{
		$this->stack[]                      = $token;
		$this->active                       = $token;
		$this->codes[$token->getRef()]      = '';

		return $this;
	}

	/**
	 * Closes the current code buffer.
	 *
	 * When $discard is false (default), the finished token is added to the
	 * $all list so its code can be retrieved later via getCode().
	 * When $discard is true, the buffer is thrown away without recording it.
	 *
	 * @param bool $discard when true the accumulated code is discarded
	 *
	 * @return $this
	 */
	public function end(bool $discard = false): static
	{
		if ($this->active) {
			if ($discard) {
				unset($this->codes[$this->active->getRef()]);
			} else {
				$this->all[] = $this->active;
			}
		}

		\array_pop($this->stack);
		$this->active = \array_pop($this->stack);

		return $this;
	}

	/**
	 * Returns the currently active token, or null when the stack is empty.
	 */
	public function getActive(): ?TokenInterface
	{
		return $this->active;
	}
}
