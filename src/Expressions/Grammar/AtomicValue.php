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

/**
 * Class AtomicValue.
 */
class AtomicValue implements TokenHandlerInterface
{
	public function handle(ParserInterface $parser, TokenInterface $token, bool $is_head): void
	{
		$current = $token;
		$lexer   = $parser->getLexer();

		$parser->write($current);
		$lexer->move();
	}
}
