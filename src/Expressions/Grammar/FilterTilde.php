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

use Blate\Exceptions\BlateParserException;
use Blate\Expressions\Helpers;
use Blate\Interfaces\ParserInterface;
use Blate\Interfaces\TokenHandlerInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Message;

/**
 * Class FilterTilde.
 */
class FilterTilde implements TokenHandlerInterface
{
	public function handle(ParserInterface $parser, TokenInterface $token, bool $is_head): void
	{
		// {expression ~ filter ~ filter}
		// {expression ~ filter(arg1,arg2) ~ filter}
		// applyFilters(expression, [filter,arg1,arg2], [filter])
		$current = $token;

		if (!Helpers::getActiveChain($current)) {
			throw BlateParserException::withToken(Message::UNEXPECTED, $current);
		}

		throw new BlateParserException('Filters are not yet implemented.');
	}
}
