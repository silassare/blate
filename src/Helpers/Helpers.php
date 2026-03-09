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
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Class Helpers.
 */
class Helpers
{
	/**
	 * Registers all built-in helpers with Blate.
	 *
	 * Called once at bootstrap time (src/bootstrap.php).
	 */
	public static function register(): void
	{
		// Misc / Logic
		Blate::registerHelper('has', [static::class, 'has']);
		Blate::registerHelper('type', [static::class, 'type']);
		Blate::registerHelper('cast', [static::class, 'cast']);
		Blate::registerHelper('default', [static::class, '_default']);
		Blate::registerHelper('if', [static::class, '_if']);

		// String
		Blate::registerHelper('escape', [static::class, 'escape']);
		Blate::registerHelper('escapeHtml', [static::class, 'escapeHtml']);
		Blate::registerHelper('attrs', [static::class, 'attrs']);
		Blate::registerHelper('quote', [static::class, 'quote']);
		Blate::registerHelper('unquote', [static::class, 'unquote']);
		Blate::registerHelper('concat', [static::class, 'concat']);
		Blate::registerHelper('upper', [static::class, 'upper']);
		Blate::registerHelper('lower', [static::class, 'lower']);
		Blate::registerHelper('ucfirst', [static::class, 'ucfirst']);
		Blate::registerHelper('trim', [static::class, 'trim']);
		Blate::registerHelper('replace', [static::class, 'replace']);
		Blate::registerHelper('split', [static::class, 'split']);
		Blate::registerHelper('substr', [static::class, 'substr']);
		Blate::registerHelper('truncate', [static::class, 'truncate']);
		Blate::registerHelper('nl2br', [static::class, 'nl2br']);
		Blate::registerHelper('url', [static::class, 'url']);
		Blate::registerHelper('json', [static::class, 'json']);

		// Array
		Blate::registerHelper('join', [static::class, 'join']);
		Blate::registerHelper('keys', [static::class, 'keys']);
		Blate::registerHelper('values', [static::class, 'values']);
		Blate::registerHelper('length', [static::class, 'length']);
		Blate::registerHelper('first', [static::class, 'first']);
		Blate::registerHelper('last', [static::class, 'last']);
		Blate::registerHelper('slice', [static::class, 'slice']);
		Blate::registerHelper('reverse', [static::class, 'reverse']);
		Blate::registerHelper('unique', [static::class, 'unique']);
		Blate::registerHelper('flatten', [static::class, 'flatten']);
		Blate::registerHelper('chunk', [static::class, 'chunk']);
		Blate::registerHelper('merge', [static::class, 'merge']);

		// Number
		Blate::registerHelper('number', [static::class, 'number']);
		Blate::registerHelper('abs', [static::class, 'abs']);
		Blate::registerHelper('round', [static::class, 'round']);
		Blate::registerHelper('clamp', [static::class, 'clamp']);

		// Date
		Blate::registerHelper('now', [static::class, 'now']);
		Blate::registerHelper('date', [static::class, 'date']);
	}

	// =========================================================================
	// Misc / Logic
	// =========================================================================

	/**
	 * Check if a property exists in a target.
	 *
	 * @param mixed      $target The target
	 * @param int|string $prop   The property name
	 *
	 * @return bool
	 */
	public static function has(mixed $target, int|string $prop): bool
	{
		return SimpleChain::has($target, $prop, $val);
	}

	/**
	 * Check or return the type of a value.
	 *
	 * When $type is omitted the debug type string is returned.
	 * When $type is provided a boolean is returned.
	 *
	 * Supported types: null, bool, int, float, string, array, object,
	 * numeric, and any fully-qualified class/interface name.
	 *
	 * @param mixed       $value The value to inspect
	 * @param null|string $type  The expected type
	 *
	 * @return bool|string
	 */
	public static function type(mixed $value, ?string $type = null): bool|string
	{
		if (null === $type) {
			return \get_debug_type($value);
		}

		if ('numeric' === $type) {
			return \is_numeric($value);
		}

		return \get_debug_type($value) === $type;
	}

	/**
	 * Cast a value to a type.
	 *
	 * Supported types: int, float, string, bool and array.
	 *
	 * @param mixed  $value The value to cast
	 * @param string $type  The target type
	 *
	 * @return mixed
	 */
	public static function cast(mixed $value, string $type): mixed
	{
		return match ($type) {
			'int'    => (int) $value,
			'float'  => (float) $value,
			'string' => (string) $value,
			'bool'   => (bool) $value,
			'array'  => (array) $value,
			default  => throw new InvalidArgumentException("Unsupported type: {$type}"),
		};
	}

	/**
	 * Return $value if it is neither null nor an empty string, otherwise return $fallback.
	 *
	 * @param mixed $value    The value to test
	 * @param mixed $fallback The fallback value
	 *
	 * @return mixed
	 */
	public static function _default(mixed $value, mixed $fallback): mixed
	{
		return (null === $value || '' === $value) ? $fallback : $value;
	}

	/**
	 * Return $a if $condition is true, otherwise return $b.
	 *
	 * This is a helper for ternary operations in templates,
	 * since the ternary operator is not supported in Blate expressions.
	 *
	 * @param bool  $condition The condition
	 * @param mixed $a         The value returned when true
	 * @param mixed $b         The value returned when false
	 *
	 * @return mixed
	 */
	public static function _if(bool $condition, mixed $a, mixed $b): mixed
	{
		return $condition ? $a : $b;
	}

	// =========================================================================
	// String
	// =========================================================================

	/**
	 * Escape a value for use in HTML text and attributes (htmlspecialchars).
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
	 * Escape a value using full HTML entities (htmlentities).
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
	 * Generate an HTML attributes string from an associative array.
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
	 * Wrap a string in single quotes, escaping internal single quotes.
	 *
	 * @param string $str The string to quote
	 *
	 * @return string
	 */
	public static function quote(string $str): string
	{
		// return '"' . \str_replace(['"', '$'], ['\\"', '\\$'], $str) . '"';
		return '\'' . \str_replace('\'', '\\\'', $str) . '\'';
	}

	/**
	 * Remove surrounding single or double quotes from a string.
	 *
	 * @param string $str The string to unquote
	 *
	 * @return string
	 */
	public static function unquote(string $str): string
	{
		if (\str_starts_with($str, '\'') && \str_ends_with($str, '\'')) {
			return \str_replace('\\\'', '\'', \substr($str, 1, -1));
		}

		if (\str_starts_with($str, '"') && \str_ends_with($str, '"')) {
			return \str_replace('\"', '"', \substr($str, 1, -1));
		}

		return $str;
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
	 * Convert a string to uppercase (multibyte-safe).
	 *
	 * @param string $str The input string
	 *
	 * @return string
	 */
	public static function upper(string $str): string
	{
		return \mb_strtoupper($str);
	}

	/**
	 * Convert a string to lowercase (multibyte-safe).
	 *
	 * @param string $str The input string
	 *
	 * @return string
	 */
	public static function lower(string $str): string
	{
		return \mb_strtolower($str);
	}

	/**
	 * Uppercase the first character of a string (multibyte-safe).
	 *
	 * @param string $str The input string
	 *
	 * @return string
	 */
	public static function ucfirst(string $str): string
	{
		if ('' === $str) {
			return $str;
		}

		return \mb_strtoupper(\mb_substr($str, 0, 1)) . \mb_substr($str, 1);
	}

	/**
	 * Strip characters from the beginning and end of a string.
	 *
	 * @param string $str   The input string
	 * @param string $chars Characters to strip (default: whitespace)
	 *
	 * @return string
	 */
	public static function trim(string $str, string $chars = " \t\n\r\0\x0B"): string
	{
		return \trim($str, $chars);
	}

	/**
	 * Replace occurrences of a search value with a replacement.
	 *
	 * @param string       $str     The subject string
	 * @param array|string $search  The value(s) to search for
	 * @param array|string $replace The replacement value(s)
	 *
	 * @return string
	 */
	public static function replace(string $str, array|string $search, array|string $replace): string
	{
		return (string) \str_replace($search, $replace, $str);
	}

	/**
	 * Split a string by a separator.
	 *
	 * When $separator is an empty string, the string is split into individual
	 * multibyte characters.
	 *
	 * @param string $str       The input string
	 * @param string $separator The boundary string
	 * @param int    $limit     Maximum number of parts (default: all)
	 *
	 * @return array<string>
	 */
	public static function split(string $str, string $separator, int $limit = \PHP_INT_MAX): array
	{
		if ('' === $separator) {
			return \mb_str_split($str);
		}

		return \explode($separator, $str, $limit);
	}

	/**
	 * Return a portion of a string (multibyte-safe).
	 *
	 * @param string   $str    The input string
	 * @param int      $start  The starting index
	 * @param null|int $length The length (optional, null = to end of string)
	 *
	 * @return string
	 */
	public static function substr(string $str, int $start, ?int $length = null): string
	{
		if (null === $length) {
			return \mb_substr($str, $start);
		}

		return \mb_substr($str, $start, $length);
	}

	/**
	 * Truncate a string to a maximum length, appending a suffix when cut.
	 *
	 * @param string $str    The input string
	 * @param int    $length The maximum number of characters
	 * @param string $suffix The suffix to append when truncated (default: '...')
	 *
	 * @return string
	 */
	public static function truncate(string $str, int $length, string $suffix = '...'): string
	{
		if (\mb_strlen($str) <= $length) {
			return $str;
		}

		return \mb_substr($str, 0, $length) . $suffix;
	}

	/**
	 * Insert HTML line breaks before all newlines in a string.
	 *
	 * @param string $str The input string
	 *
	 * @return string
	 */
	public static function nl2br(string $str): string
	{
		return \nl2br($str);
	}

	/**
	 * URL-encode a string using RFC 3986 encoding.
	 *
	 * @param string $str The string to encode
	 *
	 * @return string
	 */
	public static function url(string $str): string
	{
		return \rawurlencode($str);
	}

	/**
	 * Encode a value as a JSON string.
	 *
	 * @param mixed $value The value to encode
	 * @param int   $flags JSON encoding flags (default: 0)
	 *
	 * @return string
	 */
	public static function json(mixed $value, int $flags = 0): string
	{
		return (string) \json_encode($value, $flags);
	}

	// =========================================================================
	// Array
	// =========================================================================

	/**
	 * Join an array into a string with a glue.
	 *
	 * @param array<string> $data The array of strings
	 * @param string        $glue The glue (default: '')
	 *
	 * @return string
	 */
	public static function join(array $data, string $glue = ''): string
	{
		return \implode($glue, $data);
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
	 * Get the values of an array, re-indexed from zero.
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
	 * Get the length of a string, array, or countable value.
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
	 * Get the first element of an array.
	 *
	 * Returns null for an empty array.
	 *
	 * @param array $data The input array
	 *
	 * @return mixed
	 */
	public static function first(array $data): mixed
	{
		$key = \array_key_first($data);

		return null !== $key ? $data[$key] : null;
	}

	/**
	 * Get the last element of an array.
	 *
	 * Returns null for an empty array.
	 *
	 * @param array $data The input array
	 *
	 * @return mixed
	 */
	public static function last(array $data): mixed
	{
		$key = \array_key_last($data);

		return null !== $key ? $data[$key] : null;
	}

	/**
	 * Extract a slice of an array.
	 *
	 * @param array    $data   The input array
	 * @param int      $offset The starting offset
	 * @param null|int $length The number of elements to extract (optional)
	 *
	 * @return array
	 */
	public static function slice(array $data, int $offset, ?int $length = null): array
	{
		return \array_slice($data, $offset, $length);
	}

	/**
	 * Reverse an array or string.
	 *
	 * String reversal is multibyte-safe (character-level, not byte-level).
	 *
	 * @param array|string $value The value to reverse
	 *
	 * @return array|string
	 */
	public static function reverse(array|string $value): array|string
	{
		if (\is_string($value)) {
			return \implode('', \array_reverse(\mb_str_split($value)));
		}

		return \array_reverse($value);
	}

	/**
	 * Remove duplicate values from an array.
	 *
	 * @param array $data The input array
	 *
	 * @return array
	 */
	public static function unique(array $data): array
	{
		return \array_unique($data);
	}

	/**
	 * Flatten an array by one level.
	 *
	 * @param array $data The input array
	 *
	 * @return array
	 */
	public static function flatten(array $data): array
	{
		$result = [];

		foreach ($data as $item) {
			if (\is_array($item)) {
				foreach ($item as $v) {
					$result[] = $v;
				}
			} else {
				$result[] = $item;
			}
		}

		return $result;
	}

	/**
	 * Split an array into chunks of a given size.
	 *
	 * @param array $data The input array
	 * @param int   $size The chunk size
	 *
	 * @return array
	 */
	public static function chunk(array $data, int $size): array
	{
		return \array_chunk($data, $size);
	}

	/**
	 * Merge two or more arrays.
	 *
	 * @param array ...$arrays The arrays to merge
	 *
	 * @return array
	 */
	public static function merge(array ...$arrays): array
	{
		return \array_merge(...\array_values($arrays));
	}

	// =========================================================================
	// Number
	// =========================================================================

	/**
	 * Format a number with grouped thousands and decimal point.
	 *
	 * @param float|int $value         The number to format
	 * @param int       $decimals      The number of decimal digits (default: 2)
	 * @param string    $dec_point     The decimal point character (default: '.')
	 * @param string    $thousands_sep The thousands separator (default: ',')
	 *
	 * @return string
	 */
	public static function number(float|int $value, int $decimals = 2, string $dec_point = '.', string $thousands_sep = ','): string
	{
		return \number_format((float) $value, $decimals, $dec_point, $thousands_sep);
	}

	/**
	 * Return the absolute value of a number.
	 *
	 * @param float|int $value The number
	 *
	 * @return float|int
	 */
	public static function abs(float|int $value): float|int
	{
		return \abs($value);
	}

	/**
	 * Round a number to a given precision.
	 *
	 * @param float|int $value     The number to round
	 * @param int       $precision The number of decimal digits (default: 0)
	 *
	 * @return float
	 */
	public static function round(float|int $value, int $precision = 0): float
	{
		return \round((float) $value, $precision);
	}

	/**
	 * Constrain a number to the closed interval [$min, $max].
	 *
	 * @param float|int $value The number to constrain
	 * @param float|int $min   The lower bound
	 * @param float|int $max   The upper bound
	 *
	 * @return float|int
	 */
	public static function clamp(float|int $value, float|int $min, float|int $max): float|int
	{
		return \max($min, \min($max, $value));
	}

	// =========================================================================
	// Date
	// =========================================================================

	/**
	 * Return the current Unix timestamp.
	 *
	 * @param bool $microtime Whether to include microseconds (default: false)
	 *
	 * @return float|int
	 */
	public static function now(bool $microtime = false): float|int
	{
		if ($microtime) {
			return \microtime(true);
		}

		return \time();
	}

	/**
	 * Format a date.
	 *
	 * @param DateTimeInterface|float|int|string $date     The date to format
	 * @param string                             $format   The date format (default: 'Y-m-d H:i:s')
	 * @param null|string                        $timezone The timezone (default: null, meaning the default timezone)
	 *
	 * @return string
	 */
	public static function date(DateTimeInterface|float|int|string $date, string $format = 'Y-m-d H:i:s', ?string $timezone = null): string
	{
		if (!$date instanceof DateTimeInterface) {
			$date = new DateTimeImmutable('@' . (\is_numeric($date) ? (int) $date : \strtotime($date)));
		}

		if (null !== $timezone) {
			$immutable = $date instanceof DateTimeImmutable ? $date : DateTimeImmutable::createFromInterface($date);
			$date      = $immutable->setTimezone(new DateTimeZone($timezone));
		}

		return $date->format($format);
	}
}
