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

namespace Blate;

use Blate\Features\BlockExtends;
use Blate\Features\BlockSlot;
use Blate\Interfaces\ChunkInterface;

/**
 * Class Message.
 */
final class Message
{
	public const BLOCK_UNDEFINED = 'Unknown block name "{found}" on line {line} at index {index}.';

	public const BLOCK_NEVER_CLOSED = 'Block "{found}" opened on line {line} index {index} was never closed.';

	public const GROUP_NEVER_CLOSED = 'Opener "{found}" found on line {line} index {index} was never closed.';

	public const GROUP_NEVER_CLOSED_IN_EXPRESSION = 'Expression group "{found}" opened on line {line} index {index} was never closed.';

	public const BLOCK_BREAKPOINT_UNEXPECTED = 'Unexpected block breakpoint "{unexpected}" on line {line} at index {index}.';

	public const BLOCK_CLOSER_UNEXPECTED = 'Unexpected closing block "{unexpected}" on line {line} at index {index}.';

	public const BLOCK_CLOSER_UNEXPECTED_WHILE_EXPECTING = 'Unexpected closing block "{unexpected}" on line {line} at index {index}, while expecting "{expected}".';

	public const UNEXPECTED = 'Unexpected "{unexpected}" on line {line} at index {index}.';

	public const UNEXPECTED_WHILE_EXPECTING = 'Found "{unexpected}" on line {line} at index {index}, while expecting "{expected}".';

	public const UNEXPECTED_WHILE_EXPECTING_NUMBER = 'Found "{unexpected}" on line {line} at index {index}, while expecting number.';

	public const UNEXPECTED_WHILE_EXPECTING_EXPRESSION = 'Found "{unexpected}" on line {line} at index {index}, while expecting expression.';

	public const UNEXPECTED_END_OF_EXPRESSION = 'Unexpected end of expression.';

	public const UNEXPECTED_EOF = 'Unexpected end of file.';

	public const UNEXPECTED_EOF_WHILE_EXPECTING = 'Unexpected end of file, while expecting "{expected}".';

	public const UNEXPECTED_EOF_WHILE_EXPECTING_NAME = 'Unexpected end of file, while expecting name.';

	public const UNEXPECTED_EOF_WHILE_EXPECTING_NUMBER = 'Unexpected end of file, while expecting number.';

	public const INVALID_NUMBER = 'Invalid number "{found}" found on line {line} at index {index}.';

	public const NO_SAVED_STATE_CANT_RESTORE = 'No saved state. Can\'t restore.';

	public const CANT_EDIT_CHUNK = 'Trying to modify a locked string chunk.';

	public const CHAIN_VALUE_NOT_A_CALLABLE = 'Current value of type "%s" is not callable.';

	public const CHAIN_UNDEFINED_KEY = 'Can\'t get "%s" from "%s".';

	public const FILE_IS_NOT_READABLE = 'Unable to read file at : %s';

	public const FILE_IS_NOT_WRITABLE = 'File "%s" is not writable.';

	public const CANT_ADD_CHILD_NOT_A_GROUP = 'Can\'t add child token to the current token.';

	public const SLOT_NAME_ALREADY_IN_USE = 'Slot name "{found}" is already in use, found on line {line} at index {index}.';

	public const SLOT_DEFAULT_ALLOWED_ONLY_IN_EXTENDS = 'Can\'t use slot "' . BlockSlot::SLOT_DEFAULT . '" when parent "' . BlockSlot::NAME . '" is outside an "' . BlockExtends::NAME . '" block, found on line {line} at index {index}.';

	public const ONLY_SLOT_DEFINITION_IN_EXTENDS = 'You can only define "' . BlockSlot::NAME . '" in "' . BlockExtends::NAME . '" block, "{found}" found on line {line} at index {index}.';

	public const EXTENDED_PATH_IS_SELF = 'A template shouldn\'t extend itself, "{found}" found on line {line} at index {index}.';

	public const IMPORT_PATH_IS_SELF = 'A template shouldn\'t import itself, "{found}" found on line {line} at index {index}.';

	public const INVALID_BLOCK_NAME =  'Invalid block name "%s", block name should match pattern: "%s"';

	/**
	 * Gets message.
	 */
	public static function msg(string $str, ChunkInterface $r): string
	{
		$maps               = $r->getLocation();
		$maps['found']      = $r->getValue();
		$maps['expected']   = $r->getExpected();
		$maps['unexpected'] = $r->getUnexpected();

		$keys   = [];
		$values = [];

		foreach ($maps as $key => $value) {
			$keys[]   = '{' . $key . '}';
			$values[] = $value;
		}

		return \str_replace($keys, $values, $str);
	}
}
