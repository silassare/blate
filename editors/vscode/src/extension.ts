/**
 * Copyright (c) 2021-present, Emile Silas Sare
 *
 * This file is part of Blate package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import * as path from 'path';
import * as vscode from 'vscode';
import {
	LanguageClient,
	LanguageClientOptions,
	ServerOptions,
	TransportKind,
} from 'vscode-languageclient/node';

let client: LanguageClient | undefined;

export function activate(context: vscode.ExtensionContext): void {
	const config = vscode.workspace.getConfiguration('blate');
	const php = config.get<string>('phpExecutable', 'php');

	// The PHP server script lives at ../lsp/server.php relative to this extension root.
	const serverScript = context.asAbsolutePath(
		path.join('..', 'lsp', 'server.php')
	);

	const serverOptions: ServerOptions = {
		command: php,
		args: [serverScript],
		transport: TransportKind.stdio,
	};

	const clientOptions: LanguageClientOptions = {
		documentSelector: [{ scheme: 'file', language: 'blate' }],
		synchronize: {
			// Re-validate on save even when the content did not change.
			fileEvents: vscode.workspace.createFileSystemWatcher('**/*.blate'),
		},
	};

	client = new LanguageClient(
		'blate',
		'Blate Language Server',
		serverOptions,
		clientOptions
	);

	client.start();
	context.subscriptions.push(client);
}

export function deactivate(): Thenable<void> | undefined {
	return client?.stop();
}
