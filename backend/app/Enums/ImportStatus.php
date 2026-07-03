<?php

namespace App\Enums;

/**
 * Lifecycle of a CSV import run (spec 0012): `validating` (phase 1 dry-run in
 * progress) -> `awaiting_confirmation` (preview ready, waiting for the user's
 * confirm) -> `processing` (phase 2 commit in progress) -> `completed`.
 * `failed` is reachable from `validating` OR `processing` on any unhandled job
 * exception, so a run never stays stuck mid-flight.
 */
enum ImportStatus: string
{
    case Validating = 'validating';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
