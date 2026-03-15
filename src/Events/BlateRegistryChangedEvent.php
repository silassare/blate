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

namespace Blate\Events;

use PHPUtils\Events\Event;

/**
 * Class BlateRegistryChangedEvent.
 *
 * Dispatched whenever the Blate registry is mutated: a block, helper, or
 * global variable is registered. Listeners (e.g. the LSP server) use this
 * to detect registry updates without polling.
 */
class BlateRegistryChangedEvent extends Event {}
