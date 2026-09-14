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

use Blate\Blate;

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/Utils/define.php';

Blate::setCacheDir(__DIR__ . '/../tests/.cache');
