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
use Blate\Features\BlockComment;
use Blate\Features\BlockEach;
use Blate\Features\BlockExtends;
use Blate\Features\BlockIf;
use Blate\Features\BlockImport;
use Blate\Features\BlockImportRaw;
use Blate\Features\BlockPhp;
use Blate\Features\BlockRaw;
use Blate\Features\BlockScoped;
use Blate\Features\BlockSet;
use Blate\Features\BlockSlot;
use Blate\Helpers\Helpers;

Blate::registerBlock(BlockEach::NAME, BlockEach::class);
Blate::registerBlock(BlockIf::NAME, BlockIf::class);
Blate::registerBlock(BlockScoped::NAME, BlockScoped::class);
Blate::registerBlock(BlockSlot::NAME, BlockSlot::class);
Blate::registerBlock(BlockSet::NAME, BlockSet::class);
Blate::registerBlock(BlockImport::NAME, BlockImport::class);
Blate::registerBlock(BlockImportRaw::NAME, BlockImportRaw::class);
Blate::registerBlock(BlockRaw::NAME, BlockRaw::class);
Blate::registerBlock(BlockExtends::NAME, BlockExtends::class);
Blate::registerBlock(BlockComment::NAME, BlockComment::class);
Blate::registerBlock(BlockPhp::NAME, BlockPhp::class);
Helpers::register();
