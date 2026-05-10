import fs from 'fs';
import path from 'path';

export default function globalTeardown() {
    const envPath = path.resolve(import.meta.dirname, '..', 'vendor/orchestra/testbench-core/laravel/.env');
    const backupPath = envPath + '.bak';

    if (fs.existsSync(backupPath)) {
        fs.copyFileSync(backupPath, envPath);
        fs.unlinkSync(backupPath);
    }
}
