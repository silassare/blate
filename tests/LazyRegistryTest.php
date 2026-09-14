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

namespace Blate\Tests;

use Blate\Blate;
use Blate\Exceptions\BlateRuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * The built-ins and the deferred set-up wait for Blate's first use.
 *
 * @internal
 *
 * @coversNothing
 */
final class LazyRegistryTest extends TestCase
{
	public function testNothingIsRegisteredBeforeTheFirstUse(): void
	{
		// In a process of its own: this one used Blate long ago.
		$out = self::runFresh(<<<'PHP'
			$helpers = new ReflectionProperty(Blate\Blate::class, 'helpers');
			$order   = [];

			Blate\Blate::onFirstUse(static function () use (&$order, $helpers): void {
				$builtins = \count($helpers->getValue()) > 0;
				$order[]  = 'deferred, ' . ($builtins ? 'after the built-ins' : 'before the built-ins');

				Blate\Blate::registerHelper('deferred_probe', static fn (): string => 'ok');
			});

			$before = \count($helpers->getValue());
			$names  = \array_keys(Blate\Blate::getHelpers());

			Blate\Blate::getHelpers();
			Blate\Blate::onFirstUse(static function () use (&$order): void {
				$order[] = 'added after the first use, run at once';
			});

			echo \json_encode([
				'registered_before' => $before,
				'order'             => $order,
				'builtin'           => \in_array('has', $names, true),
				'deferred'          => \in_array('deferred_probe', $names, true),
			]);
			PHP);

		self::assertSame([
			'registered_before' => 0,
			'order'             => ['deferred, after the built-ins', 'added after the first use, run at once'],
			'builtin'           => true,
			'deferred'          => true,
		], $out);
	}

	public function testABuiltinHelperStillCannotBeReplaced(): void
	{
		$this->expectException(BlateRuntimeException::class);

		Blate::registerHelper('has', static fn (): bool => true);
	}

	/**
	 * Runs PHP code in a new process with Blate loaded, and decodes the JSON it prints.
	 */
	private static function runFresh(string $code): array
	{
		$file = \tempnam(\sys_get_temp_dir(), 'blate-lazy-');

		$autoload = \var_export(\dirname(__DIR__) . '/vendor/autoload.php', true);

		\file_put_contents($file, "<?php\n\nrequire " . $autoload . ";\n\n" . $code);

		try {
			\exec(\escapeshellarg(\PHP_BINARY) . ' ' . \escapeshellarg($file) . ' 2>&1', $lines, $status);
		} finally {
			\unlink($file);
		}

		$output = \implode("\n", $lines);

		self::assertSame(0, $status, $output);

		return \json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
	}
}
