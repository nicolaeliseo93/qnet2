#!/usr/bin/env node
/**
 * code-guard — PostToolUse (Edit|Write|MultiEdit)
 * Enforcement deterministico delle convenzioni meccanicamente verificabili:
 *   - EMOJI nei file di codice  → BLOCCA (exit 2)
 *   - FILE SIZE                 → > hard (500) BLOCCA; > soft (300) avvisa
 * Lavora sul file su disco (PostToolUse: l'edit è già applicato).
 *
 * Disattivazione / tuning via env:
 *   CODE_GUARD=off          disattiva tutto
 *   CODE_GUARD_EMOJI=off    disattiva il solo check emoji
 *   MAX_LINES_HARD=500      soglia di blocco (default 500)
 *   MAX_LINES_SOFT=300      soglia di avviso (default 300); =0 disattiva l'avviso
 *   MAX_LINES_SOFT_BLOCK=1  rende BLOCCANTE anche la soglia soft (300)
 */

'use strict';

const fs = require('fs');
const path = require('path');

const MAX_STDIN = 1024 * 1024;
const CODE_EXT = new Set(['.php', '.ts', '.tsx', '.js', '.jsx', '.mjs', '.cjs', '.vue']);
// Il limite di righe vale sul CODICE sorgente, non su doc/dati/lockfile/asset.
const SIZE_EXEMPT_EXT = new Set(['.md', '.mdx', '.json', '.lock', '.csv', '.tsv', '.sql', '.snap', '.svg', '.xml', '.yaml', '.yml']);
const HARD = Number(process.env.MAX_LINES_HARD) || 500;
const SOFT = process.env.MAX_LINES_SOFT === undefined ? 300 : Number(process.env.MAX_LINES_SOFT);
const SOFT_BLOCKS = /^(1|true|yes)$/i.test(String(process.env.MAX_LINES_SOFT_BLOCK || ''));
// \p{Extended_Pictographic} copre emoji/pittogrammi; escludiamo i caratteri tecnici comuni.
const EMOJI_RE = /\p{Extended_Pictographic}/u;

function parse(raw) {
  try { return raw.trim() ? JSON.parse(raw) : {}; } catch { return {}; }
}

function run(raw) {
  if (/^(off|0|false)$/i.test(String(process.env.CODE_GUARD || ''))) return { exitCode: 0 };

  const input = parse(raw);
  const filePath = input?.tool_input?.file_path || input?.tool_input?.path || '';
  if (!filePath) return { exitCode: 0 };
  if (!fs.existsSync(filePath)) return { exitCode: 0 };

  const ext = path.extname(filePath).toLowerCase();
  let content;
  try { content = fs.readFileSync(filePath, 'utf8'); } catch { return { exitCode: 0 }; }

  const errors = [];
  const warnings = [];

  // --- EMOJI (solo file di codice) ---
  const emojiOn = !/^(off|0|false)$/i.test(String(process.env.CODE_GUARD_EMOJI || ''));
  if (emojiOn && CODE_EXT.has(ext)) {
    const lines = content.split('\n');
    const hits = [];
    for (let i = 0; i < lines.length; i++) {
      if (EMOJI_RE.test(lines[i])) hits.push(i + 1);
      if (hits.length >= 5) break;
    }
    if (hits.length) {
      errors.push(
        `EMOJI nel codice (${path.basename(filePath)}, righe ${hits.join(', ')}). ` +
        'Regola: nessuna emoticon nel codice o nei commenti. Rimuovile.'
      );
    }
  }

  // --- FILE SIZE (solo codice sorgente; doc/dati esentati) ---
  const lineCount = SIZE_EXEMPT_EXT.has(ext) ? 0 : content.split('\n').length;
  if (lineCount > HARD) {
    errors.push(
      `FILE TROPPO GRANDE: ${path.basename(filePath)} = ${lineCount} righe (hard limit ${HARD}). ` +
      'Splitta in moduli/feature coerenti col dominio prima di proseguire.'
    );
  } else if (SOFT > 0 && lineCount > SOFT) {
    const msg =
      `FILE OLTRE IL SOFT LIMIT: ${path.basename(filePath)} = ${lineCount} righe (soft ${SOFT}). ` +
      'Valuta/proponi lo split.';
    if (SOFT_BLOCKS) errors.push(msg); else warnings.push(msg);
  }

  if (warnings.length) process.stderr.write('[code-guard] ' + warnings.join('\n[code-guard] ') + '\n');
  if (errors.length) {
    return { exitCode: 2, stderr: '[code-guard] ' + errors.join('\n[code-guard] ') };
  }
  return { exitCode: 0 };
}

let raw = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', c => { if (raw.length < MAX_STDIN) raw += c.substring(0, MAX_STDIN - raw.length); });
process.stdin.on('end', () => {
  const r = run(raw);
  if (r.stderr) process.stderr.write(r.stderr + '\n');
  if (r.exitCode === 2) process.exit(2);
  process.stdout.write(raw);
});
