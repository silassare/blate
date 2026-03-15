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
use Blate\Lsp\BlateLspServer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for the private helper methods of BlateLspServer using reflection.
 *
 * I/O-bound request handlers (handleCompletion, handleHover, handleRename,
 * handleCodeAction, logStartupInfo) write directly to STDOUT via fwrite() and
 * cannot be unit-tested without process-level output redirection. Those
 * handlers are thin wiring that delegate to the pure private methods tested
 * here.
 *
 * @internal
 *
 * @coversNothing
 */
final class BlateLspServerTest extends TestCase
{
	private BlateLspServer $server;
	private ReflectionClass $ref;

	protected function setUp(): void
	{
		$this->server = new BlateLspServer(
			\dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'autoload.php',
		);
		$this->ref    = new ReflectionClass($this->server);
	}
	// =========================================================================
	// Utilities: positionToOffset
	// =========================================================================

	public function testPositionToOffsetSingleLine(): void
	{
		// "hello" line 0, char 3 -> byte offset 3
		self::assertSame(3, $this->call('positionToOffset', 'hello', 0, 3));
	}

	public function testPositionToOffsetMultiLine(): void
	{
		// "hello\nworld" line 1, char 2 -> 6 bytes for "hello\n" + 2 = offset 8
		self::assertSame(8, $this->call('positionToOffset', "hello\nworld", 1, 2));
	}

	public function testPositionToOffsetFirstCharOfSecondLine(): void
	{
		self::assertSame(6, $this->call('positionToOffset', "hello\nworld", 1, 0));
	}

	// =========================================================================
	// Utilities: byteRangeToLspRange
	// =========================================================================

	public function testByteRangeToLspRangeSingleLine(): void
	{
		$range = $this->call('byteRangeToLspRange', 'hello world', 6, 11);

		self::assertSame(['line' => 0, 'character' => 6], $range['start']);
		self::assertSame(['line' => 0, 'character' => 11], $range['end']);
	}

	public function testByteRangeToLspRangeAcrossLines(): void
	{
		// "hello\nworld", start=3 (line 0, char 3), end=8 (line 1, char 2)
		$range = $this->call('byteRangeToLspRange', "hello\nworld", 3, 8);

		self::assertSame(['line' => 0, 'character' => 3], $range['start']);
		self::assertSame(['line' => 1, 'character' => 2], $range['end']);
	}

	public function testByteRangeToLspRangeAtLineStart(): void
	{
		// Start of second line
		$range = $this->call('byteRangeToLspRange', "abc\nxyz", 4, 7);

		self::assertSame(['line' => 1, 'character' => 0], $range['start']);
		self::assertSame(['line' => 1, 'character' => 3], $range['end']);
	}

	// =========================================================================
	// Utilities: fileUriToPath
	// =========================================================================

	public function testFileUriToPathUnix(): void
	{
		self::assertSame('/path/to/project', $this->call('fileUriToPath', 'file:///path/to/project'));
	}

	public function testFileUriToPathWindowsDrive(): void
	{
		self::assertSame('C:/path/to/project', $this->call('fileUriToPath', 'file:///C:/path/to/project'));
	}

	public function testFileUriToPathNonFileSchemeReturnsNull(): void
	{
		self::assertNull($this->call('fileUriToPath', 'http://example.com'));
	}

	public function testFileUriToPathEncodedSpaces(): void
	{
		self::assertSame('/my path/file.blate', $this->call('fileUriToPath', 'file:///my%20path/file.blate'));
	}

	// =========================================================================
	// Utilities: resolveWorkspaceRoot
	// =========================================================================

	public function testResolveWorkspaceRootFromRootUri(): void
	{
		$root = $this->call('resolveWorkspaceRoot', ['rootUri' => 'file:///my/project']);

		self::assertSame('/my/project', $root);
	}

	public function testResolveWorkspaceRootFromRootPath(): void
	{
		$root = $this->call('resolveWorkspaceRoot', ['rootPath' => '/my/project']);

		self::assertSame('/my/project', $root);
	}

	public function testResolveWorkspaceRootFromWorkspaceFolders(): void
	{
		$root = $this->call('resolveWorkspaceRoot', [
			'workspaceFolders' => [['uri' => 'file:///my/project']],
		]);

		self::assertSame('/my/project', $root);
	}

	public function testResolveWorkspaceRootReturnsNullWhenNoInfo(): void
	{
		self::assertNull($this->call('resolveWorkspaceRoot', []));
	}

	public function testResolveWorkspaceRootPreferRootUriOverRootPath(): void
	{
		// rootUri is preferred over rootPath.
		$root = $this->call('resolveWorkspaceRoot', [
			'rootUri'  => 'file:///from/uri',
			'rootPath' => '/from/path',
		]);

		self::assertSame('/from/uri', $root);
	}

	// =========================================================================
	// Dead Zones (blankDeadZones)
	// =========================================================================

	public function testBlankDeadZonesPreservesLength(): void
	{
		$content = '{# comment #}{foo} plain text {upper(x)}';
		$result  = $this->call('blankDeadZones', $content);

		self::assertSame(\strlen($content), \strlen($result));
	}

	public function testBlankDeadZonesBlanksComment(): void
	{
		$result = $this->call('blankDeadZones', '{# upper(x) #}{foo}');

		// Comment content is replaced with spaces; expression tag is preserved.
		self::assertStringNotContainsString('upper', $result);
		self::assertStringContainsString('foo', $result);
	}

	public function testBlankDeadZonesBlanksInlinePHP(): void
	{
		$result = $this->call('blankDeadZones', '{~ echo upper; ~}{foo}');

		self::assertStringNotContainsString('upper', $result);
		self::assertStringContainsString('foo', $result);
	}

	public function testBlankDeadZonesBlanksRawBlock(): void
	{
		$result = $this->call('blankDeadZones', '{@raw}upper{/raw}{foo}');

		self::assertStringNotContainsString('upper', $result);
		self::assertStringContainsString('foo', $result);
	}

	public function testBlankDeadZonesBlanksPlainText(): void
	{
		// Plain text outside any {} tag is blanked so it does not produce false
		// positives when scanning for helper/global var names.
		$result = $this->call('blankDeadZones', 'plain text{foo}more plain');

		self::assertStringContainsString('foo', $result);
		self::assertStringNotContainsString('plain', $result);
		self::assertStringNotContainsString('more', $result);
	}

	public function testBlankDeadZonesPreservesExpressionContent(): void
	{
		$result = $this->call('blankDeadZones', '{upper(name)}');

		self::assertStringContainsString('upper', $result);
		self::assertStringContainsString('name', $result);
	}

	public function testBlankDeadZonesMultiLineCommentLengthPreserved(): void
	{
		$content = "{# line1\nline2\nline3 #}{ok}";
		$result  = $this->call('blankDeadZones', $content);

		self::assertSame(\strlen($content), \strlen($result));
		self::assertStringContainsString('ok', $result);
	}

	// =========================================================================
	// Context Detection (detectContext)
	// =========================================================================

	public function testDetectContextBlockOpen(): void
	{
		// Cursor right after "{@" -- block-open context.
		self::assertSame('block-open', $this->call('detectContext', '{@if condition}', 2));
	}

	public function testDetectContextBlockClose(): void
	{
		// Cursor right after "{/" -- block-close context.
		self::assertSame('block-close', $this->call('detectContext', '{/if}', 2));
	}

	public function testDetectContextBreakpoint(): void
	{
		// Cursor right after "{:" -- breakpoint context.
		self::assertSame('breakpoint', $this->call('detectContext', '{:else}', 2));
	}

	public function testDetectContextPipe(): void
	{
		// Cursor after a pipe operator inside an open tag.
		self::assertSame('pipe', $this->call('detectContext', '{foo | ', 7));
	}

	public function testDetectContextGeneralInsideTag(): void
	{
		// Inside a plain expression tag with no special prefix.
		self::assertSame('general', $this->call('detectContext', '{foo', 4));
	}

	public function testDetectContextGeneralOutsideTag(): void
	{
		// Cursor is outside any open tag (after a closed brace).
		self::assertSame('general', $this->call('detectContext', '{foo} text', 9));
	}

	public function testDetectContextGeneralNoTag(): void
	{
		// No tag in content at all.
		self::assertSame('general', $this->call('detectContext', 'hello world', 5));
	}

	// =========================================================================
	// Variable Scanning (scanVariables)
	// =========================================================================

	public function testScanVariablesSet(): void
	{
		$vars = $this->call('scanVariables', '{@set myVar = 1}');

		self::assertContains('myVar', $vars);
	}

	public function testScanVariablesEachValueAndKey(): void
	{
		$vars = $this->call('scanVariables', '{@each item:key in list}');

		self::assertContains('item', $vars);
		self::assertContains('key', $vars);
	}

	public function testScanVariablesEachWithValueKeyIndex(): void
	{
		$vars = $this->call('scanVariables', '{@each val:k:idx in items}');

		self::assertContains('val', $vars);
		self::assertContains('k', $vars);
		self::assertContains('idx', $vars);
	}

	public function testScanVariablesRepeat(): void
	{
		$vars = $this->call('scanVariables', '{@repeat 10 as i}');

		self::assertContains('i', $vars);
	}

	public function testScanVariablesCapture(): void
	{
		$vars = $this->call('scanVariables', '{@capture buf}content{/capture}');

		self::assertContains('buf', $vars);
	}

	public function testScanVariablesDeduplicates(): void
	{
		$content = '{@set x = 1}{@set x = 2}';
		$vars    = $this->call('scanVariables', $content);

		self::assertSame(\array_unique($vars), $vars);
		self::assertCount(1, \array_filter($vars, static fn ($v) => 'x' === $v));
	}

	public function testScanVariablesReturnsEmptyForNoDeclarations(): void
	{
		$vars = $this->call('scanVariables', '{foo.bar | upper}');

		self::assertIsArray($vars);
		self::assertEmpty($vars);
	}

	public function testScanVariablesMultipleMixed(): void
	{
		$content = '{@set total = 0}{@each row:i in rows}{@capture buf}x{/capture}';
		$vars    = $this->call('scanVariables', $content);

		self::assertContains('total', $vars);
		self::assertContains('row', $vars);
		self::assertContains('i', $vars);
		self::assertContains('buf', $vars);
	}

	// =========================================================================
	// Diagnostics: buildHelperShadowWarnings
	// =========================================================================

	public function testHelperShadowWarningFires(): void
	{
		$warns = $this->call('buildHelperShadowWarnings', '{upper(name)}');

		self::assertCount(1, $warns);
		self::assertSame('blate.helper.shadow', $warns[0]['code']);
		self::assertSame(2, $warns[0]['severity']);
		self::assertSame('upper', $warns[0]['data']['helperName']);
		self::assertStringContainsString('upper', $warns[0]['message']);
	}

	public function testHelperShadowWarningSuppressedByDollarPrefix(): void
	{
		$warns = $this->call('buildHelperShadowWarnings', '{$upper(name)}');

		self::assertEmpty($warns);
	}

	public function testHelperShadowWarningSuppressedInPipeFilter(): void
	{
		// Pipe-filter position already uses helper-only lookup internally.
		$warns = $this->call('buildHelperShadowWarnings', '{name | upper}');

		self::assertEmpty($warns);
	}

	public function testHelperShadowWarningSuppressedInComment(): void
	{
		$warns = $this->call('buildHelperShadowWarnings', '{# upper(x) #}');

		self::assertEmpty($warns);
	}

	public function testHelperShadowWarningSuppressedInRawBlock(): void
	{
		$warns = $this->call('buildHelperShadowWarnings', '{@raw}upper(x){/raw}');

		self::assertEmpty($warns);
	}

	public function testHelperShadowWarningSuppressedInInlinePHP(): void
	{
		$warns = $this->call('buildHelperShadowWarnings', '{~ $x = upper($y); ~}');

		self::assertEmpty($warns);
	}

	public function testHelperShadowWarningRangeIsAccurate(): void
	{
		// '{upper(x)}' -- 'upper' starts at byte 1, ends at byte 6.
		$warns = $this->call('buildHelperShadowWarnings', '{upper(x)}');

		self::assertCount(1, $warns);
		self::assertSame(['line' => 0, 'character' => 1], $warns[0]['range']['start']);
		self::assertSame(['line' => 0, 'character' => 6], $warns[0]['range']['end']);
	}

	// =========================================================================
	// Diagnostics: buildGlobalVarShadowWarnings
	// =========================================================================

	public function testGlobalVarShadowWarningFires(): void
	{
		// BRACE_OPEN is a built-in global var; bare access triggers a warning.
		$warns = $this->call('buildGlobalVarShadowWarnings', '{BRACE_OPEN}');

		self::assertCount(1, $warns);
		self::assertSame('blate.global.shadow', $warns[0]['code']);
		self::assertSame(2, $warns[0]['severity']);
		self::assertSame('BRACE_OPEN', $warns[0]['data']['varName']);
		self::assertStringContainsString('BRACE_OPEN', $warns[0]['message']);
	}

	public function testGlobalVarShadowWarningSuppressedByGlobalRef(): void
	{
		// Qualified access via $global. never shadows.
		$warns = $this->call('buildGlobalVarShadowWarnings', '{$global.BRACE_OPEN}');

		self::assertEmpty($warns);
	}

	public function testGlobalVarShadowWarningSuppressedByDotPrefix(): void
	{
		// Property chain access (.BRACE_OPEN) is not a top-level var access.
		$warns = $this->call('buildGlobalVarShadowWarnings', '{foo.BRACE_OPEN}');

		self::assertEmpty($warns);
	}

	public function testGlobalVarShadowWarningSuppressedInComment(): void
	{
		$warns = $this->call('buildGlobalVarShadowWarnings', '{# BRACE_OPEN #}');

		self::assertEmpty($warns);
	}

	public function testGlobalVarShadowWarningSuppressedInRawBlock(): void
	{
		$warns = $this->call('buildGlobalVarShadowWarnings', '{@raw}BRACE_OPEN{/raw}');

		self::assertEmpty($warns);
	}

	public function testGlobalVarShadowWarningSuppressedInInlinePHP(): void
	{
		$warns = $this->call('buildGlobalVarShadowWarnings', '{~ echo BRACE_OPEN; ~}');

		self::assertEmpty($warns);
	}

	public function testGlobalVarShadowWarningRangeIsAccurate(): void
	{
		// '{BRACE_OPEN}' -- 'BRACE_OPEN' starts at byte 1, ends at byte 11.
		$warns = $this->call('buildGlobalVarShadowWarnings', '{BRACE_OPEN}');

		self::assertCount(1, $warns);
		self::assertSame(['line' => 0, 'character' => 1], $warns[0]['range']['start']);
		self::assertSame(['line' => 0, 'character' => 11], $warns[0]['range']['end']);
	}

	// =========================================================================
	// Diagnostics: buildGlobalRefUnknownErrors
	// =========================================================================

	public function testGlobalRefUnknownErrorFires(): void
	{
		$errors = $this->call('buildGlobalRefUnknownErrors', '{$global.DOES_NOT_EXIST_9999}');

		self::assertCount(1, $errors);
		self::assertSame('blate.global.unknown', $errors[0]['code']);
		self::assertSame(1, $errors[0]['severity']);
		self::assertStringContainsString('DOES_NOT_EXIST_9999', $errors[0]['message']);
	}

	public function testGlobalRefUnknownErrorNotFiredForRegistered(): void
	{
		$errors = $this->call('buildGlobalRefUnknownErrors', '{$global.BRACE_OPEN}');

		self::assertEmpty($errors);
	}

	public function testGlobalRefUnknownErrorSuppressedInComment(): void
	{
		$errors = $this->call('buildGlobalRefUnknownErrors', '{# $global.NOT_REAL #}');

		self::assertEmpty($errors);
	}

	public function testGlobalRefUnknownErrorSuppressedInRawBlock(): void
	{
		$errors = $this->call('buildGlobalRefUnknownErrors', '{@raw}{$global.NOT_REAL}{/raw}');

		self::assertEmpty($errors);
	}

	public function testGlobalRefUnknownErrorRangePointsAtVarName(): void
	{
		// '{$global.UNKNOWN9}' -- 'UNKNOWN9' starts at byte 9, ends at byte 17.
		$errors = $this->call('buildGlobalRefUnknownErrors', '{$global.UNKNOWN9}');

		self::assertCount(1, $errors);
		self::assertSame(['line' => 0, 'character' => 9], $errors[0]['range']['start']);
		self::assertSame(['line' => 0, 'character' => 17], $errors[0]['range']['end']);
	}

	// =========================================================================
	// Diagnostics: buildDollarHelperUnknownErrors
	// =========================================================================

	public function testDollarHelperUnknownErrorFires(): void
	{
		$errors = $this->call('buildDollarHelperUnknownErrors', '{$notRegistered9999(x)}');

		self::assertCount(1, $errors);
		self::assertSame('blate.helper.unknown', $errors[0]['code']);
		self::assertSame(1, $errors[0]['severity']);
		self::assertStringContainsString('notRegistered9999', $errors[0]['message']);
	}

	public function testDollarHelperUnknownNotFiredForRegisteredHelper(): void
	{
		// 'upper' is a built-in helper; {$upper(x)} is valid.
		$errors = $this->call('buildDollarHelperUnknownErrors', '{$upper(x)}');

		self::assertEmpty($errors);
	}

	public function testDollarHelperUnknownNotFiredForGlobalChainHead(): void
	{
		// $global is the global-vars-layer reference, not a helper name.
		$errors = $this->call('buildDollarHelperUnknownErrors', '{$global.BRACE_OPEN}');

		self::assertEmpty($errors);
	}

	public function testDollarHelperUnknownNotFiredForDoubleDollar(): void
	{
		// $$ is the DataContext reference. '$$ref(' would have the second $
		// suppress the match via the negative lookbehind.
		$errors = $this->call('buildDollarHelperUnknownErrors', '{$$notHelper(x)}');

		self::assertEmpty($errors);
	}

	public function testDollarHelperUnknownSuppressedInComment(): void
	{
		$errors = $this->call('buildDollarHelperUnknownErrors', '{# $notRegistered(x) #}');

		self::assertEmpty($errors);
	}

	public function testDollarHelperUnknownSuppressedInRawBlock(): void
	{
		$errors = $this->call('buildDollarHelperUnknownErrors', '{@raw}{$notRegistered(x)}{/raw}');

		self::assertEmpty($errors);
	}

	public function testDollarHelperUnknownSuppressedInInlinePHP(): void
	{
		$errors = $this->call('buildDollarHelperUnknownErrors', '{~ $notRegistered(x); ~}');

		self::assertEmpty($errors);
	}

	public function testDollarHelperUnknownRangePointsAtHelperName(): void
	{
		// '{$xyzzy9999(v)}': '$' at byte 1, name 'xyzzy9999' starts at byte 2.
		$errors = $this->call('buildDollarHelperUnknownErrors', '{$xyzzy9999(v)}');

		self::assertCount(1, $errors);
		self::assertSame(['line' => 0, 'character' => 2], $errors[0]['range']['start']);
		self::assertSame(['line' => 0, 'character' => 11], $errors[0]['range']['end']);
	}

	// =========================================================================
	// Completions: specialRefCompletions
	// =========================================================================

	public function testSpecialRefCompletionsReturnsTwoItems(): void
	{
		$items  = $this->call('specialRefCompletions');
		$labels = \array_column($items, 'label');

		self::assertCount(2, $items);
		self::assertContains('$$', $labels);
		self::assertContains('$global', $labels);
	}

	public function testSpecialRefCompletionsHaveDocumentation(): void
	{
		$items = $this->call('specialRefCompletions');

		foreach ($items as $item) {
			self::assertArrayHasKey('documentation', $item, 'Item "' . $item['label'] . '" must have documentation');
		}
	}

	// =========================================================================
	// Completions: globalVarCompletions
	// =========================================================================

	public function testGlobalVarCompletionsIncludesBuiltins(): void
	{
		$items  = $this->call('globalVarCompletions');
		$labels = \array_column($items, 'label');

		self::assertContains('BRACE_OPEN', $labels);
		self::assertContains('BRACE_CLOSE', $labels);
		self::assertContains('BLATE_VERSION', $labels);
		self::assertContains('BLATE_VERSION_NAME', $labels);
	}

	public function testGlobalVarCompletionsIncludesDescriptionWhenSet(): void
	{
		$items   = $this->call('globalVarCompletions');
		$byLabel = [];

		foreach ($items as $item) {
			$byLabel[$item['label']] = $item;
		}

		// BRACE_OPEN has a description set in bootstrap.php.
		self::assertArrayHasKey('BRACE_OPEN', $byLabel);
		self::assertArrayHasKey('documentation', $byLabel['BRACE_OPEN']);
		self::assertStringContainsString('literal', \strtolower((string) $byLabel['BRACE_OPEN']['documentation']));
	}

	public function testGlobalVarCompletionsItemsHaveRequiredFields(): void
	{
		$items = $this->call('globalVarCompletions');

		foreach ($items as $item) {
			self::assertArrayHasKey('label', $item);
			self::assertArrayHasKey('kind', $item);
			self::assertArrayHasKey('insertText', $item);
		}
	}

	// =========================================================================
	// Completions: helperCompletions
	// =========================================================================

	public function testHelperCompletionsIncludesBuiltinHelpers(): void
	{
		$items  = $this->call('helperCompletions');
		$labels = \array_column($items, 'label');

		self::assertContains('upper', $labels);
		self::assertContains('lower', $labels);
	}

	public function testHelperCompletionsExcludesDollarPrefixedDuplicates(): void
	{
		// The $-prefixed variants (used for force-helper syntax) must not appear.
		$items = $this->call('helperCompletions');

		foreach ($items as $item) {
			self::assertFalse(
				\str_starts_with($item['label'], '$'),
				'Helper completion label "' . $item['label'] . '" must not start with $'
			);
		}
	}

	public function testHelperCompletionsItemsHaveRequiredFields(): void
	{
		$items = $this->call('helperCompletions');

		foreach ($items as $item) {
			self::assertArrayHasKey('label', $item);
			self::assertArrayHasKey('kind', $item);
			self::assertArrayHasKey('insertText', $item);
		}
	}

	// =========================================================================
	// Completions: buildCompletionItems dispatch
	// =========================================================================

	public function testBuildCompletionItemsGeneralContextIncludesAllTypes(): void
	{
		// General context: helpers + globals + template variables + special refs.
		$items  = $this->call('buildCompletionItems', '{foo', 4);
		$labels = \array_column($items, 'label');

		self::assertContains('upper', $labels);      // helper
		self::assertContains('BRACE_OPEN', $labels); // global var
		self::assertContains('$$', $labels);          // special ref
		self::assertContains('$global', $labels);    // special ref
	}

	public function testBuildCompletionItemsPipeContextIncludesOnlyHelpers(): void
	{
		// Pipe context: only helpers are valid on the right side of |.
		$items  = $this->call('buildCompletionItems', '{foo | ', 7);
		$labels = \array_column($items, 'label');

		self::assertContains('upper', $labels);
		self::assertNotContains('BRACE_OPEN', $labels);
		self::assertNotContains('$$', $labels);
		self::assertNotContains('$global', $labels);
	}

	public function testBuildCompletionItemsBlockOpenContextIncludesBlocks(): void
	{
		// Block-open context: block names with snippets.
		$items  = $this->call('buildCompletionItems', '{@', 2);
		$labels = \array_column($items, 'label');

		self::assertContains('if', $labels);
		self::assertContains('each', $labels);
		self::assertContains('set', $labels);
		self::assertNotContains('upper', $labels);      // no helpers in block-open
		self::assertNotContains('BRACE_OPEN', $labels); // no globals in block-open
	}

	public function testBuildCompletionItemsIncludesTemplateVariables(): void
	{
		// Variables declared in the template appear in general completions.
		$content = '{@set myLocalVar = 1}{myL';
		$items   = $this->call('buildCompletionItems', $content, \strlen($content));
		$labels  = \array_column($items, 'label');

		self::assertContains('myLocalVar', $labels);
	}

	// =========================================================================
	// Hover (buildHover)
	// =========================================================================

	public function testHoverOnGlobalRefReturnsMarkdown(): void
	{
		// Cursor on 'global' in '{$global.BRACE_OPEN}':
		// offset 0={  1=$  2=g  3=l  4=o  5=b  6=a  7=l  8=.  9=B ...
		$hover = $this->call('buildHover', '{$global.BRACE_OPEN}', 2);

		self::assertNotNull($hover);
		self::assertSame('markdown', $hover['contents']['kind']);
		self::assertStringContainsString('$global', $hover['contents']['value']);
		// Hover lists the registered globals.
		self::assertStringContainsString('BRACE_OPEN', $hover['contents']['value']);
	}

	public function testHoverOnDataContextRefReturnsMarkdown(): void
	{
		// $$ hover fires when a word character immediately follows $$:
		// '{$$ctx}': offset 0={ 1=$ 2=$ 3=c 4=t 5=x 6=}
		// Hovering at offset 3 (on 'c') triggers the $$ check.
		$hover = $this->call('buildHover', '{$$ctx}', 3);

		self::assertNotNull($hover);
		self::assertStringContainsString('$$', $hover['contents']['value']);
		self::assertStringContainsString('DataContext', $hover['contents']['value']);
	}

	public function testHoverOnStaticGlobalVarShowsValue(): void
	{
		// Cursor on 'BRACE_OPEN' in '{BRACE_OPEN}':
		// offset 0={ 1=B 2=R 3=A 4=C 5=E 6=_ 7=O 8=P 9=E 10=N 11=}
		$hover = $this->call('buildHover', '{BRACE_OPEN}', 1);

		self::assertNotNull($hover);
		$md = $hover['contents']['value'];
		self::assertStringContainsString('BRACE_OPEN', $md);
		self::assertStringContainsString('Built-in variable', $md);
		// The value of BRACE_OPEN is '{', shown as "{" in repr.
		self::assertStringContainsString('"{"', $md);
	}

	public function testHoverOnStaticGlobalVarIncludesDescription(): void
	{
		$hover = $this->call('buildHover', '{BRACE_OPEN}', 1);

		self::assertNotNull($hover);
		// bootstrap.php registers BRACE_OPEN with a description about literal brace.
		self::assertStringContainsString('literal', \strtolower($hover['contents']['value']));
	}

	public function testHoverOnComputedGlobalVarShowsComputedLabel(): void
	{
		Blate::registerComputedGlobalVar('BLATE_LSP_TEST_COMPUTED', static fn () => 'test', ['editable' => true]);

		// '{BLATE_LSP_TEST_COMPUTED}': cursor at offset 1 (on 'B').
		$hover = $this->call('buildHover', '{BLATE_LSP_TEST_COMPUTED}', 1);

		self::assertNotNull($hover);
		self::assertStringContainsString('(computed)', $hover['contents']['value']);
		self::assertStringContainsString('BLATE_LSP_TEST_COMPUTED', $hover['contents']['value']);
	}

	public function testHoverOnHelperReturnsMarkdown(): void
	{
		// Cursor on 'upper' in '{upper(name)}': offset 1 on 'u'.
		$hover = $this->call('buildHover', '{upper(name)}', 1);

		self::assertNotNull($hover);
		self::assertStringContainsString('upper', $hover['contents']['value']);
	}

	public function testHoverOnBlockReturnsMarkdown(): void
	{
		// Cursor on 'if' in '{@if condition}':
		// offset 0={ 1=@ 2=i 3=f 4=space ...
		$hover = $this->call('buildHover', '{@if condition}', 2);

		self::assertNotNull($hover);
		self::assertStringContainsString('@if', $hover['contents']['value']);
	}

	public function testHoverOnBlockCloseReturnsMarkdown(): void
	{
		// Cursor on 'if' in '{/if}':
		// offset 0={ 1=/ 2=i 3=f 4=}
		$hover = $this->call('buildHover', '{/if}', 2);

		self::assertNotNull($hover);
		self::assertStringContainsString('@if', $hover['contents']['value']);
	}

	public function testHoverOnUnknownNameReturnsNull(): void
	{
		// 'xyzzy_unknown_9999' is not a helper, block, or global.
		$hover = $this->call('buildHover', '{xyzzy_unknown_9999}', 1);

		self::assertNull($hover);
	}

	public function testHoverOnNonWordCharacterReturnsNull(): void
	{
		// Cursor on '$' -- wordAt returns '' -> null.
		$hover = $this->call('buildHover', '{$upper(x)}', 1);

		self::assertNull($hover);
	}

	// =========================================================================
	// Rename (buildRenameEdits)
	// =========================================================================

	public function testBuildRenameEditsFindsAllOccurrences(): void
	{
		// 'foo' appears twice: once as {foo} and once in {foo.bar}.
		$content = '{foo} and {foo.bar} and {baz}';
		$edits   = $this->call('buildRenameEdits', $content, 1, 'qux');

		self::assertNotNull($edits);
		self::assertCount(2, $edits);

		foreach ($edits as $edit) {
			self::assertSame('qux', $edit['newText']);
		}
	}

	public function testBuildRenameEditsReturnsNullForEmptyWord(): void
	{
		// Cursor on '$' which is not a word character -> null.
		$edits = $this->call('buildRenameEdits', '{$upper(x)}', 1, 'newName');

		self::assertNull($edits);
	}

	public function testBuildRenameEditsRangeIsAccurate(): void
	{
		// '{foo}': 'foo' occupies bytes 1..3 (length 3), range end is byte 4.
		$edits = $this->call('buildRenameEdits', '{foo}', 1, 'bar');

		self::assertNotNull($edits);
		self::assertCount(1, $edits);
		self::assertSame(['line' => 0, 'character' => 1], $edits[0]['range']['start']);
		self::assertSame(['line' => 0, 'character' => 4], $edits[0]['range']['end']);
	}

	public function testBuildRenameEditsRespectsWordBoundary(): void
	{
		// 'foo' must not match inside 'foobar'.
		$content = '{foo} {foobar}';
		$edits   = $this->call('buildRenameEdits', $content, 1, 'qux');

		self::assertNotNull($edits);
		self::assertCount(1, $edits);
		self::assertSame('qux', $edits[0]['newText']);
	}

	public function testBuildRenameEditsMultiLine(): void
	{
		$content = "{foo}\n{bar}\n{foo}";
		$edits   = $this->call('buildRenameEdits', $content, 1, 'renamed');

		self::assertNotNull($edits);
		self::assertCount(2, $edits);
		// First occurrence: line 0, chars 1..4
		self::assertSame(['line' => 0, 'character' => 1], $edits[0]['range']['start']);
		// Second occurrence: line 2, chars 1..4
		self::assertSame(['line' => 2, 'character' => 1], $edits[1]['range']['start']);
	}

	/**
	 * Calls a private method on the server instance via reflection.
	 */
	private function call(string $method, mixed ...$args): mixed
	{
		$m = $this->ref->getMethod($method);
		$m->setAccessible(true);

		return $m->invoke($this->server, ...$args);
	}
}
