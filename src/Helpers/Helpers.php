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
use PHPUtils\Store\Store;

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
		Blate::registerHelper('startsWith', [static::class, 'startsWith']);
		Blate::registerHelper('endsWith', [static::class, 'endsWith']);
		Blate::registerHelper('contains', [static::class, 'contains']);
		Blate::registerHelper('repeat', [static::class, 'repeat']);
		Blate::registerHelper('pad', [static::class, 'pad']);
		Blate::registerHelper('sprintf', [static::class, '_sprintf']);
		Blate::registerHelper('stripTags', [static::class, 'stripTags']);

		// Array
		Blate::registerHelper('join', [static::class, 'join']);
		Blate::registerHelper('keys', [static::class, 'keys']);
		Blate::registerHelper('values', [static::class, 'values']);
		Blate::registerHelper('length', [static::class, 'length']);
		Blate::registerHelper('count', [static::class, 'length']);
		Blate::registerHelper('first', [static::class, 'first']);
		Blate::registerHelper('last', [static::class, 'last']);
		Blate::registerHelper('slice', [static::class, 'slice']);
		Blate::registerHelper('reverse', [static::class, 'reverse']);
		Blate::registerHelper('unique', [static::class, 'unique']);
		Blate::registerHelper('flatten', [static::class, 'flatten']);
		Blate::registerHelper('chunk', [static::class, 'chunk']);
		Blate::registerHelper('merge', [static::class, 'merge']);
		Blate::registerHelper('sort', [static::class, 'sort']);
		Blate::registerHelper('sortBy', [static::class, 'sortBy']);
		Blate::registerHelper('range', [static::class, '_range']);
		Blate::registerHelper('min', [static::class, '_min']);
		Blate::registerHelper('max', [static::class, '_max']);
		Blate::registerHelper('sum', [static::class, 'sum']);
		Blate::registerHelper('avg', [static::class, 'avg']);
		Blate::registerHelper('shuffle', [static::class, 'shuffle']);
		Blate::registerHelper('filter', [static::class, 'filter']);
		Blate::registerHelper('map', [static::class, 'map']);
		Blate::registerHelper('list', [static::class, '_list']);
		Blate::registerHelper('store', [static::class, 'store']);

		// Number
		Blate::registerHelper('number', [static::class, 'number']);
		Blate::registerHelper('abs', [static::class, 'abs']);
		Blate::registerHelper('round', [static::class, 'round']);
		Blate::registerHelper('clamp', [static::class, 'clamp']);
		Blate::registerHelper('floor', [static::class, 'floor']);
		Blate::registerHelper('ceil', [static::class, 'ceil']);
		Blate::registerHelper('random', [static::class, 'random']);

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
	 * Escape a value for safe HTML output (htmlspecialchars).
	 *
	 * Encodes <, >, &, ", ' only. This is correct and sufficient for XSS
	 * prevention in UTF-8 documents without corrupting multibyte characters.
	 *
	 * @param mixed $untrusted The value to escape
	 *
	 * @return string
	 */
	public static function escapeHtml(mixed $untrusted): string
	{
		return \htmlspecialchars((string) $untrusted, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
	}

	/**
	 * Generate an HTML attributes string from an associative array.
	 *
	 * Default mode ($raw = false) -- HTML boolean attribute semantics:
	 *   - null / false / '' : attribute is omitted entirely (absent = false in HTML)
	 *   - true              : standalone boolean attribute, e.g. disabled, checked
	 *   - other             : attr="escaped-value"
	 *
	 * Raw mode ($raw = true) -- for data-* / ARIA / custom attributes where
	 * false and null carry meaningful string values, not absent-flag semantics:
	 *   - null  -> attr=""
	 *   - false -> attr="false"
	 *   - true  -> attr="true" (NOT a standalone flag)
	 *   - other -> attr="escaped-value"
	 *
	 * Example (template syntax, pass 1 for raw mode since boolean literals
	 * resolve as variable names in expressions):
	 *   {= attrs(boolAttrs)}        -> disabled id="btn"
	 *   {= attrs(dataAttrs, 1)}     -> data-active="false" data-count=""
	 *
	 * @param array<string, null|bool|int|string> $data
	 * @param bool|int                            $raw  When truthy, disables HTML boolean
	 *                                                  semantics: false -> "false",
	 *                                                  null -> "", true -> "true".
	 *                                                  Pass 1 from templates (boolean
	 *                                                  literals resolve as variable names).
	 *
	 * @return string
	 */
	public static function attrs(array $data, bool|int $raw = false): string
	{
		$out = '';

		foreach ($data as $raw_attr => $val) {
			$attr = self::escape((string) $raw_attr);

			if ($raw) {
				// Raw coercion: emit every attribute, map PHP booleans/null to
				// their string representation. false !== null so they differ.
				$str_val = match (true) {
					null === $val  => '',
					false === $val => 'false',
					true === $val  => 'true',
					default        => (string) $val,
				};
				$attr .= '="' . self::escape($str_val) . '"';
			} else {
				// HTML boolean attribute semantics: presence = true, absence = false.
				// Omit the attribute entirely when value is null, '' or false.
				if (null === $val || '' === $val || false === $val) {
					continue;
				}

				if (true !== $val) {
					$attr .= '="' . self::escape($val) . '"';
				}
				// bool true -> standalone attribute (no value suffix)
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
	 * The default flags include JSON_HEX_TAG and JSON_HEX_AMP so that
	 * the output is safe to embed inside HTML <script> blocks.
	 *
	 * @param mixed $value  The value to encode
	 * @param int   $flags  JSON encoding flags (default: JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR)
	 * @param bool  $pretty When true, pretty-print the JSON output
	 *
	 * @return string
	 */
	public static function json(mixed $value, int $flags = \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_THROW_ON_ERROR, bool $pretty = false): string
	{
		if ($pretty) {
			$flags |= \JSON_PRETTY_PRINT;
		}

		return (string) \json_encode($value, $flags);
	}

	/**
	 * Check if a string starts with a given prefix.
	 *
	 * @param string $str    The input string
	 * @param string $prefix The prefix to check
	 *
	 * @return bool
	 */
	public static function startsWith(string $str, string $prefix): bool
	{
		return \str_starts_with($str, $prefix);
	}

	/**
	 * Check if a string ends with a given suffix.
	 *
	 * @param string $str    The input string
	 * @param string $suffix The suffix to check
	 *
	 * @return bool
	 */
	public static function endsWith(string $str, string $suffix): bool
	{
		return \str_ends_with($str, $suffix);
	}

	/**
	 * Check if a string or array contains a given value.
	 *
	 * For strings: checks if $needle appears as a substring (case-sensitive).
	 * For arrays: checks strict membership with in_array.
	 *
	 * @param array|string $haystack The string or array to search
	 * @param mixed        $needle   The value to search for
	 *
	 * @return bool
	 */
	public static function contains(array|string $haystack, mixed $needle): bool
	{
		if (\is_string($haystack)) {
			return \str_contains($haystack, (string) $needle);
		}

		return \in_array($needle, $haystack, true);
	}

	/**
	 * Repeat a string a given number of times.
	 *
	 * @param string $str   The string to repeat
	 * @param int    $times The number of repetitions
	 *
	 * @return string
	 */
	public static function repeat(string $str, int $times): string
	{
		return \str_repeat($str, $times);
	}

	/**
	 * Pad a string to a certain length.
	 *
	 * @param string $str     The input string
	 * @param int    $length  The target length
	 * @param string $pad_str The padding character(s) (default: ' ')
	 * @param string $side    Where to apply padding: 'right' (default), 'left', or 'both'
	 *
	 * @return string
	 */
	public static function pad(string $str, int $length, string $pad_str = ' ', string $side = 'right'): string
	{
		$type = match ($side) {
			'left'  => \STR_PAD_LEFT,
			'both'  => \STR_PAD_BOTH,
			default => \STR_PAD_RIGHT,
		};

		return \str_pad($str, $length, $pad_str, $type);
	}

	/**
	 * Format a string using sprintf-style placeholders.
	 *
	 * @param string $format  The format string
	 * @param mixed  ...$args The values to insert
	 *
	 * @return string
	 */
	public static function _sprintf(string $format, mixed ...$args): string
	{
		return \sprintf($format, ...$args);
	}

	/**
	 * Strip HTML and PHP tags from a string.
	 *
	 * @param string       $str     The input string
	 * @param array|string $allowed Tags to keep, e.g. '<p><br>' or ['p', 'br'] (optional)
	 *
	 * @return string
	 */
	public static function stripTags(string $str, array|string $allowed = ''): string
	{
		return \strip_tags($str, $allowed);
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

	/**
	 * Sort an array in ascending order and re-index from zero.
	 *
	 * @param array $data The input array
	 *
	 * @return array
	 */
	public static function sort(array $data): array
	{
		\sort($data);

		return $data;
	}

	/**
	 * Sort an array of associative arrays by the value of a given key.
	 *
	 * @param array  $data The input array
	 * @param string $key  The key to sort by
	 *
	 * @return array
	 */
	public static function sortBy(array $data, string $key): array
	{
		\usort($data, static fn ($a, $b) => ($a[$key] ?? null) <=> ($b[$key] ?? null));

		return $data;
	}

	/**
	 * Create an array of integers (or floats) in the given range.
	 *
	 * @param float|int $start The first value
	 * @param float|int $end   The last value (inclusive)
	 * @param float|int $step  The increment between values (default: 1)
	 *
	 * @return array
	 */
	public static function _range(float|int $start, float|int $end, float|int $step = 1): array
	{
		return \range($start, $end, $step);
	}

	/**
	 * Return the minimum value in an array.
	 *
	 * @param array $data The input array (must not be empty)
	 *
	 * @return mixed
	 */
	public static function _min(array $data): mixed
	{
		return \min($data);
	}

	/**
	 * Return the maximum value in an array.
	 *
	 * @param array $data The input array (must not be empty)
	 *
	 * @return mixed
	 */
	public static function _max(array $data): mixed
	{
		return \max($data);
	}

	/**
	 * Return the sum of all values in an array.
	 *
	 * @param array $data The input array
	 *
	 * @return float|int
	 */
	public static function sum(array $data): float|int
	{
		return \array_sum($data);
	}

	/**
	 * Return the arithmetic mean of all values in an array.
	 *
	 * Returns 0.0 for an empty array.
	 *
	 * @param array $data The input array
	 *
	 * @return float
	 */
	public static function avg(array $data): float
	{
		$count = \count($data);

		return $count > 0 ? (float) \array_sum($data) / (float) $count : 0.0;
	}

	/**
	 * Shuffle an array and return it.
	 *
	 * The original array is not modified; a shuffled copy is returned.
	 *
	 * @param array $data The input array
	 *
	 * @return array
	 */
	public static function shuffle(array $data): array
	{
		\shuffle($data);

		return $data;
	}

	/**
	 * Filter an array by value.
	 *
	 * When $value is omitted, falsy elements are removed (like array_filter with no callback).
	 * When $value is provided, only elements strictly equal to $value are kept.
	 * The returned array is re-indexed from zero.
	 *
	 * @param array $data  The input array
	 * @param mixed $value The value to match (optional)
	 *
	 * @return array
	 */
	public static function filter(array $data, mixed $value = null): array
	{
		if (\func_num_args() < 2) {
			return \array_values(\array_filter($data));
		}

		return \array_values(\array_filter($data, static fn ($item) => $item === $value));
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

	/**
	 * Round a number down to the nearest integer.
	 *
	 * @param float|int $value The number to floor
	 *
	 * @return float
	 */
	public static function floor(float|int $value): float
	{
		return \floor((float) $value);
	}

	/**
	 * Round a number up to the nearest integer.
	 *
	 * @param float|int $value The number to ceil
	 *
	 * @return float
	 */
	public static function ceil(float|int $value): float
	{
		return \ceil((float) $value);
	}

	/**
	 * Return a cryptographically secure random integer.
	 *
	 * When called with no arguments, returns a random integer between 0 and PHP_INT_MAX.
	 *
	 * @param int $min The lower bound (inclusive, default: 0)
	 * @param int $max The upper bound (inclusive, default: PHP_INT_MAX)
	 *
	 * @return int
	 */
	public static function random(int $min = 0, int $max = \PHP_INT_MAX): int
	{
		return \random_int($min, $max);
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
			$date = new DateTimeImmutable('@' . (\is_numeric($date) ? (int) $date : (int) \strtotime($date)));
		}

		if (null !== $timezone) {
			$immutable = $date instanceof DateTimeImmutable ? $date : DateTimeImmutable::createFromInterface($date);
			$date      = $immutable->setTimezone(new DateTimeZone($timezone));
		}

		return $date->format($format);
	}

	/**
	 * Build an associative array from alternating key/value pairs.
	 *
	 * Keys are DotPath expressions, so 'foo.bar' nests the value.
	 *
	 * @param mixed ...$pairs Alternating keys and values: map(k1, v1, k2, v2, ...)
	 *
	 * @return array<mixed, mixed>
	 */
	public static function map(mixed ...$pairs): array
	{
		$store = new Store([]);
		$count = \count($pairs);

		for ($i = 0; $i + 1 < $count; $i += 2) {
			$store->set((string) $pairs[$i], $pairs[$i + 1]);
		}

		return (array) $store->getData();
	}

	/**
	 * Build an indexed array from the given values.
	 *
	 * @param mixed ...$values The values
	 *
	 * @return list<mixed>
	 */
	public static function _list(mixed ...$values): array
	{
		return \array_values($values);
	}

	/**
	 * Wrap an array in a mutable Store for chained .set() calls.
	 *
	 * @param array<mixed, mixed> $arr The initial data
	 *
	 * @return Store
	 */
	public static function store(array $arr = []): Store
	{
		return new Store($arr);
	}
}
