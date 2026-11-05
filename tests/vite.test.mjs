import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';
import themeManagerAssets, { themeInputs } from '../resources/vite-plugin.mjs';

test('theme discovery preserves existing string, array and object inputs', () => {
    const root = mkdtempSync(join(tmpdir(), 'theme-vite-'));
    try {
        for (const name of ['ocean', 'forest']) {
            for (const dir of ['css', 'js']) mkdirSync(join(root, 'resources/views/themes', name, 'assets', dir), { recursive: true });
            writeFileSync(join(root, 'resources/views/themes', name, 'assets/css', `${name}-styles.scss`), 'body { color: teal; }');
            writeFileSync(join(root, 'resources/views/themes', name, 'assets/js', `${name}-scripts.js`), '// starter');
        }
        assert.equal(themeInputs(root, 'resources/views/themes').length, 4);
        for (const input of ['app.js', ['app.js'], { app: 'app.js' }]) {
            const config = { root, build: { rollupOptions: { input } } };
            const plugin = themeManagerAssets();
            plugin.config(config);
            const entries = Object.values(config.build.rollupOptions.input);
            assert.ok(entries.includes('app.js'));
            assert.equal(entries.length, 5);
            plugin.config(config);
            assert.equal(Object.values(config.build.rollupOptions.input).length, 5);
        }
        const config = { root };
        themeManagerAssets().config(config);
        assert.equal(config.build.rollupOptions.input.length, 4);
        assert.deepEqual(themeInputs(root, 'missing'), []);
    } finally { rmSync(root, { recursive: true, force: true }); }
});

test('starter script handles both early inline and late module execution', () => {
    const code = readFileSync(new URL('../stubs/scripts.stub', import.meta.url), 'utf8').replaceAll('{{name}}', 'ocean');
    for (const readyState of ['loading', 'complete']) {
        let ready, click;
        const status = { classList: { add(value) { this.value = value; } } };
        const button = { dataset: {}, addEventListener(event, handler) { assert.equal(event, 'click'); click = handler; } };
        const document = {
            readyState,
            querySelector(selector) { return selector.includes('button') ? button : status; },
            addEventListener(event, handler) { assert.equal(event, 'DOMContentLoaded'); ready = handler; },
        };
        vm.runInNewContext(code, { document });
        if (ready) ready();
        click();
        assert.equal(status.textContent, 'JavaScript is working. Welcome to ocean!');
        assert.equal(button.textContent, 'Scripts connected');
        assert.equal(status.classList.value, 'is-success');
    }
});
