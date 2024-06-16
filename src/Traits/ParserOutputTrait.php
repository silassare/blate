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
use Blate\TypedStack;
use PHPUtils\Store\Store;

/**
 * Trait ParserOutputTrait.
 */
trait ParserOutputTrait
{
	protected string $main_code = '';
	protected TypedStack $ts_slots;
	protected TypedStack $ts_extends;
	protected Store $ps_store;

	public function slots(): TypedStack
	{
		return $this->ts_slots;
	}

	public function extends(): TypedStack
	{
		return $this->ts_extends;
	}

	public function store(): Store
	{
		return $this->ps_store;
	}

	public function newDataContext(string $context_var = Blate::DATA_CONTEXT_VAR): static
	{
		$this->writeCode(\PHP_EOL . $context_var . '->newContext();' . \PHP_EOL);

		return $this;
	}

	public function popDataContext(string $context_var = Blate::DATA_CONTEXT_VAR): static
	{
		$this->writeCode(\PHP_EOL . $context_var . '->popContext();' . \PHP_EOL);

		return $this;
	}

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

	public function writeCode(string $code): static
	{
		if ($this->ts_slots->getActive()) {
			$this->ts_slots->write($code);
		} else {
			$this->main_code .= $code;
		}

		return $this;
	}

	public function write(string $str): static
	{
		$code = \PHP_EOL . 'echo ' . Blate::quote($str) . ';';

		if ($this->ts_slots->getActive()) {
			$this->ts_slots->write($code);
		} else {
			$this->main_code .= $code;
		}

		return $this;
	}

	public function writeExpression(string $expression, bool $clean_html = true): static
	{
		$code = \PHP_EOL . ($clean_html ? 'echo ' . Blate::DATA_CONTEXT_VAR . '->noHTML(' . $expression . ');' : 'echo (' . $expression . ');');

		if ($this->ts_slots->getActive()) {
			$this->ts_slots->write($code);
		} else {
			$this->main_code .= $code;
		}

		return $this;
	}
}
