<?php

namespace App\Imports\Support;

use RuntimeException;

/**
 * A CSV file is unreadable by CsvReader (missing/wrong header, row cap
 * exceeded, unreadable path). Left uncaught in a job (ValidateImportJob/
 * ProcessImportJob), it is exactly the kind of Throwable the job's own
 * catch-all maps to `status=failed`.
 */
class CsvReaderException extends RuntimeException {}
