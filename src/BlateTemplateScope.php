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

namespace Blate;

/**
 * Class BlateTemplateScope.
 *
 * Snapshot of the currently executing template, accessible via Blate::scope().
 *
 * A scope is pushed onto a static stack before each BlateTemplateParsed::build() call
 * and popped in a finally block when it returns.  The stack grows for nested
 * templates ({@import} / {@extends}) so Blate::scope() always returns the
 * innermost (currently executing) template's scope.
 *
 * NOTE: the static stack is safe for conventional PHP-FPM / CLI and for
 * cooperative runtimes (Swoole coroutines, PHP Fibers) because build() is
 * synchronous -- it never suspends control between the push and the pop.
 * It is NOT safe for preemptive threading (e.g. pthreads / parallel extension).
 */
final class BlateTemplateScope
{
	/**
	 * BlateTemplateScope constructor.
	 *
	 * @param DataContext $data     the current render context
	 * @param Blate       $template the Blate instance for the executing template
	 */
	public function __construct(
		public readonly DataContext $data,
		public readonly Blate $template,
	) {}
}
