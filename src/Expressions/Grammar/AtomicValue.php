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

namespace Blate\Expressions\Grammar;

use Blate\Interfaces\ParserInterface;
use Blate\Interfaces\TokenHandlerInterface;
use Blate\Interfaces\TokenInterface;
use Override;

/**
 * Class AtomicValue.
 *
 * Handles literal number (T_DNUMBER) and string (T_STRING) tokens.
 * The raw token value is emitted directly into the PHP output; PHP itself
 * handles the literal semantics.
 */
class AtomicValue implements TokenHandlerInterface
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function handle(ParserInterface $parser, TokenInterface $token, bool $is_head): void
	{
		$current = $token;
		$lexer   = $parser->getLexer();

		// Emit the literal as-is; advance to the next token.
		$parser->write($current);
		$lexer->move();
	}
}
