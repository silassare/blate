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

namespace Blate\Exceptions\Traits;

use Blate\Interfaces\ChunkInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use const JSON_PRETTY_PRINT;
use const PHP_EOL;
use PHPUtils\Traits\RichExceptionTrait;

/**
 * Class BlateExceptionTrait.
 */
trait BlateExceptionTrait
{
	use RichExceptionTrait;

	private ?ChunkInterface $chunk = null;

	/**
	 * {@inheritDoc}
	 */
	public function __toString()
	{
		return $this->describe(true, true);
	}

	/**
	 * Gets instance with chunk exception with.
	 */
	public static function withChunk(string $message, ChunkInterface $chunk, array $data = []): static
	{
		$e        = new static(Message::msg($message, $chunk), $data);
		$e->chunk = $chunk;

		return $e;
	}

	/**
	 * Gets instance with chunk exception with.
	 */
	public static function withToken(string $message, TokenInterface $token, array $data = []): static
	{
		$e        = new static(Message::msg($message, $chunk = $token->getChunk()), $data);
		$e->chunk = $chunk;

		return $e;
	}

	/**
	 * Pretty error string.
	 */
	public function describe(bool $include_debug_data = false, bool $include_stack_trace = false): string
	{
		$str = $this->getMessage() . PHP_EOL;

		if ($this->chunk) {
			$str .= PHP_EOL . $this->chunk->getLocationString(true);
		}

		if ($include_debug_data) {
			$str .= PHP_EOL . 'Debug data: ' . \json_encode($this->getData(), JSON_PRETTY_PRINT);
		}

		if ($include_stack_trace) {
			$str .= PHP_EOL . 'File: ' . $this->getFile();
			$str .= PHP_EOL . 'Line: ' . $this->getLine();
			$str .= PHP_EOL . $this->getTraceAsString();
		}

		return $str;
	}
}
