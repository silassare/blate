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
	 * @return $this
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

	public function getCodes(): array
	{
		return $this->codes;
	}

	/**
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

	public function getActive(): ?TokenInterface
	{
		return $this->active;
	}
}
