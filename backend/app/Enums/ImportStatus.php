<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

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
 *
 * The #[Color]/#[Label] presentation metadata (via HasMeta) drives the
 * `status` badge of the `lead-imports` table: the frontend renders the pill
 * from the backend-supplied color, localizing the label from its own i18n
 * (`enums.import_status.<value>`) — the #[Label] is the source string / fallback.
 */
enum ImportStatus: string
{
    use HasMeta;

    #[Label('Validating')]
    #[Color('blue')]
    case Validating = 'validating';

    #[Label('Awaiting confirmation')]
    #[Color('amber')]
    case AwaitingConfirmation = 'awaiting_confirmation';

    #[Label('Analyzing')]
    #[Color('blue')]
    case Analyzing = 'analyzing';

    #[Label('Configuring')]
    #[Color('blue')]
    case Configuring = 'configuring';

    #[Label('Staging')]
    #[Color('blue')]
    case Staging = 'staging';

    #[Label('Reviewing')]
    #[Color('violet')]
    case Reviewing = 'reviewing';

    #[Label('Processing')]
    #[Color('amber')]
    case Processing = 'processing';

    #[Label('Completed')]
    #[Color('green')]
    case Completed = 'completed';

    #[Label('Failed')]
    #[Color('red')]
    case Failed = 'failed';
}
