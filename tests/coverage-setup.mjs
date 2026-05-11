import fs from 'fs';
import path from 'path';

export default function globalSetup() {
    const coverageDir = path.resolve(import.meta.dirname, 'coverage');
    const dbPath = path.join(coverageDir, 'test.sqlite');

    fs.mkdirSync(coverageDir, { recursive: true });

    if (!fs.existsSync(dbPath)) {
        fs.writeFileSync(dbPath, '');
    }
}
