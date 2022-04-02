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

namespace Blate\Interfaces;

/**
 * Interface TokenHandlerInterface.
 */
interface TokenHandlerInterface
{
	/**
	 * Called when the parser found a token that matches what we are listening for.
	 */
	public function handle(ParserInterface $parser, TokenInterface $token, bool $is_head): void;
}
