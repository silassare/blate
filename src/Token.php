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
use PHPUtils\Traits\ArrayCapableTrait;
use ReflectionClass;

/**
 * Class Token.
 */
class Token implements TokenInterface
{
	use ArrayCapableTrait;

	public const T_UNKNOWN  = 1;

	public const T_RAW_DATA = 2;

	public const T_HASH = 3;

	public const T_COLON = 4;

	public const T_DNUMBER = 5;

	public const T_WHITESPACE = 6;

	public const T_NAME = 7;

	public const T_DOT = 8;

	public const T_STRING = 9;

	public const T_OPERATOR = 10;

	public const T_COMMA = 11;

	public const T_COND_AND = 12;

	public const T_COND_OR = 13;

	public const T_EQ = 14;

	public const T_NOT_EQ = 15;

	public const T_NOT = 16;

	public const T_LT_OR_LT_EQ = 17;

	public const T_GT_OR_GT_EQ = 18;

	public const T_PAREN_OPEN = 19;

	public const T_PAREN_CLOSE = 20;

	public const T_SQUARE_BRACKET_OPEN = 21;

	public const T_SQUARE_BRACKET_CLOSE = 22;

	public const T_CURLY_BRACKET_OPEN = 23;

	public const T_CURLY_BRACKET_CLOSE = 24;

	public const T_TAG_OPEN = 25;

	public const T_TAG_CLOSE = 26;

	public const T_PIPE = 27;

	public const T_AT = 28;

	public const T_TILDE = 29;

	public const ATTR_IN_TREE = 'in_tree';

	public const ATTR_ACTIVE_CHAIN = 'active_chain';

	public const ATTR_EXTENDED_BLATE_VAR    = 'extended_blate_var';
	public const ATTR_EXTENDED_INSTANCE_VAR = 'extended_instance_var';
	public const ATTR_EXTENDED_CONTEXT_VAR  = 'extended_context_var';

	/**
	 * @var TokenInterface[]
	 */
	protected array $children = [];

	protected array $attributes = [];

	private static int $counter = 0;
	private string $ref;

	/**
	 * Token constructor.
	 *
	 * @param ChunkInterface      $chunk
	 * @param int                 $type
	 * @param null|TokenInterface $parent
	 */
	public function __construct(protected ChunkInterface $chunk, protected int $type, protected ?TokenInterface $parent = null)
	{
		$location  = $chunk->getLocation();
		$this->ref = 't' . self::$counter++ . '_l' . $location['line'] . '_i' . $location['index'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function __toString(): string
	{
		return \sprintf('"%s" (%s) %s.', $this->chunk->getValue(), self::getTypeName($this->type), $this->chunk->getLocationString());
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRef(): string
	{
		return $this->ref;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getType(): int
	{
		return $this->type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getChunk(): ChunkInterface
	{
		return $this->chunk;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getValue(): string
	{
		return $this->chunk->getValue();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isGroupOpener(): bool
	{
		return self::T_TAG_OPEN === $this->type
			   || self::T_PAREN_OPEN === $this->type
			   || self::T_SQUARE_BRACKET_OPEN === $this->type
			   || self::T_CURLY_BRACKET_OPEN === $this->type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isGroupCloser(): bool
	{
		return self::T_TAG_CLOSE === $this->type
			   || self::T_PAREN_CLOSE === $this->type
			   || self::T_SQUARE_BRACKET_CLOSE === $this->type
			   || self::T_CURLY_BRACKET_CLOSE === $this->type;
	}

	/**
	 * {@inheritDoc}
	 */
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

	/**
	 * {@inheritDoc}
	 */
	public function isComparator(): bool
	{
		return match ($this->type) {
			self::T_EQ, self::T_NOT_EQ, self::T_GT_OR_GT_EQ, self::T_LT_OR_LT_EQ => true,
			default => false,
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function isOperator(): bool
	{
		return match ($this->type) {
			self::T_OPERATOR, self::T_NOT, self::T_COMMA => true,
			default => false,
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function isLogicalCondition(): bool
	{
		return match ($this->type) {
			self::T_COND_AND, self::T_COND_OR => true,
			default => false,
		};
	}

	/**
	 * Converts type constant to name.
	 */
	public static function getTypeName(int $type): string
	{
		/** @var array<int, string> $types_names */
		static $types_names =  [];

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

	/**
	 * {@inheritDoc}
	 */
	public function getChildren(): array
	{
		return $this->children;
	}

	/**
	 * {@inheritDoc}
	 */
	public function addChild(TokenInterface $token): static
	{
		if (!$this->isGroupOpener()) {
			throw new LogicException(Message::CANT_ADD_CHILD_NOT_A_GROUP);
		}

		$this->children[] = $token;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getParent(): ?self
	{
		return $this->parent;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setParent(?TokenInterface $parent): static
	{
		$this->parent = $parent;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAttribute(string $name): mixed
	{
		return $this->attributes[$name] ?? null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setAttribute(string $name, mixed $value): static
	{
		$this->attributes[$name] = $value;

		return $this;
	}

	/**
	 * JSON magic method.
	 */
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
