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

Blate::registerHelper('scopeGetPath', static function (): string {
    return \basename(Blate::scope()->template->getSrcPath() ?? '');
});

return [];
