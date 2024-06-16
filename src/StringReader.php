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

use Blate\Exceptions\BlateParserException;

/**
 * Class StringReader.
 */
class StringReader
{
	protected int $length;

	protected int $cursor = 0;

	protected int $line_number = 1;

	protected int $line_index = 1;

	protected array $states = [];

	protected array $digits;

	/**
	 * @param string $input
	 */
	public function __construct(protected string $input)
	{
		$this->length = \strlen($this->input);
	}

	public function getCursor(): int
	{
		return $this->cursor;
	}

	/**
	 * Returns the line index.
	 */
	public function getLineIndex(): int
	{
		return $this->line_index;
	}

	/**
	 * Returns the line number.
	 */
	public function getLineNumber(): int
	{
		return $this->line_number;
	}

	/**
	 * Returns the input length.
	 */
	public function getLength(): int
	{
		return $this->length;
	}

	public function getInput(): string
	{
		return $this->input;
	}

	/**
	 * Move the cursor.
	 */
	public function move(): bool
	{
		if ($this->cursor < $this->length) {
			$this->checkNewLine();
			++$this->line_index;
			++$this->cursor;

			return true;
		}

		return false;
	}

	/**
	 * Returns current character.
	 *
	 * @return null|string the current character or null when we reach end of file
	 */
	public function current(): ?string
	{
		if ($this->cursor < $this->length) {
			return $this->input[$this->cursor];
		}

		return null;
	}

	/**
	 * Returns next character.
	 *
	 * @return null|string the next character or null when we reach end of file
	 */
	public function next(): ?string
	{
		if ($this->cursor + 1 < $this->length) {
			return $this->input[$this->cursor + 1];
		}

		return null;
	}

	/**
	 * Returns previous character.
	 *
	 * @return null|string the prev character or null when we are at the beginning of the file
	 */
	public function previous(): ?string
	{
		if ($this->cursor > 0 && $this->cursor < $this->length) {
			return $this->input[$this->cursor - 1];
		}

		return null;
	}

	/**
	 * Save the current state.
	 */
	public function saveCurrentState(): self
	{
		$state['cursor']      = $this->cursor;
		$state['line_number'] = $this->line_number;
		$state['line_index']  = $this->line_index;

		$this->states[] = $state;

		return $this;
	}

	/**
	 * Restore to the previous saved state.
	 */
	public function restorePreviousState(): self
	{
		$state = \array_pop($this->states);

		if (null === $state) {
			throw new BlateParserException(Message::NO_SAVED_STATE_CANT_RESTORE);
		}

		$this->cursor      = $state['cursor'];
		$this->line_number = $state['line_number'];
		$this->line_index  = $state['line_index'];

		return $this;
	}

	public function whileTrue(callable $fn): StringChunk
	{
		$result = new StringChunk($this);
		$i      = 0;
		$acc    = '';

		while (($c = $this->current()) !== null) {
			$tmp_acc = $acc . $c;

			if (!$fn($c, $i, $tmp_acc)) {
				break;
			}

			$this->move();

			$acc .= $c;
			++$i;
		}

		return $result->setValue($acc);
	}

	/**
	 * @return null|StringChunk
	 */
	public function getChunkIfNextIs(string $chunk, ?string $escape_char = null): ?StringChunk
	{
		if (null !== $escape_char && $escape_char === $this->previous()) {
			return null;
		}

		$c = \strlen($chunk);

		if (\substr($this->input, $this->cursor, $c) === $chunk) {
			$result = new StringChunk($this);
			while ($c) {
				$this->move();
				--$c;
			}

			return $result->setValue($chunk);
		}

		return null;
	}

	public function isNextChunk(string $chunk, ?string $escape_char = null): bool
	{
		if (null !== $escape_char && $escape_char === $this->previous()) {
			return false;
		}

		$c = \strlen($chunk);

		return \substr($this->input, $this->cursor, $c) === $chunk;
	}

	public function moveUntilChar(string $char, ?string $escape_char = null): StringChunk
	{
		$acc    = '';
		$result = new StringChunk($this);
		$result->setExpected($char);

		while (($c = $this->current()) !== null) {
			if ($c === $char) {
				if (null === $escape_char || $escape_char !== $this->previous()) {
					break;
				}
			}

			$this->move();

			$acc .= $c;
		}

		return $result->setValue($acc);
	}

	/**
	 * New line counter.
	 */
	private function checkNewLine(): void
	{
		$c = $this->input[$this->cursor];

		switch (\PHP_EOL) {
			case "\n":
			case "\r":
				if (\PHP_EOL === $c) {
					++$this->line_number;
					$this->line_index = 0;
				}

				break;

			case "\r\n":
				if ("\n" === $c && "\r" === $this->previous()) {
					++$this->line_number;
					$this->line_index = 0;
				}

				break;
		}
	}
}
