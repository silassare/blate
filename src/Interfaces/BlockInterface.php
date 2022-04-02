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

use Blate\Parser;

/**
 * Class BlockInterface.
 */
interface BlockInterface
{
	/**
	 * BlockInterface constructor.
	 */
	public function __construct(Parser $parser, TokenInterface $token);

	/**
	 * Returns the block name token.
	 */
	public function getToken(): TokenInterface;

	/**
	 * Returns the block name.
	 */
	public function getName(): string;

	/**
	 * Called when the block is found.
	 */
	public function onOpen(): void;

	/**
	 * Called when a child block is found in this block.
	 */
	public function onChildBlockFound(self $block): void;

	/**
	 * Called when a raw data token is found in this block.
	 */
	public function onChildContentFound(TokenInterface $token): void;

	/**
	 * Called when a child expression is found in this block.
	 */
	public function onChildExpressionFound(TokenInterface $token): void;

	/**
	 * Called when a child breakpoint is found in this block.
	 */
	public function onBreakPoint(TokenInterface $token): void;

	/**
	 * Called when the block is closed.
	 */
	public function onClose(): void;

	/**
	 * Called to checks if the block should be closed.
	 */
	public function requireClose(): bool;
}
