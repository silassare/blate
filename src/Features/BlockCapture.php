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
use Blate\Token;
use Override;
use PHPUtils\Str;

/**
 * Class BlockCapture.
 *
 * Implements the {@capture varname}...{/capture} block.
 *
 * Buffers the rendered body of the block and stores the result as a template
 * variable accessible via {varname} in the current data context.
 *
 * Example:
 *   {@capture greeting}Hello, {name}!{/capture}
 *   {greeting}
 *
 * The captured string is stored with htmlspecialchars NOT applied; use {= greeting}
 * to output it unescaped or {greeting} to apply the default escaping.
 */
class BlockCapture extends Block
{
	public const NAME = 'capture';

	private string $var_name = '';

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
		$var_token      = $this->lexer->nextIs(Token::T_NAME, null, true);
		$this->var_name = $var_token->getValue();
		$this->lexer->nextIs(Token::T_TAG_CLOSE, null, true);
		$this->parser->writeCode('ob_start();');
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function onClose(): void
	{
		$this->parser->writeCode(Str::interpolate(
			"{ctx}->set('{var_name}', ob_get_clean());",
			[
				'ctx'      => Blate::DATA_CONTEXT_VAR,
				'var_name' => $this->var_name,
			]
		));
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
