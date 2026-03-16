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

use Blate\Interfaces\ChunkInterface;
use Override;
use PHPUtils\Traits\ArrayCapableTrait;
use RuntimeException;

/**
 * Class StringChunk.
 *
 * A single contiguous slice of the template source, produced by StringReader.
 *
 * Captures the start/end cursor positions and line/index information at the
 * moment it is created (constructor) and locked (setValue()).  These are used
 * for rich error messages that point users to the exact location in the source.
 *
 * Once setValue() is called the chunk is locked and cannot be modified; any
 * further setValue() call throws a RuntimeException.
 */
class StringChunk implements ChunkInterface
{
	use ArrayCapableTrait;

	private bool $locked = false;

	private int $start_cursor;

	private int $end_cursor = 0;

	private int $start_line_number;

	private int $start_line_index;

	private int $end_line_number = 1;

	private int $end_line_index = 0;

	private bool $eof = false;

	private mixed $value      = '';
	private mixed $expected   = '';
	private mixed $unexpected = '';

	/**
	 * StringChunk constructor.
	 *
	 * @param StringReader $reader
	 */
	public function __construct(protected StringReader $reader)
	{
		$this->start_cursor      = $reader->getCursor();
		$this->start_line_number = $reader->getLineNumber();
		$this->start_line_index  = $reader->getLineIndex();
	}

	/**
	 * {@inheritDoc}
	 */
	public function __toString(): string
	{
		return $this->getLocationString();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setExpected(mixed $expected): static
	{
		$this->expected = $expected;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getExpected(): mixed
	{
		return $this->expected;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setUnexpected(mixed $unexpected): static
	{
		$this->unexpected = $unexpected;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getUnexpected(): mixed
	{
		if ($this->unexpected) {
			return $this->unexpected;
		}

		return $this->getValue();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getValue(): string
	{
		return !empty($this->value) ? $this->value : \substr($this->reader->getInput(), $this->start_cursor, $this->end_cursor - $this->start_cursor);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setValue(mixed $value): static
	{
		if (!$this->locked) {
			$this->locked          = true;
			$this->end_line_number = $this->reader->getLineNumber();
			$this->end_line_index  = $this->reader->getLineIndex();
			$this->end_cursor      = $this->reader->getCursor();
			$this->eof             = !($this->end_cursor < $this->reader->getLength());
			$this->value           = $value;
		} else {
			throw new RuntimeException(Message::CANT_EDIT_CHUNK);
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function eof(): bool
	{
		return $this->eof;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getLocationString(bool $full = false): string
	{
		if ($full) {
			return $this->getTemplateLines();
		}

		return \sprintf('Line %s index %s', $this->start_line_number, $this->start_line_index);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getLocation(): array
	{
		return [
			'line'              => $this->start_line_number,
			'index'             => $this->start_line_index,
			'start_line_number' => $this->start_line_number,
			'start_line_index'  => $this->start_line_index,
			'start_cursor'      => $this->start_cursor,
			'end_line_number'   => $this->end_line_number,
			'end_line_index'    => $this->end_line_index,
			'end_cursor'        => $this->end_cursor,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'found'      => $this->getValue(),
			'expected'   => $this->expected,
			'unexpected' => $this->getUnexpected(),
			'location'   => $this->getLocation(),
		];
	}

	/**
	 * Gets full chunk location.
	 */
	protected function getTemplateLines(int $lines_before = 1, int $lines_after = 1): string
	{
		$target_line  = $this->start_line_number;
		$target_index = $this->start_line_index;
		$template     = $this->reader->getInput();
		$lines        = \explode(\PHP_EOL, $template);

		$str = '';

		$min = $target_line - $lines_before;
		$max = $target_line + $lines_after;

		for ($i = $min; $i < $max + 1; ++$i) {
			$index = $i - 1;

			if (isset($lines[$index])) {
				$line = $lines[$index];
				$str .= $i . '|' . $line . \PHP_EOL;

				if ($i === $target_line) {
					$spaces = \str_repeat(' ', \strlen($i . '|'));

					for ($j = 0; $j < $target_index - 1; ++$j) {
						if ("\t" === $line[$j]) {
							$spaces .= "\t";
						} else {
							$spaces .= ' ';
						}
					}

					$str .= $spaces . '^' . \PHP_EOL;
				}
			}
		}

		return $str;
	}
}
