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

// Walk up the directory tree from this file to find vendor/autoload.php.
// This works both when blate is the root project (development) and when it
// is installed as a dependency inside another project's vendor/ tree.
$_blate_lsp_autoload = null;
$_blate_lsp_dir      = __DIR__;

while (true) {
    $candidate = $_blate_lsp_dir . \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'autoload.php';

    if (\file_exists($candidate)) {
        $_blate_lsp_autoload = $candidate;

        break;
    }

    $parent = \dirname($_blate_lsp_dir);

    if ($parent === $_blate_lsp_dir) {
        break;
    }

    $_blate_lsp_dir = $parent;
}

unset($_blate_lsp_dir, $candidate, $parent);

if (null === $_blate_lsp_autoload) {
    \fwrite(\STDERR, '[blate-lsp] Cannot find vendor/autoload.php' . "\n");

    exit(1);
}

require_once $_blate_lsp_autoload;

(new Blate\Lsp\BlateLspServer($_blate_lsp_autoload))->run();
