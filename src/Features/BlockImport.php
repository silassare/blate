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
use Blate\Message;
use Blate\Token;
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
 * The imported path cannot be the same file as the current template.
 */
class BlockImport extends Block
{
	public const NAME = 'import';

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws BlateParserException
	 */
	public function onOpen(): void
	{
		$path_token = $this->lexer->nextIs(Token::T_STRING, null, true);

		$blate = $this->parser->getBlate();

		$abs_path = PathUtils::resolve($blate->getSrcDir(), Helpers::unquote($path_token->getValue()));

		if ($abs_path === $blate->getSrcPath()) {
			throw BlateParserException::withToken(Message::IMPORT_PATH_IS_SELF, $path_token);
		}

		$this->lexer->nextIs(Token::T_WHITESPACE);

		$context      = (new Expression())->get($this->lexer);
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
				'abs_path'     => Helpers::quote($abs_path),
				'instance_var' => $instance_var,
				'context_var'  => $context_var,
				'context'      => $context,
			]
		));
	}

	/**
	 * {@inheritDoc}
	 */
	public function requireClose(): bool
	{
		return false;
	}
}
