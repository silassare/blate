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

namespace Blate\Exceptions;

use Blate\Exceptions\Traits\BlateExceptionTrait;
use PHPUtils\Interfaces\RichExceptionInterface;
use RuntimeException;

/**
 * Class BlateRuntimeException.
 */
class BlateRuntimeException extends RuntimeException implements RichExceptionInterface
{
	use BlateExceptionTrait;
}
