<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/imports/{domain}: a CSV/TXT (spec 0012), XLSX (spec
 * 0033 — parsed via the already-installed openspout, no new dependency) or
 * legacy binary XLS (spec 0033 — Excel 97-2003, parsed via
 * `phpoffice/phpspreadsheet`, dependency authorized by the user 2026-07-15)
 * file, size-capped by config('imports.max_file_kb').
 *
 * Validation is `extensions:` (the real filename extension against the
 * whitelist) + `file` + `max:`. We deliberately do NOT validate the sniffed
 * CONTENT MIME here (neither `mimes:` nor `mimetypes:`): finfo content
 * sniffing is unreliable for exactly these formats — a CSV can be classified
 * as any of a dozen text/* or application/* types depending on its bytes, an
 * `.xlsx` sniffs as `application/zip`, and an `.xls` as `application/
 * vnd.ms-excel` or a generic `application/octet-stream` that varies per
 * environment — so a content-MIME rule produces false 422s on legitimate
 * spreadsheets (observed). The ACTUAL content gate is the parser:
 * SpreadsheetReader/openspout/PhpSpreadsheet reject non-spreadsheet bytes
 * (SpreadsheetReaderException → the run fails), and no formulas are ever
 * evaluated (setReadDataOnly). So `extensions:` (spoofing gate) + parser
 * (content gate) together cover backend.md §8 without the false negatives of
 * MIME sniffing.
 *
 * Authorization is intentionally NOT handled here: the controller enforces
 * authorizeImport() via the ImportDefinition resolved from the {domain} route
 * segment, which this FormRequest does not see before the controller runs.
 */
class UploadImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'extensions:csv,txt,xlsx,xls',
                'max:'.(int) config('imports.max_file_kb'),
            ],
        ];
    }
}
