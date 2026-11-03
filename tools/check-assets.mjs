import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { gzipSync } from 'node:zlib';

const root = resolve(import.meta.dirname, '..');
const manifest = JSON.parse(readFileSync(resolve(root, 'public/build/manifest.json'), 'utf8'));
let failed = false;
const seen = new Set();
for (const entry of Object.values(manifest)) {
  for (const file of [entry.file, ...(entry.css ?? [])]) {
    if (!file || seen.has(file)) continue;
    seen.add(file);
    const budget = file.endsWith('.js') ? 80 * 1024 : file.endsWith('.css') ? 40 * 1024 : null;
    if (!budget) continue;
    const size = gzipSync(readFileSync(resolve(root, 'public/build', file))).length;
    const ok = size <= budget;
    console.log(`${ok ? 'PASS' : 'FAIL'} ${file}: ${size} gzip bytes / ${budget} budget`);
    failed ||= !ok;
  }
}
if (!seen.size) throw Error('No built assets found; run npm run build first.');
process.exitCode = failed ? 1 : 0;
