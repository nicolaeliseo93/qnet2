<?php

namespace App\Exports;

use App\Enums\ExportFormat;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Resolves the ExportWriter for a format from the `config('exports.writers')`
 * registry through the container (spec 0014), so a new format is one config
 * line + one class — ExportService never branches per-format (OCP).
 */
class ExportWriterFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(ExportFormat $format): ExportWriter
    {
        /** @var array<string, class-string<ExportWriter>> $writers */
        $writers = config('exports.writers', []);

        $class = $writers[$format->value] ?? null;

        if ($class === null) {
            // Should be unreachable: CreateExportRequest already validates
            // `format` against config('exports.formats') via Rule::in.
            throw new RuntimeException("No export writer registered for format [{$format->value}].");
        }

        /** @var ExportWriter $writer */
        $writer = $this->container->make($class);

        return $writer;
    }
}
