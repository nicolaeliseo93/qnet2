# docs/specs/ — le spec versionate

La spec è il **primo pilastro** della qualità (più dell'harness e molto più del prompt). È un file
versionato, posseduto da te: il modello propone, tu validi.

## Workflow
1. Copia `templates/spec.template.xml` in `docs/specs/<NNN>-<nome-feature>.xml`.
2. Compila `goal`, `context`, `scope/in`, **`scope/out` (sempre — uccide il drift)**, `data_contract`, `acceptance_criteria`, `constraints`.
3. **Congela il `data_contract` prima di spawnare i teammate**: backend e frontend lavorano in parallelo contro la stessa shape senza comunicare.
4. I teammate implementano; il `verifier` esegue gli `acceptance_criteria` 1:1 sui test.
5. A stato verde: git checkpoint + aggiorna `docs/HANDOFF.md`.

## Convenzioni
- Numerazione progressiva: `001-...`, `002-...`.
- Dominio/naming in italiano; non mescolare lingue dentro un identificatore (il code-mixing degrada più del gap linguistico).
- Una spec = una feature. Se cresce, splittala.

L'hook `session-start.sh` elenca automaticamente le spec presenti qui all'avvio di ogni sessione.
