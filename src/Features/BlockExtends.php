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
use Blate\Expressions\Expression;
use Blate\Helpers\Helpers;
use Blate\Interfaces\BlockInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use Blate\Token;
use PHPUtils\FS\PathUtils;

/**
 * Class BlockExtends.
 *
 * Implements the {@extends 'path' context}{@slot name}...{/slot}{/extends} block.
 *
 * At compile time this block:
 *   1. Resolves the path of the parent template relative to the current source.
 *   2. Emits PHP code to load, parse, and instantiate the parent template.
 *   3. Creates an extends context from the given expression.
 *   4. Allows only {@slot} children; any other block or non-whitespace content throws.
 *   5. On close, emits a call to the parent template's build() method.
 *
 * The parent class cannot be the same file as the current template.
 */
class BlockExtends extends Block
{
	public const NAME = 'extends';

	private TokenInterface $extends;

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onChildBlockFound(BlockInterface $block): void
	{
		if (BlockSlot::NAME !== $block->getName()) {
			throw BlateParserException::withToken(Message::ONLY_SLOT_DEFINITION_IN_EXTENDS, $block->getToken());
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function onChildContentFound(TokenInterface $token): void
	{
		if (!\preg_match('~^\s+$~', $token->getValue())) {
			throw BlateParserException::withToken(Message::UNEXPECTED, $token);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function onChildExpressionFound(TokenInterface $token, bool $escape): void
	{
		throw BlateParserException::withToken(Message::UNEXPECTED, $token);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException
	 */
	public function onOpen(): void
	{
		$this->extends = $this->lexer->current();

		$this->parser->extends()
			->start($this->extends);

		$path_token = $this->lexer->nextIs(Token::T_STRING, null, true);

		$blate = $this->parser->getBlate();

		$abs_path = PathUtils::resolve($blate->getSrcDir(), Helpers::unquote($path_token->getValue()));

		if ($abs_path === $blate->getSrcPath()) {
			throw BlateParserException::withToken(Message::EXTENDED_PATH_IS_SELF, $path_token);
		}

		$this->lexer->nextIs(Token::T_WHITESPACE);

		$context               = (new Expression())->get($this->lexer);
		$extended_blate_var    = Blate::createVar();
		$extended_instance_var = Blate::createVar();
		$extended_context_var  = Blate::createVar();

		$this->extends->setAttribute(Token::ATTR_EXTENDED_BLATE_VAR, $extended_instance_var);
		$this->extends->setAttribute(Token::ATTR_EXTENDED_INSTANCE_VAR, $extended_instance_var);
		$this->extends->setAttribute(Token::ATTR_EXTENDED_CONTEXT_VAR, $extended_context_var);

		$this->parser->writeCode(\sprintf(
			'
%s = Blate::fromPath(%s)->parse();
%s = %s->getParsedInstance();
%s = $this->createExtendsContext(%s, %s);
',
			$extended_blate_var,
			Helpers::quote($abs_path),
			$extended_instance_var,
			$extended_blate_var,
			$extended_context_var,
			$extended_blate_var,
			$context
		));
	}

	/**
	 * {@inheritDoc}
	 */
	public function onClose(): void
	{
		$this->parser->writeCode(\sprintf(
			'
%s->build(%s);',
			$this->extends->getAttribute(Token::ATTR_EXTENDED_INSTANCE_VAR),
			$this->extends->getAttribute(Token::ATTR_EXTENDED_CONTEXT_VAR),
		));
	}

	/**
	 * {@inheritDoc}
	 */
	public function requireClose(): bool
	{
		return true;
	}
}
