#!/usr/bin/env node
/**
 * doc-guard — PreToolUse (Write)
 * Enforcement della regola "non creare file di documentazione se non richiesto".
 * BLOCCA (exit 2) la creazione di NUOVI file .md / README* fuori da docs/.
 * Consentiti sempre: file dentro docs/**, modifica di file già esistenti,
 * e qualsiasi creazione quando l'utente l'ha richiesta (ALLOW_DOCS=1).
 *
 * Disattivazione / override via env:
 *   DOC_GUARD=off    disattiva del tutto
 *   ALLOW_DOCS=1     consente la creazione di doc (usa quando l'utente la chiede)
 */

'use strict';

const fs = require('fs');
const path = require('path');

const MAX_STDIN = 1024 * 1024;

function parse(raw) {
  try { return raw.trim() ? JSON.parse(raw) : {}; } catch { return {}; }
}

function isDocFile(filePath) {
  const base = path.basename(filePath).toLowerCase();
  if (base.endsWith('.md') || base.endsWith('.mdx')) return true;
  if (base.startsWith('readme') || base.startsWith('changelog')) return true;
  return false;
}

function run(raw) {
  if (/^(off|0|false)$/i.test(String(process.env.DOC_GUARD || ''))) return { exitCode: 0 };
  if (/^(1|true|yes)$/i.test(String(process.env.ALLOW_DOCS || ''))) return { exitCode: 0 };

  const input = parse(raw);
  const tool = input?.tool_name || '';
  if (tool && tool !== 'Write') return { exitCode: 0 };

  const filePath = input?.tool_input?.file_path || input?.tool_input?.path || '';
  if (!filePath || !isDocFile(filePath)) return { exitCode: 0 };

  // Consenti dentro docs/ (separatori normalizzati).
  const norm = filePath.replace(/\\/g, '/');
  if (/(^|\/)docs\//.test(norm)) return { exitCode: 0 };

  // Consenti la modifica di un file già esistente (la regola riguarda la CREAZIONE).
  if (fs.existsSync(filePath)) return { exitCode: 0 };

  return {
    exitCode: 2,
    stderr:
      `[doc-guard] BLOCCATO: creazione di un file di documentazione non richiesto (${path.basename(filePath)}). ` +
      'Regola: non creare README/CHANGELOG/.md se non esplicitamente richiesti. ' +
      'Se l\'utente l\'ha chiesto: scrivilo sotto docs/ oppure imposta ALLOW_DOCS=1.'
  };
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
