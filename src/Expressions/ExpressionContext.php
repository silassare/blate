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
 * Class ExpressionContext.
 *
 * Holds the chain-head tracking state for a single expression parse.
 *
 * The expression compiler emits chain calls such as:
 *   {foo.bar}  ->  $context->chain('L:I')->get('L:I', 'foo')->get('L:I', 'bar')->val()
 *
 * To know when to terminate a chain with ->val(), the compiler tracks
 * the "active chain head" -- the token that started the current chain.
 * Inside grouped expressions (parentheses, brackets) the parent token
 * stores this state as an attribute; at root level it is held in the
 * $root_active_chain instance property.
 *
 * One ExpressionContext instance is created per ExpressionParser instance,
 * so each parse starts with clean state -- no reset() call is required.
 */
class ExpressionContext
{
	protected ?TokenInterface $root_active_chain = null;

	/**
	 * Returns the active chain head for the scope containing the given token.
	 *
	 * When the token has a parent (i.e., it is inside a grouped expression
	 * such as parentheses or brackets) the chain head is stored on the
	 * parent's ATTR_ACTIVE_CHAIN attribute.  Otherwise it is stored on
	 * the $root_active_chain instance property.
	 *
	 * @param TokenInterface $token the token whose scope we query
	 *
	 * @return null|TokenInterface the active chain head, or null if none
	 */
	public function getActiveChain(TokenInterface $token): ?TokenInterface
	{
		$parent = $token->getParent();

		if ($parent) {
			return $parent->getAttribute(Token::ATTR_ACTIVE_CHAIN);
		}

		return $this->root_active_chain;
	}

	/**
	 * Sets the active chain head for the scope containing the given token.
	 *
	 * @param TokenInterface      $token      the token whose scope we update
	 * @param null|TokenInterface $chain_head the new chain head, or null to clear
	 */
	public function setActiveChain(TokenInterface $token, ?TokenInterface $chain_head): void
	{
		$parent = $token->getParent();

		if ($parent) {
			$parent->setAttribute(Token::ATTR_ACTIVE_CHAIN, $chain_head);
		} else {
			$this->root_active_chain = $chain_head;
		}
	}

	/**
	 * Returns a predicate that is true while the current token is a
	 * direct child of the given parent token.
	 *
	 * Used by the expression parser to limit processing to tokens within
	 * a specific group (e.g., the arguments of a function call).
	 *
	 * @param TokenInterface $parent the group-opener token to match against
	 *
	 * @return callable predicate: (TokenInterface $token): bool
	 */
	public function whileInChildrenOf(TokenInterface $parent): callable
	{
		return static function ($token) use ($parent) {
			return $token->getParent() === $parent;
		};
	}
}
