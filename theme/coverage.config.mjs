import { defineConfig } from '@playwright/test';
import path from 'path';

const baseURL = process.env.BASE_URL || 'http://localhost:8000';
const theme = process.env.THEME || '';
const rootDir = path.resolve(import.meta.dirname, '..');
const appKey = 'base64:dGVzdGtleWZvcmNvdmVyYWdlMTIzNDU2Nzg5MDEyMzQ=';
const dbPath = path.join(import.meta.dirname, 'coverage', 'test.sqlite');

export default defineConfig({
    testDir: path.join(import.meta.dirname, 'tests'),
    testMatch: 'css-coverage.spec.mjs',
    timeout: 300_000,
    use: { baseURL },
    webServer: process.env.BASE_URL ? undefined : {
        command: [
            'ENV_FILE=vendor/orchestra/testbench-core/laravel/.env;',
            'cp "$ENV_FILE" "$ENV_FILE.bak" 2>/dev/null;',
            'mkdir -p theme/coverage && touch theme/coverage/test.sqlite;',
            'rm -rf vendor/orchestra/testbench-core/laravel/storage/framework/cache/data/*;',
            `printf 'APP_KEY=${appKey}\\nDB_CONNECTION=sqlite\\nDB_DATABASE=${dbPath}\\nCMS_THEME=${theme}\\nSCOUT_DRIVER=cms\\n' > "$ENV_FILE";`,
            'php vendor/bin/testbench migrate --database=sqlite 2>/dev/null;',
            'php vendor/bin/testbench vendor:publish --tag=cms-theme --force 2>/dev/null;',
            'php vendor/bin/testbench db:seed --class=DemoSeeder 2>/dev/null;',
            'php vendor/bin/testbench serve --no-reload',
        ].join(' '),
        cwd: rootDir,
        url: baseURL,
        reuseExistingServer: true,
        timeout: 60_000,
        env: {
            APP_KEY: appKey,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: dbPath,
            CMS_THEME: theme,
            SCOUT_DRIVER: 'cms',
        },
        stderr: 'pipe',
        stdout: 'pipe',
    },
    globalSetup: './coverage-setup.mjs',
    globalTeardown: './coverage-teardown.mjs',
});
