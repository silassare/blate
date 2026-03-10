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

use Blate\Blate;
use Blate\Exceptions\BlateParserException;
use Blate\Interfaces\LexerInterface;
use Blate\Interfaces\TokenInterface;
use Blate\Message;
use Blate\Token;

/**
 * Class Expression.
 *
 * Entry point for parsing a single template expression.
 *
 * Usage:
 *   $compiled = (new Expression())->get($lexer);         // reads until TAG_CLOSE
 *   $compiled = (new Expression())->getWhileTrue($lexer, $fn); // reads while $fn returns true
 */
class Expression
{
	/**
	 * Parses an expression from the lexer until the closing brace (TAG_CLOSE) is consumed.
	 *
	 * @param LexerInterface $lexer the active lexer, positioned just after the opening brace
	 *
	 * @return string compiled PHP expression string
	 */
	public function get(LexerInterface $lexer): string
	{
		return $this->getWhileTrue($lexer, static function (TokenInterface $token) {
			return Token::T_TAG_CLOSE !== $token->getType();
		});
	}

	/**
	 * Parses an expression from the lexer, advancing tokens as long as $whileTrue returns true.
	 *
	 * Resets the expression compiler's process-level chain-tracking state before
	 * starting, so that stale state from a previously failed parse cannot affect
	 * this one (important in long-running processes).
	 *
	 * Pipe filters are applied left to right: {expr | fn1 | fn2(arg)} compiles to
	 * fn2(fn1(expr), arg).
	 *
	 * @param LexerInterface $lexer     the active lexer
	 * @param callable       $whileTrue predicate: (TokenInterface $token): bool
	 *
	 * @return string compiled PHP expression string
	 */
	public function getWhileTrue(LexerInterface $lexer, callable $whileTrue): string
	{
		// Parse the base expression, stopping at T_PIPE as well as the caller's boundary.
		$ep = new ExpressionParser($lexer);
		$ep->parse(static function (TokenInterface $token) use ($whileTrue) {
			return Token::T_PIPE !== $token->getType() && $whileTrue($token);
		});

		$output = $ep->getOutput();

		// Apply each pipe filter in sequence: {expr | fn1 | fn2(arg)} -> fn2(fn1(expr), arg).
		// Whitespace between pipe segments is not significant; skip it before each check.
		while (true) {
			while (($pipe_token = $lexer->current()) !== null && Token::T_WHITESPACE === $pipe_token->getType()) {
				$lexer->move();
			}

			$pipe_token = $lexer->current();

			if (
				null === $pipe_token
				|| Token::T_PIPE !== $pipe_token->getType()
				|| !$whileTrue($pipe_token)
			) {
				break;
			}

			$lexer->move(); // consume |
			$output = $this->applyFilter($lexer, $output);
		}

		return $output;
	}

	/**
	 * Processes one pipe-filter segment and returns the updated compiled expression.
	 *
	 * Expects the lexer to be positioned immediately after the | character.
	 * Consumes: optional whitespace, the filter function name, and optional (extra args...).
	 *
	 * The piped value is injected as the first argument:
	 *   {expr | fn}        -> $context->chain('L:I')->getHelper('L:I', 'fn')->call('L:I', expr)->val()
	 *   {expr | fn(a, b)}  -> $context->chain('L:I')->getHelper('L:I', 'fn')->call('L:I', expr, a, b)->val()
	 *
	 * @param LexerInterface $lexer the active lexer
	 * @param string         $piped the already-compiled left-hand expression
	 *
	 * @return string compiled PHP for fn(piped[, extra_args])
	 *
	 * @throws BlateParserException when the filter name is missing or not a plain name
	 */
	private function applyFilter(LexerInterface $lexer, string $piped): string
	{
		// Skip optional whitespace before the filter name.
		while (($tok = $lexer->current()) !== null && Token::T_WHITESPACE === $tok->getType()) {
			$lexer->move();
		}

		$fn_token = $lexer->current();

		if (null === $fn_token) {
			throw new BlateParserException(Message::UNEXPECTED_END_OF_EXPRESSION);
		}

		if (Token::T_NAME !== $fn_token->getType()) {
			throw BlateParserException::withToken(Message::UNEXPECTED, $fn_token);
		}

		$fn_name = $fn_token->getValue();

		// Strip optional leading prefix (allowed for clarity, e.g. {expr | $upper}).
		if (\str_starts_with($fn_name, Blate::HELPER_PREFIX_CHAR)) {
			$fn_name = \substr($fn_name, \strlen(Blate::HELPER_PREFIX_CHAR));
		}

		$lexer->move(); // consume the function name

		// Skip optional whitespace before an opening '('.
		while (($tok = $lexer->current()) !== null && Token::T_WHITESPACE === $tok->getType()) {
			$lexer->move();
		}

		$extra_args = '';
		$next       = $lexer->current();

		if (null !== $next && Token::T_PAREN_OPEN === $next->getType()) {
			// fn(arg1, arg2) form -- parse the additional arguments.
			$paren_token = $next;
			$lexer->move(); // consume (

			$args_ep = new ExpressionParser($lexer);
			$args_ep->parse($args_ep->whileInChildrenOf($paren_token), [
				ExpressionParser::IN_FUNC_CALL_ARGS => true,
				ExpressionParser::ALLOW_EMPTY       => true,
			]);
			$extra_args = \trim($args_ep->getOutput());

			// The ) is still current after the inner parse; consume it.
			$closing = $lexer->current();

			if (null !== $closing && Token::T_PAREN_CLOSE === $closing->getType()) {
				$lexer->move();
			}
		}

		// Build: $context->chain('L:I')->getHelper('L:I', 'fn')->call('L:I', <piped>[, extra_args])->val()
		$fn_loc     = $fn_token->getChunk()->getLocation();
		$fn_loc_str = $fn_loc['line'] . ':' . $fn_loc['index'];
		$call       = Blate::DATA_CONTEXT_VAR . '->chain(\'' . $fn_loc_str . '\')->getHelper(\'' . $fn_loc_str . '\', \'' . $fn_name . '\')->call(\'' . $fn_loc_str . '\', ' . $piped;

		if ('' !== $extra_args) {
			$call .= ', ' . $extra_args;
		}

		return $call . ')->val()';
	}
}
