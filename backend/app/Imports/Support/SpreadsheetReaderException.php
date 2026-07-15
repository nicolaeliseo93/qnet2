<?php

namespace App\Imports\Support;

use RuntimeException;

/**
 * A spreadsheet file (xlsx/csv) is unreadable by SpreadsheetReader (missing/
 * corrupted file, unsupported extension, no header row, row cap exceeded).
 * Left uncaught in a job (AnalyzeImportJob/StageImportJob), it is exactly the
 * kind of Throwable the job's own catch-all maps to `status=failed` (AC-010),
 * mirroring CsvReaderException for the legacy CSV-only flow.
 */
class SpreadsheetReaderException extends RuntimeException {}
