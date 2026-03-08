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

namespace Blate\Helpers;

use Blate\Blate;
use Blate\SimpleChain;

/**
 * Class Helpers.
 */
class Helpers
{
	public static function register(): void
	{
		Blate::registerHelper('has', [static::class, 'has']);
		Blate::registerHelper('type', [static::class, 'type']);
		Blate::registerHelper('attrs', [static::class, 'attrs']);
		Blate::registerHelper('join', [static::class, 'join']);
		Blate::registerHelper('concat', [static::class, 'concat']);
		Blate::registerHelper('keys', [static::class, 'keys']);
		Blate::registerHelper('values', [static::class, 'values']);
		Blate::registerHelper('length', [static::class, 'length']);
		Blate::registerHelper('escape', [static::class, 'escape']);
		Blate::registerHelper('escapeHtml', [static::class, 'escapeHtml']);
		Blate::registerHelper('quote', [static::class, 'quote']);
		Blate::registerHelper('unquote', [static::class, 'unquote']);
	}

	/**
	 * Check if a property exists in a target.
	 *
	 * @param mixed      $target The target
	 * @param int|string $prop   The property
	 *
	 * @return bool
	 */
	public static function has(mixed $target, int|string $prop): bool
	{
		return SimpleChain::has($target, $prop, $val);
	}

	/**
	 * Escape a value.
	 *
	 * @param mixed $value The value to escape
	 *
	 * @return string
	 */
	public static function escape(mixed $value): string
	{
		return \htmlspecialchars((string) $value, \ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Escape a value for html.
	 *
	 * @param mixed $untrusted The value to escape
	 *
	 * @return string
	 */
	public static function escapeHtml(mixed $untrusted): string
	{
		return \htmlentities((string) $untrusted, \ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Quote a string.
	 *
	 * @param string $str the string to quote
	 *
	 * @return string
	 */
	public static function quote(string $str): string
	{
		// return '"' . \str_replace(['"', '$'], ['\\"', '\\$'], $str) . '"';
		return '\'' . \str_replace('\'', '\\\'', $str) . '\'';
	}

	/**
	 * Unquote a string.
	 *
	 * @param string $str the string to unquote
	 *
	 * @return string
	 */
	public static function unquote(string $str): string
	{
		if (\str_starts_with($str, '\'') && \str_ends_with($str, '\'')) {
			return \str_replace('\\\'', '\'', \substr($str, 1, -1));
		}

		if (\str_starts_with($str, '"') && \str_ends_with($str, '"')) {
			return \str_replace('\\"', '"', \substr($str, 1, -1));
		}

		return $str;
	}

	/**
	 * Generate attributes string.
	 *
	 * @param array<string, null|bool|int|string> $data
	 *
	 * @return string
	 */
	public static function attrs(array $data): string
	{
		$out = '';

		foreach ($data as $raw_attr => $val) {
			$attr = self::escape((string) $raw_attr);

			if (null !== $val && '' !== $val) {
				if (\is_bool($val)) {
					$attr .= ($val ? '' : '="false"');
				} else {
					$attr .= '="' . self::escape($val) . '"';
				}
			}

			$out .= ($out ? ' ' . $attr : $attr);
		}

		return $out;
	}

	/**
	 * Join an array of strings.
	 *
	 * @param array<string> $data The array of strings
	 * @param string        $glue The glue
	 *
	 * @return string
	 */
	public static function join(array $data, string $glue = ''): string
	{
		return \implode($glue, $data);
	}

	/**
	 * Concatenate strings.
	 *
	 * @param string ...$args The strings to concatenate
	 *
	 * @return string
	 */
	public static function concat(string ...$args): string
	{
		return \implode('', $args);
	}

	/**
	 * Get the keys of an array.
	 *
	 * @param array $data The array
	 *
	 * @return array
	 */
	public static function keys(array $data): array
	{
		return \array_keys($data);
	}

	/**
	 * Get the values of an array.
	 *
	 * @param array $data The array
	 *
	 * @return array
	 */
	public static function values(array $data): array
	{
		return \array_values($data);
	}

	/**
	 * Get the length of a value.
	 *
	 * @param mixed $value The value
	 *
	 * @return int
	 */
	public static function length(mixed $value): int
	{
		if (\is_string($value)) {
			return \mb_strlen($value);
		}

		if (null === $value || \is_bool($value) || \is_numeric($value)) {
			return (int) $value;
		}

		return \count($value);
	}

	/**
	 * Check the type of a value.
	 *
	 * If the type is not provided, it returns the type of the value.
	 * If the type is provided, it returns a boolean indicating if the value is of the provided type.
	 *
	 * @param mixed       $value The value
	 * @param null|string $type  The type
	 *
	 * @return bool|string
	 */
	public static function type(mixed $value, ?string $type = null): bool|string
	{
		if (null === $type) {
			return \get_debug_type($value);
		}

		return \get_debug_type($value) === $type;
	}
}
