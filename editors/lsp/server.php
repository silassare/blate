#!/usr/bin/env php
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

// Resolve the project root relative to this file (editors/lsp/ -> root).
$root = \dirname(__DIR__, 2);

if (!\file_exists($root . '/vendor/autoload.php')) {
    fwrite(\STDERR, '[blate-lsp] Cannot find vendor/autoload.php at: ' . $root . "\n");

    exit(1);
}

require_once $root . '/vendor/autoload.php';

(new Blate\Lsp\BlateLspServer())->run();
