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

use Blate\Exceptions\BlateException;
use LogicException;

/**
 * Class TemplateParsed.
 *
 * Abstract base class for all compiled template files.
 *
 * When a .blate file is compiled, the output is a class that extends this base.
 * The generated class implements build(DataContext $context): void with the
 * compiled template body, plus one method per named slot.
 *
 * At render time, an extends block calls injectSlot() on the parent template
 * to override individual slots before calling build().
 */
abstract class TemplateParsed
{
	protected array $injected_slots = [];

	/**
	 * Checks whether a slot override has been injected for the given slot name.
	 *
	 * @param string $name slot name
	 */
	public function hasInjectedSlot(string $name): bool
	{
		return isset($this->injected_slots[$name]);
	}

	/**
	 * Registers a callable as an override for the named slot.
	 *
	 * The callable receives a DataContext and is expected to echo the slot content.
	 *
	 * @param string   $name the slot name to override
	 * @param callable $slot the renderer: (DataContext $ctx): void
	 *
	 * @return $this
	 */
	public function injectSlot(string $name, callable $slot): static
	{
		$this->injected_slots[$name] = $slot;

		return $this;
	}

	/**
	 * Executes the injected slot renderer for the given name.
	 *
	 * @param string      $name         the slot name
	 * @param DataContext $data_context the current render context
	 *
	 * @throws LogicException when no slot with the given name has been injected
	 */
	public function renderInjectedSlot(string $name, DataContext $data_context): void
	{
		if (!$this->hasInjectedSlot($name)) {
			throw new LogicException(\sprintf('Unknown slot %s', $name));
		}

		$fn = $this->injected_slots[$name];

		$fn($data_context);
	}

	/**
	 * @param Blate $to_extends
	 * @param mixed $data
	 *
	 * @return DataContext
	 *
	 * @throws BlateException
	 */
	public function createExtendsContext(Blate $to_extends, mixed $data): DataContext
	{
		if (!\is_array($data) && !\is_object($data)) {
			throw new BlateException(\sprintf('Invalid context data type, found "%s" while expecting array|object.', \get_debug_type($data)));
		}

		return new DataContext($data, $to_extends);
	}

	/**
	 * @param DataContext $context
	 */
	abstract public function build(DataContext $context): void;

	/**
	 * Push a BlateTemplateScope, call build(), then pop the scope.
	 *
	 * Use this method in place of build() at all call sites (runGet(),
	 * {@import}, {@extends}) so that Blate::scope() is always available
	 * inside helpers while a template is executing.
	 *
	 * @param DataContext $context
	 */
	public function run(DataContext $context): void
	{
		Blate::pushScope(new BlateTemplateScope($context, $context->getBlate()));

		try {
			$this->build($context);
		} finally {
			Blate::popScope();
		}
	}
}
