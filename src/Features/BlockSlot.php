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

namespace Blate\Features;

use Blate\Blate;
use Blate\Exceptions\BlateParserException;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use Blate\Token;
use Override;
use PHPUtils\Str;

/**
 * Class BlockSlot.
 *
 * Implements the {@slot name}default content{/slot} block.
 *
 * Two contexts:
 *
 *   Inside an {@extends} block:
 *     Captures the slot body as a closure injected into the parent template
 *     instance via injectSlot().  A {:default} breakpoint renders the parent's
 *     own default content for that slot.
 *
 *   Outside an {@extends} block (standalone slot definition):
 *     Generates a public slot method on the compiled template class.  At render
 *     time, if an override has been injected (via injectSlot()), the override
 *     is called; otherwise the default method body is used.
 *
 * Slot names must be unique per template to avoid method name conflicts.
 */
class BlockSlot extends Block
{
	public const NAME         = 'slot';
	public const SLOT_DEFAULT = 'default';

	private TokenInterface $slot;
	private string $slot_inject_arg;

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException
	 */
	#[Override]
	public function onOpen(): void
	{
		$name_token = $this->lexer->nextIs(Token::T_NAME, null, true);
		$name       = $name_token->getValue();

		$extends               = $this->parser->extends()
			->getActive();
		$this->slot            = $name_token;
		$slots                 = $this->parser->slots();
		$this->slot_inject_arg = $this->parser->createVar();

		if (!$extends) {
			$store_key = self::NAME . '.' . $name;

			if (
				$this->parser->store()
					->has($store_key)
			) {
				throw BlateParserException::withToken(Message::SLOT_NAME_ALREADY_IN_USE, $this->slot);
			}

			$this->parser->store()
				->set($store_key, 1);
		}

		$slots->start($this->slot);

		if ($extends) {
			// {@slot name:inject}
			$next = $this->lexer->lookForward(true);

			if ($next && ':' === $next->getValue()) {
				$this->lexer->nextIs(null, ':');

				$inject = $this->lexer->nextIs(Token::T_NAME);

				$this->parser->writeCode(Str::interpolate(
					"\n{ctx}->set('{inject_name}', {inject_arg});\n",
					[
						'ctx'         => Blate::DATA_CONTEXT_VAR,
						'inject_name' => $inject->getValue(),
						'inject_arg'  => $this->slot_inject_arg,
					]
				));
			}
		}

		$this->parser->tagClose();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function onClose(): void
	{
		$name  = $this->slot->getValue();
		$slots = $this->parser->slots();

		$extends = $this->parser->extends()
			->getActive();

		if ($extends) {
			$code = $slots->getCode($this->slot);
			$slots->end(true);
			$extended_instance = $extends->getAttribute(Token::ATTR_EXTENDED_INSTANCE_VAR);
			$extended_context  = $extends->getAttribute(Token::ATTR_EXTENDED_CONTEXT_VAR);
			$this->parser->writeCode(Str::interpolate(
				"\n{instance}->injectSlot('{slot_name}', function (DataContext {inject_arg}) use ({ctx}, {instance}, {ext_ctx}) {\n",
				[
					'instance'   => $extended_instance,
					'slot_name'  => $name,
					'inject_arg' => $this->slot_inject_arg,
					'ctx'        => Blate::DATA_CONTEXT_VAR,
					'ext_ctx'    => $extended_context,
				]
			));
			$this->parser->newDataContext();
			$this->parser->writeCode($code);
			$this->parser->popDataContext();
			$this->parser->writeCode('
});
');
		} else {
			$slots->end();
			$this->parser->writeCode(Str::interpolate(
				"\nif (\$this->hasInjectedSlot('{slot_name}')) {\n"
					. "\t\$this->renderInjectedSlot('{slot_name}', {ctx});\n"
					. "} else {\n"
					. "\t\$this->{slot_method}({ctx});\n"
					. "}\n",
				[
					'slot_name'   => $name,
					'ctx'         => Blate::DATA_CONTEXT_VAR,
					'slot_method' => Blate::slotMethodName($name),
				]
			));
		}
	}

	#[Override]
	public function onBreakPoint(TokenInterface $token): void
	{
		if (self::SLOT_DEFAULT !== $token->getValue()) {
			throw BlateParserException::withToken(Message::BLOCK_BREAKPOINT_UNEXPECTED, $token);
		}

		$extends = $this->parser->extends()
			->getActive();

		if (!$extends) {
			throw BlateParserException::withToken(Message::SLOT_DEFAULT_ALLOWED_ONLY_IN_EXTENDS, $token);
		}

		$name             = $this->slot->getValue();
		$extended_context = $extends->getAttribute(Token::ATTR_EXTENDED_CONTEXT_VAR);
		$this->parser->writeCode(Str::interpolate(
			"\n{instance}->{slot_method}({ext_ctx});\n",
			[
				'instance'    => $extends->getAttribute(Token::ATTR_EXTENDED_INSTANCE_VAR),
				'slot_method' => Blate::slotMethodName($name),
				'ext_ctx'     => $extended_context,
			]
		));

		$this->parser->tagClose();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function requireClose(): bool
	{
		return true;
	}
}
