import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

const tokensPath = path.join(rootDir, 'resources', 'design', 'tokens.json');
const outputPath = path.join(rootDir, 'resources', 'css', 'design-tokens.css');

const tokens = JSON.parse(readFileSync(tokensPath, 'utf8'));

const lines = [':root {'];

const flattenTokens = (value, parts = []) => {
    if (typeof value === 'string') {
        lines.push(`  --${parts.join('-')}: ${value};`);
        return;
    }

    for (const [key, nestedValue] of Object.entries(value)) {
        flattenTokens(nestedValue, [...parts, key]);
    }
};

for (const [group, value] of Object.entries(tokens)) {
    flattenTokens(value, [group]);
}

lines.push('}');
lines.push('');

mkdirSync(path.dirname(outputPath), { recursive: true });
writeFileSync(outputPath, lines.join('\n'), 'utf8');

console.log(`Design tokens synced: ${path.relative(rootDir, outputPath)}`);
