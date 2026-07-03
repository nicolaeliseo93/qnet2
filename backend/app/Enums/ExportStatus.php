<?php

namespace App\Enums;

/**
 * Lifecycle of a generic table export run (spec 0014): single-phase, no
 * validation/confirm step (unlike ImportStatus). `failed` is reachable from
 * `processing` on any unhandled GenerateExportJob exception, so a run never
 * stays stuck mid-flight.
 */
enum ExportStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
