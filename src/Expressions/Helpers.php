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

namespace Blate\Expressions;

use Blate\Interfaces\TokenInterface;
use Blate\Token;

/**
 * Class Helpers.
 */
class Helpers
{
	protected static ?TokenInterface $root_active_chain = null;

	public static function getActiveChain(TokenInterface $token): ?TokenInterface
	{
		$parent = $token->getParent();

		if ($parent) {
			return $parent->getAttribute(Token::ATTR_ACTIVE_CHAIN);
		}

		return self::$root_active_chain;
	}

	public static function setActiveChain(TokenInterface $token, ?TokenInterface $chain_head): void
	{
		$parent = $token->getParent();

		if ($parent) {
			$parent->setAttribute(Token::ATTR_ACTIVE_CHAIN, $chain_head);
		} else {
			self::$root_active_chain = $chain_head;
		}
	}

	public static function whileInChildrenOf(TokenInterface $parent): callable
	{
		return static function ($token) use ($parent) {
			return $token->getParent() === $parent;
		};
	}
}
