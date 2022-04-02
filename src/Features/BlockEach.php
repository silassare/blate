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
use Blate\Message;
use Blate\Token;

/**
 * Class BlockEach.
 */
class BlockEach extends Block
{
	public const NAME = 'each';

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Blate\Exceptions\BlateParserException
	 */
	public function onOpen(): void
	{
		// each value[:key[:index]] in list

		$value_access_name = $this->lexer->nextIs(Token::T_NAME, null, true);
		$key_access_name   = null;
		$index_access_name = null;
		$next              = $this->lexer->nextIsOneOf([Token::T_COLON, Token::T_NAME], true);

		if (Token::T_COLON === $next->getType()) {
			$key_access_name    = $this->lexer->nextIs(Token::T_NAME, null, true);
			$forward            = $this->lexer->lookForward(true);

			if ($forward && Token::T_COLON === $forward->getType()) {
				$this->lexer->nextIs(Token::T_COLON, null, true);
				$index_access_name = $this->lexer->nextIs(Token::T_NAME, null, true);
			}

			$this->lexer->nextIs(Token::T_NAME, 'in', true);
		} elseif ('in' !== $next->getValue()) {
			throw BlateParserException::withChunk(Message::UNEXPECTED_WHILE_EXPECTING, $next->getChunk()
				->setExpected('in'));
		}

		$this->lexer->nextIs(Token::T_WHITESPACE);

		$list_access = (new Expression())->get($this->lexer);

		$value_var = Blate::createVar();
		$key_var   = Blate::createVar();
		$index_var = Blate::createVar();
		$code      = '';

		$this->parser->newDataContext();

		if (isset($key_access_name, $index_access_name)) {
			$code .= \sprintf(
				'
%s = 0;
foreach (%s as %s => %s) {
	%s->set(\'%s\',%s)->set(\'%s\',%s)->set(\'%s\',%s++);
',
				$index_var,
				$list_access,
				$key_var,
				$value_var,
				Blate::DATA_CONTEXT_VAR,
				$key_access_name->getValue(),
				$key_var,
				$value_access_name->getValue(),
				$value_var,
				$index_access_name->getValue(),
				$index_var
			);
		} elseif (isset($key_access_name)) {
			$code .= \sprintf(
				'
foreach (%s as %s => %s) {
	%s->set(\'%s\',%s)->set(\'%s\',%s);
',
				$list_access,
				$key_var,
				$value_var,
				Blate::DATA_CONTEXT_VAR,
				$key_access_name->getValue(),
				$key_var,
				$value_access_name->getValue(),
				$value_var
			);
		} else {
			$code .= \sprintf(
				'
foreach (%s as %s) {
	%s->set(\'%s\',%s);
',
				$list_access,
				$value_var,
				Blate::DATA_CONTEXT_VAR,
				$value_access_name->getValue(),
				$value_var
			);
		}

		$this->parser->writeCode($code);
	}

	/**
	 * {@inheritDoc}
	 */
	public function onClose(): void
	{
		$this->parser->writeCode('
}
');
		$this->parser->popDataContext();
	}

	/**
	 * {@inheritDoc}
	 */
	public function requireClose(): bool
	{
		return true;
	}
}
