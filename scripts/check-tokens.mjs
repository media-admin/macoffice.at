#!/usr/bin/env node
// Prüft das Theme auf feste Farbwerte. Erlaubt sind sie nur in den Token-Definitionen
// (assets/src/scss/abstracts/_variables.scss). Überall sonst gilt: Tokens verwenden.
//   npm run lint:tokens
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const root = 'cms/wp-content/themes/macoffice/assets/src/scss';
const allowed = new Set([join(root, 'abstracts/_variables.scss')]);
const patterns = [
  [/#[0-9a-fA-F]{3,8}\b(?!\s*\})/, 'Hex-Farbe'],
  [/\brgba?\(/, 'rgb()/rgba()'],
  [/\bhsla?\(/, 'hsl()/hsla()'],
  [/\$color-[a-z0-9-]+/, 'statisches SCSS-Farbtoken (CSS-Variable var(--color-*) verwenden)'],
];

const files = [];
(function walk(dir) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    statSync(p).isDirectory() ? walk(p) : p.endsWith('.scss') && files.push(p);
  }
})(root);

let errors = 0;
for (const file of files) {
  if (allowed.has(file)) continue;
  readFileSync(file, 'utf8').split('\n').forEach((raw, i) => {
    const line = raw.replace(/\/\/.*$/, '').replace(/#\{[^}]*\}/g, '');
    for (const [re, label] of patterns) {
      if (re.test(line)) { console.error(`${relative('.', file)}:${i + 1}  ${label}: ${raw.trim()}`); errors++; break; }
    }
  });
}
if (errors) { console.error(`\n${errors} feste Farbwerte gefunden. Bitte Tokens aus _variables.scss verwenden.`); process.exit(1); }
console.log('Token-Check ok: keine festen Farbwerte im Theme-SCSS.');
