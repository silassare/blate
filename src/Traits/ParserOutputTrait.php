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

namespace Blate\Traits;

use Blate\Blate;
use Blate\Helpers\Helpers;
use Blate\TypedStack;
use PHPUtils\Store\Store;

/**
 * Trait ParserOutputTrait.
 *
 * PHP code-generation helpers mixed into Parser and ExpressionParser.
 *
 * The trait maintains two separate output channels:
 *   1. $main_code   -- the body of the compiled template's build() method.
 *   2. $ts_slots    -- one buffer per named slot (each becomes its own method).
 *
 * All write*() methods automatically route to the active slot buffer when a
 * slot is open, falling back to $main_code otherwise.
 */
trait ParserOutputTrait
{
	protected string $main_code = '';
	protected TypedStack $ts_slots;
	protected TypedStack $ts_extends;
	protected Store $ps_store;

	/**
	 * Returns the typed stack used for slot code buffers.
	 */
	public function slots(): TypedStack
	{
		return $this->ts_slots;
	}

	/**
	 * Returns the typed stack used for extends code buffers.
	 */
	public function extends(): TypedStack
	{
		return $this->ts_extends;
	}

	/**
	 * Returns the key-value store used by block handlers to share parse-time state.
	 */
	public function store(): Store
	{
		return $this->ps_store;
	}

	/**
	 * Emits a $context->newContext() call into the PHP output.
	 *
	 * Called by block handlers (BlockEach, BlockScoped) that push a new
	 * scope layer before rendering their body.
	 *
	 * @param string $context_var the PHP variable name of the DataContext
	 *
	 * @return $this
	 */
	public function newDataContext(string $context_var = Blate::DATA_CONTEXT_VAR): static
	{
		$this->writeCode(\PHP_EOL . $context_var . '->newContext();' . \PHP_EOL);

		return $this;
	}

	/**
	 * Emits a $context->popContext() call into the PHP output.
	 *
	 * Called by block handlers when their scoped body ends.
	 *
	 * @param string $context_var the PHP variable name of the DataContext
	 *
	 * @return $this
	 */
	public function popDataContext(string $context_var = Blate::DATA_CONTEXT_VAR): static
	{
		$this->writeCode(\PHP_EOL . $context_var . '->popContext();' . \PHP_EOL);

		return $this;
	}

	/**
	 * Builds the full class-body string for the compiled template.
	 *
	 * Produces a build() method from $main_code and one method per named slot
	 * from $ts_slots.
	 */
	public function getClassBody(): string
	{
		$output = \sprintf(
			'
		public function build(DataContext %s): void
		{
			%s
		}',
			Blate::DATA_CONTEXT_VAR,
			$this->main_code
		);

		$slots = $this->ts_slots->getAll();
		foreach ($slots as $slot) {
			$name   = $slot->getValue();
			$output .= \sprintf(
				'

		public function %s(DataContext %s): void
		{
			%s
		}',
				Blate::slotMethodName($name),
				Blate::DATA_CONTEXT_VAR,
				$this->ts_slots->getCode($slot)
			);
		}

		return $output;
	}

	/**
	 * Appends raw PHP code to the active output channel.
	 *
	 * Routes to the current slot buffer when a slot is open, otherwise appends
	 * to $main_code.
	 *
	 * @param string $code PHP code to append verbatim
	 *
	 * @return $this
	 */
	public function writeCode(string $code): static
	{
		if ($this->ts_slots->getActive()) {
			$this->ts_slots->write($code);
		} else {
			$this->main_code .= $code;
		}

		return $this;
	}

	/**
	 * Appends an echo statement for the given literal string value.
	 *
	 * The value is quoted via Helpers::quote() so it is emitted as a PHP string
	 * literal, avoiding variable interpolation or injection.
	 *
	 * @param string $str the literal text to echo
	 *
	 * @return $this
	 */
	public function write(string $str): static
	{
		$code = \PHP_EOL . 'echo ' . Helpers::quote($str) . ';';

		if ($this->ts_slots->getActive()) {
			$this->ts_slots->write($code);
		} else {
			$this->main_code .= $code;
		}

		return $this;
	}

	/**
	 * Appends an echo statement for the given compiled PHP expression.
	 *
	 * When $escape is true the value is wrapped with $context->noHTML() so it
	 * is HTML-escaped before output ({expr}).  When false it is echoed raw
	 * ({= expr}).
	 *
	 * @param string $expression compiled PHP expression string
	 * @param bool   $escape     true to HTML-escape the output (default)
	 *
	 * @return $this
	 */
	public function writeExpression(string $expression, bool $escape = true): static
	{
		$code = \PHP_EOL;

		if ($escape) {
			$code .= 'echo ' . Blate::DATA_CONTEXT_VAR . '->noHTML(' . $expression . ');';
		} else {
			$code .= 'echo (' . $expression . ');';
		}

		if ($this->ts_slots->getActive()) {
			$this->ts_slots->write($code);
		} else {
			$this->main_code .= $code;
		}

		return $this;
	}
}
