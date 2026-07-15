<?php

namespace App\Enums;

/**
 * Lifecycle of an import run. Legacy two-phase flow (spec 0012): `validating`
 * (phase 1 dry-run in progress) -> `awaiting_confirmation` (preview ready,
 * waiting for the user's confirm) -> `processing` (phase 2 commit in
 * progress) -> `completed`. Unified wizard flow (spec 0033): `analyzing`
 * (reading header/counts) -> `configuring` (mapping/global config step) ->
 * `staging` (writing `import_run_rows`) -> `reviewing` (staged preview ready
 * for edit/confirm) -> `processing` -> `completed`. `failed` is reachable
 * from any in-progress status on an unhandled job exception, so a run never
 * stays stuck mid-flight. The legacy cases stay for retro-compat until the
 * 5 legacy domains are migrated to the unified flow.
 */
enum ImportStatus: string
{
    case Validating = 'validating';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Analyzing = 'analyzing';
    case Configuring = 'configuring';
    case Staging = 'staging';
    case Reviewing = 'reviewing';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
