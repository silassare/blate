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

namespace Blate\Tests\Utils;

/**
 * Class TestPaths.
 *
 * Where the suite's own files live, as constants rather than as a global the analyser cannot see:
 * a `define()` in one file is invisible to every other, so a test reading it was reading something
 * nothing declared as far as any tool could tell.
 */
final class TestPaths
{
	/**
	 * The sample templates the suite renders.
	 */
	public const string SAMPLES = __DIR__ . '/../samples';
}
