#!/usr/bin/env node
/**
 * First-load JS budget enforcement.
 *
 * The target device is a Rs 8,000 Android on 3G. Vol 2 ch.1.3 sets a hard budget of
 * 100 KB gzipped for first-load JavaScript, and this script is what makes that a real
 * constraint rather than an aspiration: it fails the build.
 *
 * Every kilobyte here is a kilobyte a student pays for out of a limited data pack, on a
 * phone where parse time costs more than transfer time. A budget that is only checked
 * manually is a budget that is exceeded within two sprints.
 */

import { readdir, readFile, stat } from 'node:fs/promises';
import { join, extname } from 'node:path';
import { gzipSize } from 'gzip-size';

const BUILD_DIR = 'public/build/assets';
const BUDGET_BYTES = 100 * 1024;

async function jsFiles(dir) {
    let entries;

    try {
        entries = await readdir(dir, { withFileTypes: true });
    } catch {
        console.error(`No build output at ${dir}. Run "npm run build" first.`);
        process.exit(1);
    }

    const files = [];

    for (const entry of entries) {
        const path = join(dir, entry.name);

        if (entry.isDirectory()) {
            files.push(...(await jsFiles(path)));
        } else if (extname(entry.name) === '.js') {
            files.push(path);
        }
    }

    return files;
}

const files = await jsFiles(BUILD_DIR);
const report = [];
let total = 0;

for (const file of files) {
    const contents = await readFile(file);
    const size = await gzipSize(contents);
    const raw = (await stat(file)).size;

    total += size;
    report.push({ file, gzip: size, raw });
}

report.sort((a, b) => b.gzip - a.gzip);

const kb = (n) => `${(n / 1024).toFixed(1)} KB`;

console.log('\nFirst-load JS, gzipped:\n');
for (const { file, gzip, raw } of report) {
    console.log(`  ${kb(gzip).padStart(9)}  (raw ${kb(raw).padStart(9)})  ${file}`);
}

console.log(`\n  ${'─'.repeat(48)}`);
console.log(`  ${kb(total).padStart(9)}  total against a ${kb(BUDGET_BYTES)} budget\n`);

if (total > BUDGET_BYTES) {
    console.error(
        `FAIL: over budget by ${kb(total - BUDGET_BYTES)}.\n\n` +
        `This is not a soft warning. Before raising the budget, try:\n` +
        `  - lazy-loading the Livewire components below the fold\n` +
        `  - checking whether a dependency was pulled in for one helper\n` +
        `  - moving work to the server, which is where it belongs in this stack\n`
    );
    process.exit(1);
}

console.log('PASS: within budget.\n');
