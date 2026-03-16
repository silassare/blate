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
use Blate\Interfaces\TokenInterface;
use Blate\Token;
use Override;
use PHPUtils\Str;

/**
 * Class BlockSet.
 *
 * Implements the {@set name = expr; name2 = expr2} variable-assignment block.
 *
 * Sets one or more template variables in the current DataContext scope.
 * Multiple assignments in a single tag are delimited by semicolons:
 *   {@set x = foo; y = bar}
 *
 * Compile-time output:
 *   $context->set('name', expr);
 */
class BlockSet extends Block
{
	public const NAME = 'set';

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
		handle_var:

		$var_name = $this->lexer->nextIs(Token::T_NAME, null, true);
		$this->lexer->nextIs(null, '=', true);
		$this->lexer->move();
		$expression = (new Expression())->getWhileTrue($this->lexer, static function (TokenInterface $token) {
			return Token::T_TAG_CLOSE !== $token->getType() && Token::T_SEMICOLON !== $token->getType();
		});
		$this->parser->writeCode(Str::interpolate(
			"\n{ctx}->set('{var_name}', {expression});\n",
			[
				'ctx'        => Blate::DATA_CONTEXT_VAR,
				'var_name'   => $var_name->getValue(),
				'expression' => $expression,
			]
		));

		$current = $this->lexer->current();

		if ($current && Token::T_SEMICOLON === $current->getType()) {
			$next = $this->lexer->lookForward(true);
			if ($next && Token::T_NAME === $next->getType()) {
				goto handle_var;
			}
			$this->lexer->nextIs(Token::T_TAG_CLOSE, null, true);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function requireClose(): bool
	{
		return false;
	}
}
