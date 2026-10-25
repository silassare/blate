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

use Blate\Interfaces\ChunkInterface;
use Blate\Interfaces\TokenInterface;
use LogicException;
use Override;
use PHPUtils\Traits\ArrayCapableTrait;
use ReflectionClass;

/**
 * Class Token.
 *
 * Represents a single token produced by the Lexer.
 *
 * Each token carries:
 *   - a type constant (T_NAME, T_OPERATOR, T_TAG_OPEN, ...)
 *   - a StringChunk (value + source location)
 *   - optional children (for group-opener tokens such as T_PAREN_OPEN)
 *   - optional parent token (set when the token is inside a group)
 *   - a key-value attribute bag used by the expression compiler for
 *     per-scope active-chain tracking (ATTR_ACTIVE_CHAIN) and by block
 *     handlers for temporary storage (ATTR_EXTENDED_*)
 *
 * Every token gets a unique $ref string derived from a global counter and
 * its source location, used as an identity key in TypedStack code buffers.
 */
class Token implements TokenInterface
{
	use ArrayCapableTrait;

	public const int T_UNKNOWN  = 1;

	public const int T_RAW_DATA = 2;

	public const int T_HASH = 3;

	public const int T_COLON = 4;

	public const int T_DNUMBER = 5;

	public const int T_WHITESPACE = 6;

	public const int T_NAME = 7;

	public const int T_DOT = 8;

	public const int T_STRING = 9;

	public const int T_OPERATOR = 10;

	public const int T_COMMA = 11;

	public const int T_COND_AND = 12;

	public const int T_COND_OR = 13;

	public const int T_EQ = 14;

	public const int T_NOT_EQ = 15;

	public const int T_NOT = 16;

	public const int T_LT_OR_LT_EQ = 17;

	public const int T_GT_OR_GT_EQ = 18;

	public const int T_PAREN_OPEN = 19;

	public const int T_PAREN_CLOSE = 20;

	public const int T_SQUARE_BRACKET_OPEN = 21;

	public const int T_SQUARE_BRACKET_CLOSE = 22;

	public const int T_CURLY_BRACKET_OPEN = 23;

	public const int T_CURLY_BRACKET_CLOSE = 24;

	public const int T_TAG_OPEN = 25;

	public const int T_TAG_CLOSE = 26;

	public const int T_PIPE = 27;

	public const int T_AT = 28;

	public const int T_TILDE = 29;

	public const int T_NULL_COALESCE = 30;

	public const int T_SEMICOLON = 31;

	public const string ATTR_IN_TREE = 'in_tree';

	public const string ATTR_ACTIVE_CHAIN = 'active_chain';

	public const string ATTR_EXTENDED_BLATE_VAR    = 'extended_blate_var';
	public const string ATTR_EXTENDED_INSTANCE_VAR = 'extended_instance_var';
	public const string ATTR_EXTENDED_CONTEXT_VAR  = 'extended_context_var';

	/**
	 * @var TokenInterface[]
	 */
	protected array $children = [];

	protected array $attributes = [];

	private static int $counter = 0;
	private string $ref;

	/**
	 * Token constructor.
	 */
	public function __construct(protected ChunkInterface $chunk, protected int $type, protected ?TokenInterface $parent = null)
	{
		$location  = $chunk->getLocation();
		$this->ref = 't' . self::$counter++ . '_l' . $location['line'] . '_i' . $location['index'];
	}

	public function __toString(): string
	{
		return \sprintf('"%s" (%s) %s.', $this->chunk->getValue(), self::getTypeName($this->type), $this->chunk->getLocationString());
	}

	#[Override]
	public function getRef(): string
	{
		return $this->ref;
	}

	#[Override]
	public function getType(): int
	{
		return $this->type;
	}

	#[Override]
	public function getChunk(): ChunkInterface
	{
		return $this->chunk;
	}

	#[Override]
	public function getValue(): string
	{
		return $this->chunk->getValue();
	}

	#[Override]
	public function isGroupOpener(): bool
	{
		return self::T_TAG_OPEN === $this->type
			|| self::T_PAREN_OPEN === $this->type
			|| self::T_SQUARE_BRACKET_OPEN === $this->type
			|| self::T_CURLY_BRACKET_OPEN === $this->type;
	}

	#[Override]
	public function isGroupCloser(): bool
	{
		return self::T_TAG_CLOSE === $this->type
			|| self::T_PAREN_CLOSE === $this->type
			|| self::T_SQUARE_BRACKET_CLOSE === $this->type
			|| self::T_CURLY_BRACKET_CLOSE === $this->type;
	}

	#[Override]
	public function isGroupCloserOf(TokenInterface $opener): bool
	{
		return match ($opener->getType()) {
			self::T_TAG_OPEN            => self::T_TAG_CLOSE === $this->type,
			self::T_PAREN_OPEN          => self::T_PAREN_CLOSE === $this->type,
			self::T_SQUARE_BRACKET_OPEN => self::T_SQUARE_BRACKET_CLOSE === $this->type,
			self::T_CURLY_BRACKET_OPEN  => self::T_CURLY_BRACKET_CLOSE === $this->type,
			default                     => false,
		};
	}

	#[Override]
	public function isComparator(): bool
	{
		return match ($this->type) {
			self::T_EQ, self::T_NOT_EQ, self::T_GT_OR_GT_EQ, self::T_LT_OR_LT_EQ => true,
			default                                                              => false,
		};
	}

	#[Override]
	public function isOperator(): bool
	{
		return match ($this->type) {
			self::T_OPERATOR, self::T_NOT, self::T_COMMA => true,
			default                                      => false,
		};
	}

	#[Override]
	public function isLogicalCondition(): bool
	{
		return match ($this->type) {
			self::T_COND_AND, self::T_COND_OR, self::T_NULL_COALESCE => true,
			default                                                  => false,
		};
	}

	/**
	 * Converts type constant to name.
	 */
	public static function getTypeName(int $type): string
	{
		/** @var array<int, string> $types_names */
		static $types_names = [];

		if (empty($types_names)) {
			$o         = new ReflectionClass(static::class);
			$constants = $o->getConstants();

			foreach ($constants as $name => $value) {
				if (\str_starts_with($name, 'T_')) {
					$types_names[$value] = $name;
				}
			}
		}

		return $types_names[$type] ?? $types_names[self::T_UNKNOWN];
	}

	/**
	 * Converts type constant list to type name list.
	 */
	public static function getTypesNames(array $types): array
	{
		return \array_map([self::class, 'getTypeName'], $types);
	}

	#[Override]
	public function getChildren(): array
	{
		return $this->children;
	}

	#[Override]
	public function addChild(TokenInterface $token): static
	{
		if (!$this->isGroupOpener()) {
			throw new LogicException(Message::CANT_ADD_CHILD_NOT_A_GROUP);
		}

		$this->children[] = $token;

		return $this;
	}

	#[Override]
	public function getParent(): ?self
	{
		return $this->parent;
	}

	#[Override]
	public function setParent(?TokenInterface $parent): static
	{
		$this->parent = $parent;

		return $this;
	}

	#[Override]
	public function getAttribute(string $name): mixed
	{
		return $this->attributes[$name] ?? null;
	}

	#[Override]
	public function setAttribute(string $name, mixed $value): static
	{
		$this->attributes[$name] = $value;

		return $this;
	}

	/**
	 * JSON magic method.
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'ref'      => $this->ref,
			'type'     => self::getTypeName($this->type),
			'value'    => $this->chunk->getValue(),
			'location' => $this->chunk->getLocationString(),
			'children' => $this->children,
			// 'attributes' => $this->attributes,
		];
	}
}
