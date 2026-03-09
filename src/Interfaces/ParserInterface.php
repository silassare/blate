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
 * Interface ParserInterface.
 */
interface ParserInterface
{
	/**
	 * Returns the active chain head for the scope containing the given token.
	 */
	public function getActiveChain(TokenInterface $token): ?TokenInterface;

	/**
	 * Sets the active chain head for the scope containing the given token.
	 */
	public function setActiveChain(TokenInterface $token, ?TokenInterface $chain_head): void;

	/**
	 * Returns a predicate that is true while the current token is a direct child of the given parent.
	 */
	public function whileInChildrenOf(TokenInterface $parent): callable;

	/**
	 * Returns lexer.
	 */
	public function getLexer(): LexerInterface;

	/**
	 * Writes to output.
	 *
	 * @return $this
	 */
	public function write(string|TokenInterface $str): static;

	/**
	 * Gets output string.
	 */
	public function getOutput(): string;

	/**
	 * Parse.
	 *
	 * @return $this
	 */
	public function parse(?callable $while_true = null, ?array $options = []): static;
}
