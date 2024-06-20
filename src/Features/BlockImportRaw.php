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
use Blate\Token;
use PHPUtils\FS\PathUtils;

/**
 * Class BlockImportRaw.
 */
class BlockImportRaw extends Block
{
	public const NAME = 'import_raw';

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

		$abs_path = PathUtils::resolve($blate->getSrcDir(), Blate::unquote($path_token->getValue()));

		$this->parser->writeCode(\sprintf(
			\PHP_EOL . 'echo Blate::loadFile(%s);' . \PHP_EOL,
			Blate::quote($abs_path),
		));

		$this->lexer->nextIs(Token::T_TAG_CLOSE, null, true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function requireClose(): bool
	{
		return false;
	}
}
