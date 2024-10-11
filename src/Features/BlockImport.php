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

use Blate\Blate;
use Blate\Exceptions\BlateParserException;
use Blate\Expressions\Expression;
use Blate\Helpers\Helpers;
use Blate\Message;
use Blate\Token;
use PHPUtils\FS\PathUtils;

/**
 * Class BlockImport.
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
		$blate_var    = Blate::createVar();
		$instance_var = Blate::createVar();
		$context_var  = Blate::createVar();

		$this->parser->writeCode(\sprintf(
			'
%s = Blate::fromPath(%s)->parse();
%s = %s->getParsedInstance();
%s = $this->createExtendsContext(%s, %s);
%s->build(%s);
',
			$blate_var,
			Helpers::quote($abs_path),
			$instance_var,
			$blate_var,
			$context_var,
			$blate_var,
			$context,
			$instance_var,
			$context_var
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
