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

namespace Blate\Features;

use Blate\Exceptions\BlateParserException;
use Blate\Expressions\Expression;
use Blate\Helpers\Helpers;
use Blate\Token;
use Override;
use PHPUtils\FS\PathUtils;
use PHPUtils\Str;

/**
 * Class BlockImport.
 *
 * Implements the {@import 'path' context} inline block.
 *
 * Resolves the path relative to the current source, then compiles and renders
 * the imported template immediately in the current output stream using the
 * given context expression.
 *
 * The imported path may be the current template: a partial rendering a tree imports itself for a
 * node's children. The render depth bounds it ({@see \Blate\Blate::MAX_RENDER_DEPTH}).
 */
class BlockImport extends Block
{
	public const string NAME = 'import';

	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * @throws BlateParserException
	 */
	#[Override]
	public function onOpen(): void
	{
		$path_token = $this->lexer->nextIs(Token::T_STRING, null, true);

		$blate = $this->parser->getBlate();

		$abs_path = PathUtils::resolve($blate->getSrcDir(), Helpers::unquote($path_token->getValue()));

		// A template may import itself. It is how a tree is rendered: the partial that renders one
		// node imports itself for that node's children, under a condition on the data, and what
		// ends it is the children running out. Refusing it here only moved the same recursion into
		// a second file that imports this one back, which is no more bounded and harder to read.
		// What bounds it is the render depth, which is the only thing that can: whether the
		// recursion ends is a property of the data and not of the path.
		$this->lexer->nextIs(Token::T_WHITESPACE);

		$context      = new Expression()->get($this->lexer);
		$blate_var    = $this->parser->createVar();
		$instance_var = $this->parser->createVar();
		$context_var  = $this->parser->createVar();

		$this->parser->writeCode(Str::interpolate(
			"\n{blate_var} = Blate::fromPath({abs_path})->parse();\n"
				. '{instance_var} = {blate_var}->getParsedInstance();' . "\n"
				. '{context_var} = $this->createExtendsContext({blate_var}, {context});' . "\n"
				. '{instance_var}->run({context_var});' . "\n",
			[
				'blate_var'    => $blate_var,
				'abs_path'     => $this->parser->phpStringLiteral($abs_path),
				'instance_var' => $instance_var,
				'context_var'  => $context_var,
				'context'      => $context,
			]
		));
	}

	#[Override]
	public function requireClose(): bool
	{
		return false;
	}
}
