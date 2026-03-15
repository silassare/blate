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
use Blate\Features\BlockCapture;
use Blate\Features\BlockComment;
use Blate\Features\BlockEach;
use Blate\Features\BlockExtends;
use Blate\Features\BlockIf;
use Blate\Features\BlockImport;
use Blate\Features\BlockImportRaw;
use Blate\Features\BlockPhp;
use Blate\Features\BlockRaw;
use Blate\Features\BlockRepeat;
use Blate\Features\BlockScoped;
use Blate\Features\BlockSet;
use Blate\Features\BlockSlot;
use Blate\Features\BlockSwitch;
use Blate\Helpers\Helpers;

// = Blocks
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
Blate::registerBlock(BlockCapture::NAME, BlockCapture::class);
Blate::registerBlock(BlockRepeat::NAME, BlockRepeat::class);
Blate::registerBlock(BlockSwitch::NAME, BlockSwitch::class);

// = Global variables
Blate::registerGlobalVar('BLATE_VERSION', Blate::VERSION, ['description' => 'The Blate engine version string (e.g. ' . Blate::VERSION . ').']);
Blate::registerGlobalVar('BLATE_VERSION_NAME', Blate::VERSION_NAME, ['description' => 'The Blate engine display name (e.g. ' . Blate::VERSION_NAME . ').']);
Blate::registerGlobalVar('BRACE_OPEN', '{', ['description' => 'Literal opening brace. Use to output a literal { without opening a Blate tag.']);
Blate::registerGlobalVar('BRACE_CLOSE', '}', ['description' => 'Literal closing brace. Use to output a literal } without closing a Blate tag.']);

// = Helpers
Helpers::register();
