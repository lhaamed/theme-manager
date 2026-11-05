// @lhaamed/theme-manager — installed by theme:make
import { existsSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';

export function themeInputs(root, directory) {
    const themes = resolve(root, directory);
    if (!existsSync(themes)) return [];
    return readdirSync(themes, { withFileTypes: true })
        .filter((entry) => entry.isDirectory() && /^[a-z][a-z0-9_-]*$/.test(entry.name))
        .sort((a, b) => a.name.localeCompare(b.name))
        .flatMap(({ name }) => [
            resolve(themes, name, 'assets/css', `${name}-styles.scss`),
            resolve(themes, name, 'assets/js', `${name}-scripts.js`),
        ])
        .filter((file) => existsSync(file))
        .map((file) => file.replaceAll('\\', '/'));
}

export default function themeManagerAssets({ directory = 'resources/views/themes' } = {}) {
    return {
        name: 'lhaamed-theme-manager-assets',
        enforce: 'post',
        config(config) {
            const root = resolve(config.root ?? process.cwd());
            const entries = themeInputs(root, directory);
            const current = config.build?.rollupOptions?.input;
            const input = current && !Array.isArray(current) && typeof current === 'object'
                ? { ...current, ...Object.fromEntries(entries.map((entry) => [entry, entry])) }
                : [...new Set([...(Array.isArray(current) ? current : current ? [current] : []), ...entries])];
            // Mutate this array so Vite's deep merge does not concatenate it
            // with the old array and duplicate existing application entries.
            config.build ??= {};
            config.build.rollupOptions ??= {};
            config.build.rollupOptions.input = input;
        },
    };
}
