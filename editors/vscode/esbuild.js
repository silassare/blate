// esbuild bundler for the Blate VS Code extension.
// Bundles src/extension.ts + all dependencies into a single out/extension.js.
// After the build, node_modules/ is NOT needed at runtime.

const esbuild = require('esbuild');

const watch = process.argv.includes('--watch');
const minify = process.argv.includes('--minify');

/** @type {import('esbuild').BuildOptions} */
const config = {
	entryPoints: ['src/extension.ts'],
	bundle: true,
	outfile: 'out/extension.js',
	platform: 'node', // target VS Code's Node.js extension host
	format: 'cjs',
	// 'vscode' is provided by the extension host at runtime - never bundle it.
	external: ['vscode'],
	sourcemap: !minify,
	minify,
};

if (watch) {
	esbuild.context(config).then((ctx) => {
		ctx.watch();
		console.log('[esbuild] watching...');
	});
} else {
	esbuild
		.build(config)
		.then(() => {
			console.log('[esbuild] built out/extension.js');
		})
		.catch(() => process.exit(1));
}
