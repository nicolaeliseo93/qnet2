<?php

// Agnosticism (spec 0052, D-9, AC-021): the notes CORE component must never
// reference the host module directly — the only place `Opportunity`/
// `RequestManagement*`/the `request-management` slug may appear is
// config/notes.php.

if (! function_exists('phpFilesUnder')) {
    /**
     * @return array<int, string>
     */
    function phpFilesUnder(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        $files = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}

it('the notes core never references Opportunity/RequestManagement*/request-management outside config/notes.php (AC-021)', function () {
    $files = array_merge(
        [app_path('Models/Note.php')],
        phpFilesUnder(app_path('Notes')),
        phpFilesUnder(app_path('Http/Controllers/Notes')),
        glob(app_path('Http/Resources/Note*.php')) ?: [],
    );

    expect($files)->not->toBeEmpty();

    $needles = ['Opportunity', 'RequestManagement', 'request-management'];

    foreach ($files as $file) {
        $contents = file_get_contents($file);

        foreach ($needles as $needle) {
            expect(str_contains($contents, $needle))
                ->toBeFalse("{$file} references \"{$needle}\" — the host module must only appear in config/notes.php.");
        }
    }
});
