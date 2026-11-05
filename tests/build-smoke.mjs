// Optional integration smoke test: pass a path to Vite's Node API module.
// Example from the parent project:
// node lhaamed/ThemeManager/tests/build-smoke.mjs node_modules/vite/dist/node/index.js
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { pathToFileURL } from 'node:url';
import assert from 'node:assert/strict';
import themeManagerAssets from '../resources/vite-plugin.mjs';

const { build } = await import(pathToFileURL(resolve(process.argv[2])).href);
const laravel = process.argv[3] ? (await import(pathToFileURL(resolve(process.argv[3])).href)).default : null;
// Keep this fixture under the project so Vite can resolve its Sass installation.
const root = mkdtempSync(join(process.cwd(), '.theme-build-'));
try {
    mkdirSync(join(root, 'resources/views/themes/ocean/assets/css'), { recursive: true });
    mkdirSync(join(root, 'resources/views/themes/ocean/assets/js'), { recursive: true });
    writeFileSync(join(root, 'resources/views/themes/ocean/assets/css/ocean-styles.scss'), readFileSync(new URL('../stubs/styles.stub', import.meta.url)));
    writeFileSync(join(root, 'resources/views/themes/ocean/assets/js/ocean-scripts.js'), readFileSync(new URL('../stubs/scripts.stub', import.meta.url)));
    writeFileSync(join(root, 'app.js'), 'console.log("existing application entry");');
    const appInput = join(root, 'app.js');
    await build({ root, configFile: false, logLevel: 'warn', plugins: [
        ...(laravel ? [laravel({ input: [appInput] })] : []),
        themeManagerAssets(),
    ], build: { outDir: 'dist', manifest: 'manifest.json', rollupOptions: laravel ? {} : { input: [appInput] } } });
    const manifest = JSON.parse(readFileSync(join(root, 'dist/manifest.json'), 'utf8'));
    assert.ok(manifest['app.js']);
    assert.ok(manifest['resources/views/themes/ocean/assets/css/ocean-styles.scss']);
    assert.ok(manifest['resources/views/themes/ocean/assets/js/ocean-scripts.js']);
    console.log('Vite build passed: existing app entry, theme SCSS and theme JS are present in the manifest.');
} finally { rmSync(root, { recursive: true, force: true }); }
