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

use Blate\Exceptions\BlateParserException;
use Blate\Interfaces\LexerInterface;
use Blate\Interfaces\TokenInterface;

/**
 * Class Lexer.
 *
 * Tokenizes raw template source text into a flat stack and a parenthesis/bracket
 * tree of Token objects.  The Lexer is stateful and cursor-based: callers
 * advance through tokens via move() and look around via lookForward()/lookBackward().
 *
 * Infinite-loop protection: if computeToken() is called twice without the
 * underlying StringReader cursor advancing, a BlateParserException is thrown.
 */
class Lexer implements LexerInterface
{
	/**
	 * @var TokenInterface[]
	 */
	protected array $stack = [];

	/**
	 * @var TokenInterface[]
	 */
	protected array $tree = [];

	protected int $token_cursor          = -1;
	protected bool $in_tag               = false;
	protected ?int $infinite_loop_cursor = null;

	protected array $states = [];

	/**
	 * @var TokenInterface[]
	 */
	protected array $open_stack = [];

	protected ?TokenInterface $current_group = null;

	protected StringReader $reader;

	protected array $digits;

	protected array $alpha_lower;

	protected array $alpha_upper;

	public function __construct(protected string $input, protected ?string $until = null)
	{
		$this->reader = new StringReader($this->input);

		$this->alpha_upper = \array_fill_keys(\range('A', 'Z'), 1);
		$this->alpha_lower = \array_fill_keys(\range('a', 'z'), 1);
		$this->digits      = \array_fill_keys(\range(0, 9), 1);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReader(): StringReader
	{
		return $this->reader;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCursor(): int
	{
		return $this->token_cursor;
	}

	/**
	 * {@inheritDoc}
	 */
	public function current(): ?TokenInterface
	{
		if (!isset($this->stack[$this->token_cursor])) {
			return $this->move();
		}

		return $this->stack[$this->token_cursor] ?? null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function move(): ?TokenInterface
	{
		if (!isset($this->stack[$this->token_cursor + 1])) {
			if ($token = $this->computeToken()) {
				$this->stack[++$this->token_cursor] = $this->addToTree($token);

				return $token;
			}

			return null;
		}

		return $this->stack[++$this->token_cursor];
	}

	/**
	 * {@inheritDoc}
	 */
	public function lookBackward(bool $ignore_whitespace = false): ?TokenInterface
	{
		if ($ignore_whitespace) {
			$prev = null;
			$c    = $this->token_cursor - 1;

			while ($c >= 0) {
				$prev = $this->stack[$c];
				if (Token::T_WHITESPACE !== $prev->getType()) {
					break;
				}

				--$c;
			}

			return $prev;
		}

		if ($this->token_cursor > 0) {
			return $this->stack[$this->token_cursor - 1];
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function lookForward(bool $ignore_whitespace = false): ?TokenInterface
	{
		$this->saveCurrentState();

		while ($token = $this->computeToken()) {
			if (!$ignore_whitespace || Token::T_WHITESPACE !== $token->getType()) {
				break;
			}
		}

		$this->restorePreviousState();

		return $token;
	}

	/**
	 * {@inheritDoc}
	 */
	public function saveCurrentState(): self
	{
		$this->states[] = [
			'token_cursor'         => $this->token_cursor,
			'infinite_loop_cursor' => $this->infinite_loop_cursor,
			'in_tag'               => $this->in_tag,
		];

		$this->reader->saveCurrentState();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function restorePreviousState(): self
	{
		$state = \array_pop($this->states);

		if (null === $state) {
			throw new BlateParserException(Message::NO_SAVED_STATE_CANT_RESTORE);
		}

		$this->reader->restorePreviousState();

		$this->token_cursor         = $state['token_cursor'];
		$this->infinite_loop_cursor = $state['infinite_loop_cursor'];
		$this->in_tag               = $state['in_tag'];

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException
	 */
	public function tokenize(): array
	{
		while (true) {
			if (!$this->move()) {
				break;
			}
		}

		return $this->tree;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTokensTree(): array
	{
		return $this->tree;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException
	 */
	public function nextIs(?int $type = null, ?string $value = null, bool $ignore_whitespace = false): TokenInterface
	{
		while ($token = $this->move()) {
			if (!$ignore_whitespace || Token::T_WHITESPACE !== $token->getType()) {
				break;
			}
		}

		if (!$token) {
			$chunk = new StringChunk($this->reader);

			throw BlateParserException::withChunk(Message::UNEXPECTED_EOF_WHILE_EXPECTING, $chunk->setExpected(Token::getTypeName($type)));
		}

		if (null !== $type && $type !== $token->getType()) {
			$token->getChunk()
				->setExpected($value ?? Token::getTypeName($type));

			throw BlateParserException::withToken(Message::UNEXPECTED_WHILE_EXPECTING, $token);
		}

		if (null !== $value && $value !== $token->getValue()) {
			$token->getChunk()
				->setExpected($value);

			throw BlateParserException::withToken(Message::UNEXPECTED_WHILE_EXPECTING, $token);
		}

		return $token;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException
	 */
	public function nextIsOneOf(array $types, bool $ignore_whitespace = false): TokenInterface
	{
		while ($token = $this->move()) {
			if (!$ignore_whitespace || Token::T_WHITESPACE !== $token->getType()) {
				break;
			}
		}

		if (!$token) {
			$chunk    = new StringChunk($this->reader);
			$expected = \implode('|', Token::getTypesNames($types));

			throw BlateParserException::withChunk(Message::UNEXPECTED_EOF_WHILE_EXPECTING, $chunk->setExpected($expected));
		}

		$type = $token->getType();
		if (!\in_array($type, $types, true)) {
			$expected = \implode('|', Token::getTypesNames($types));

			$token->getChunk()
				->setExpected($expected);

			throw BlateParserException::withToken(Message::UNEXPECTED_WHILE_EXPECTING, $token);
		}

		return $token;
	}

	/**
	 * Gets next token.
	 */
	private function computeToken(): ?TokenInterface
	{
		$reader = $this->reader;

		if (($this->infinite_loop_cursor === $reader->getCursor()) && $reader->move()) {
			throw new BlateParserException(\sprintf(
				'Possible infinite loop on line %s at index %s.',
				$reader->getLineNumber(),
				$reader->getLineIndex()
			));
		}

		$this->infinite_loop_cursor = $reader->getCursor();

		$c = $reader->current();

		if (null === $c) {
			$this->onEnd();

			return null;
		}

		$chunk = new StringChunk($reader);

		if (!$this->in_tag && ($tag = $reader->getChunkIfNextIs(Blate::TAG_OPENER))) {
			$chunk        = $tag;
			$type         = Token::T_TAG_OPEN;
			$this->in_tag = true;
		} elseif ($this->in_tag && ($tag = $reader->getChunkIfNextIs(Blate::TAG_CLOSER))) {
			$chunk        = $tag;
			$type         = Token::T_TAG_CLOSE;
			$this->in_tag = false;
		} elseif (!$this->in_tag) {
			$chunk = $reader->whileTrue(static function () use ($reader) {
				return !$reader->isNextChunk(Blate::TAG_OPENER);
			});
			$type  = Token::T_RAW_DATA;
		} elseif ('\'' === $c || '"' === $c) {
			$reader->move();
			$r = $reader->moveUntilChar($c, '\\');
			$reader->move();

			$chunk->setValue($c . $r->getValue() . $c);
			$type = Token::T_STRING;
		} elseif ($this->isWhiteSpace($c)) {
			$chunk = $reader->whileTrue(function ($cur) {
				return $this->isWhiteSpace($cur);
			});
			$type  = Token::T_WHITESPACE;
		} elseif ('{' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_CURLY_BRACKET_OPEN;
		} elseif ('}' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_CURLY_BRACKET_CLOSE;
		} elseif ('(' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_PAREN_OPEN;
		} elseif (')' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_PAREN_CLOSE;
		} elseif ('[' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_SQUARE_BRACKET_OPEN;
		} elseif (']' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_SQUARE_BRACKET_CLOSE;
		} elseif ($this->isNameFirstChar($c)) {
			$r = $this->eatName();
			$chunk->setValue($r->getValue());
			$type = Token::T_NAME;
		} elseif ('.' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_DOT;
		} elseif ('~' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_TILDE;
		} elseif ($this->isDigit($c)) {
			$chunk = $this->eatNumber();
			$type  = Token::T_DNUMBER;
		} elseif ('&' === $c && '&' === $reader->next()) {
			$reader->move();
			$reader->move();
			$chunk->setValue('&&');
			$type = Token::T_COND_AND;
		} elseif ('|' === $c) {
			if ('|' === $reader->next()) {
				$reader->move();
				$reader->move();
				$type = Token::T_COND_OR;
				$chunk->setValue('||');
			} else {
				$reader->move();
				$type = Token::T_PIPE;
				$chunk->setValue($c);
			}
		} elseif ('+' === $c || '-' === $c || '*' === $c || '/' === $c || '%' === $c || '^' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_OPERATOR;
		} elseif (',' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_COMMA;
		} elseif ('>' === $c) {
			$reader->move();
			$op = $c;

			if ('=' === $reader->current()) {
				$reader->move();
				$op .= '=';
			}

			$chunk->setValue($op);
			$type = Token::T_GT_OR_GT_EQ;
		} elseif ('<' === $c) {
			$reader->move();
			$op = $c;

			if ('=' === $reader->current()) {
				$reader->move();
				$op .= '=';
			}

			$chunk->setValue($op);
			$type = Token::T_LT_OR_LT_EQ;
		} elseif ('!' === $c) {
			$reader->move();
			$op   = $c;
			$type = Token::T_NOT;

			if ('=' === $reader->current()) {
				$reader->move();
				$op .= '=';
				$n  = $reader->next();

				if ('=' === $n) {
					$reader->move();
					$op .= '=';
				}

				$type = Token::T_NOT_EQ;
			}

			$chunk->setValue($op);
		} elseif ('=' === $c && '=' === $reader->next()) {
			$reader->move();
			$reader->move();
			$op = '==';

			if ('=' === $reader->current()) {
				$reader->move();
				$op .= '=';
			}

			$chunk->setValue($op);
			$type = Token::T_EQ;
		} elseif (':' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_COLON;
		} elseif ('#' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_HASH;
		} elseif ('@' === $c) {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_AT;
		} elseif ('?' === $c && '?' === $reader->next()) {
			$reader->move();
			$reader->move();
			$chunk->setValue('??');
			$type = Token::T_NULL_COALESCE;
		} else {
			$reader->move();
			$chunk->setValue($c);
			$type = Token::T_UNKNOWN;
		}

		return new Token($chunk, $type);
	}

	/**
	 * Adds the given token to the tree.
	 */
	private function addToTree(TokenInterface $token): TokenInterface
	{
		if (!$token->getAttribute(Token::ATTR_IN_TREE)) {
			if ($token->isGroupOpener()) {
				if ($this->current_group) {
					$this->current_group->addChild($token->setParent($this->current_group));
				} else {
					$this->tree[] = $token;
				}

				$this->current_group = $token;
				$this->open_stack[]  = $token;
			} elseif ($token->isGroupCloser()) {
				if ($this->current_group && $token->isGroupCloserOf($this->current_group)) {
					$this->current_group = $this->current_group->getParent();
					\array_pop($this->open_stack);

					if ($this->current_group) {
						$this->current_group->addChild($token->setParent($this->current_group));
					} else {
						$this->tree[] = $token;
					}
				} else {
					throw BlateParserException::withToken(Message::UNEXPECTED, $token);
				}
			} elseif ($this->current_group) {
				$this->current_group->addChild($token->setParent($this->current_group));
			} else {
				$this->tree[] = $token;
			}

			$token->setAttribute(Token::ATTR_IN_TREE, true);
		}

		return $token;
	}

	/**
	 * Called once we reach the end.
	 */
	private function onEnd(): void
	{
		$last_unclosed_group = \array_pop($this->open_stack);

		if ($last_unclosed_group) {
			throw BlateParserException::withToken(Message::GROUP_NEVER_CLOSED, $last_unclosed_group);
		}
	}

	/**
	 * Reads a valid identifier (T_NAME) from the current position in the input.
	 *
	 * An identifier starts with an underscore, dollar sign, or ASCII letter,
	 * followed by zero or more underscores, dollar signs, ASCII letters, or digits.
	 *
	 * @throws BlateParserException on an empty name or unexpected character
	 */
	private function eatName(): StringChunk
	{
		$result = $this->reader->whileTrue(
			function ($c, $i) {
				if (0 === $i) {
					return $this->isNameFirstChar($c);
				}

				return $this->isNameChar($c);
			}
		);

		$name = $result->getValue();

		if ('' === $name) {
			if (!$result->eof()) {
				throw BlateParserException::withChunk(
					Message::UNEXPECTED,
					$result
				);
			}

			throw new BlateParserException(Message::UNEXPECTED_EOF_WHILE_EXPECTING_NAME);
		}

		return $result;
	}

	/**
	 * Reads a numeric literal (integer, float, scientific notation) from the input.
	 *
	 * Accepts digits, '.', 'e', and 'E'.  Validates the accumulated string
	 * with is_numeric() after consumption.
	 *
	 * @throws BlateParserException on an empty or non-numeric result
	 */
	private function eatNumber(): StringChunk
	{
		$result = $this->reader->whileTrue(
			function ($c) {
				return $this->isDigit($c) || '.' === $c || 'e' === $c || 'E' === $c;
			}
		);

		$value = $result->getValue();

		if ('' === $value) {
			if (!$result->eof()) {
				throw BlateParserException::withChunk(
					Message::UNEXPECTED_WHILE_EXPECTING_NUMBER,
					$result
				);
			}

			throw new BlateParserException(Message::UNEXPECTED_EOF_WHILE_EXPECTING_NUMBER);
		}

		if (!\is_numeric($value)) {
			throw BlateParserException::withChunk(Message::INVALID_NUMBER, $result);
		}

		return $result;
	}

	/**
	 * Returns true when $c is a whitespace character.
	 */
	private function isWhiteSpace(string $c): bool
	{
		return \ctype_space($c);
	}

	/**
	 * Returns true when $c is an ASCII digit (0-9). */
	private function isDigit(string $c): bool
	{
		return isset($this->digits[$c]);
	}

	/**
	 * Returns true when $c is valid as the first character of an identifier.
	 */
	private function isNameFirstChar(string $c): bool
	{
		return '_' === $c || Blate::HELPER_PREFIX_CHAR === $c || isset($this->alpha_lower[$c]) || isset($this->alpha_upper[$c]);
	}

	/**
	 * Returns true when $c is valid as a non-first character of an identifier.
	 */
	private function isNameChar(string $c): bool
	{
		return '_' === $c || Blate::HELPER_PREFIX_CHAR === $c || isset($this->alpha_lower[$c]) || isset($this->alpha_upper[$c]) || isset($this->digits[$c]);
	}
}
