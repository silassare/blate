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
use Blate\Exceptions\BlateException;
use Blate\Exceptions\BlateParserException;
use Blate\Parser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 *
 * @coversNothing
 */
final class TemplateSyntaxTest extends TestCase
{
	/**
	 * @throws BlateException
	 */
	public function testNoTokenTemplate(): void
	{
		$this->runValid('template-no-token');
	}

	/**
	 * @throws BlateException
	 */
	public function testBlockPrint(): void
	{
		$this->runValid('block-print');
	}

	/**
	 * @throws BlateException
	 */
	public function testEmptyTemplate(): void
	{
		$this->runValid('template-empty');
	}

	/**
	 * @throws BlateException
	 */
	public function testValidTemplate(): void
	{
		$this->runValid('template-valid');
	}

	/**
	 * @throws BlateException
	 */
	public function testValidPhp(): void
	{
		$this->runValid('template-php');
	}

	/**
	 * @throws BlateException
	 */
	public function testValidExpression(): void
	{
		$this->runValid('expression-valid');
	}

	public function testBlockUndefined(): void
	{
		$this->runInvalid('block-undefined');
	}

	public function testBlockUnclosed1(): void
	{
		$this->runInvalid('block-unclosed-1');
	}

	public function testBlockUnclosed2(): void
	{
		$this->runInvalid('block-unclosed-2');
	}

	public function testExpressionInvalid1(): void
	{
		$this->runInvalid('expression-invalid-1');
	}

	public function testExpressionInvalid2(): void
	{
		$this->runInvalid('expression-invalid-2');
	}

	public function testExpressionInvalid3(): void
	{
		$this->runInvalid('expression-invalid-3');
	}

	public function testExpressionInvalid4(): void
	{
		$this->runInvalid('expression-invalid-4');
	}

	public function testExpressionInvalid5(): void
	{
		$this->runInvalid('expression-invalid-5');
	}

	public function testExpressionInvalid6(): void
	{
		$this->runInvalid('expression-invalid-6');
	}

	public function testExpressionInvalid7(): void
	{
		$this->runInvalid('expression-invalid-7');
	}

	public function testExpressionInvalid8(): void
	{
		$this->runInvalid('expression-invalid-8');
	}

	public function testExpressionInvalid9(): void
	{
		$this->runInvalid('expression-invalid-9');
	}

	public function testSlotNameExists(): void
	{
		$this->runInvalid('slot-name-exists');
	}

	public function testSlotDefaultOutsideExtends(): void
	{
		$this->runInvalid('slot-default-outside-extends');
	}

	public function testExtendsUnexpectedChildBlock(): void
	{
		$this->runInvalid('extends-unexpected-child-block');
	}

	public function testExtendsSamePath(): void
	{
		$this->runInvalid('extends-same-path');
	}

	public function testExtendsUnexpectedChildExpression(): void
	{
		$this->runInvalid('extends-unexpected-child-expression');
	}

	public function testExtendsUnexpectedContent(): void
	{
		$this->runInvalid('extends-unexpected-content');
	}

	/**
	 * @throws BlateException
	 */
	public function testExtendsValid(): void
	{
		$this->runValid('extends-valid');
	}

	/**
	 * @throws BlateException
	 */
	public function testImportValid(): void
	{
		$this->runValid('import-valid');
	}

	public function testImportSamePath(): void
	{
		$this->runInvalid('import-same-path');
	}

	/**
	 * @throws BlateException
	 */
	public function testTemplatePrintToken(): void
	{
		$this->runValid('template-print-token');
	}

	/**
	 * @throws BlateException
	 */
	public function testTemplateComment(): void
	{
		$this->runValid('template-comment');
	}

	/**
	 * @throws BlateException
	 */
	public function testTemplateRawBlock(): void
	{
		$this->runValid('template-raw-block');
	}

	/**
	 * @throws BlateException
	 */
	public function testTemplateImportRaw(): void
	{
		$this->runValid('template-import-raw');
	}

	/**
	 * @throws BlateException
	 */
	public function testTemplateSetAndScopedBlock(): void
	{
		$this->runValid('template-set-and-scoped-block');
	}

	/**
	 * @throws BlateException
	 */
	protected function runValid(string $name): void
	{
		$test_dir        = BLATE_TEST_TEMPLATES_DIR . '/' . $name;
		$template        = $test_dir . '/template.blate';
		$output_file     = $test_dir . '/output.txt';
		$full_error_file = $test_dir . '/error.full.txt';
		$inject_file     = $test_dir . '/inject.php';
		$output          = null;
		$error           = null;

		try {
			if (\file_exists($inject_file)) {
				$bl = Blate::fromPath($template)
					->parse(true);

				$inject = include $inject_file;
				$output = $bl->runGet($inject);
			} else {
				$parser = new Parser(Blate::fromPath($template));
				$parser->parse();
				$output = $parser->getClassBody();
			}
		} catch (BlateException|BlateParserException $e) {
			$error = $e->describe(false, false);
			\file_put_contents($full_error_file, $e->describe(false, true));
		}

		if ($error) {
			throw new RuntimeException('Unexpected error see details in: ' . $full_error_file);
		}

		if (\file_exists($full_error_file)) {
			\unlink($full_error_file);
		}

		if (!\file_exists($output_file)) {
			\file_put_contents($output_file, $output);
		} else {
			$expected = \file_get_contents($output_file);
			self::assertSame($expected, $output);
		}
	}

	protected function runInvalid(string $name): void
	{
		$test_dir        = BLATE_TEST_TEMPLATES_DIR . '/' . $name;
		$template        = $test_dir . '/template.blate';
		$error_file      = $test_dir . '/error.txt';
		$full_error_file = $test_dir . '/error.full.txt';
		$inject_file     = $test_dir . '/inject.php';
		$error           = null;

		try {
			$bl = Blate::fromPath($template)
				->parse(true);

			if (\file_exists($inject_file)) {
				$inject = include $inject_file;
				$bl->runGet($inject);
			}
		} catch (BlateException|BlateParserException $e) {
			$error = $e->describe(false, false);
			\file_put_contents($full_error_file, $e->describe(false, true));
		}

		if (null === $error) {
			throw new RuntimeException('Exception expected, none found.');
		}

		if (!\file_exists($error_file)) {
			\file_put_contents($error_file, $error);
		} else {
			$expected = \file_get_contents($error_file);
			self::assertSame($expected, $error);
		}
	}
}
