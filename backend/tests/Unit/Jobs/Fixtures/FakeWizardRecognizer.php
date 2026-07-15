<?php

namespace Tests\Unit\Jobs\Fixtures;

use App\Imports\ImportRowContext;
use App\Imports\Recognition\RecognitionResult;
use App\Imports\Recognition\RowRecognizer;

/**
 * Deterministic test-only recognizer for FakeWizardImportDefinition: derives
 * `domain_hint` from the mapped `email`'s domain part, and flags the row for
 * review (needsReview) when the email is prefixed `lowconf` — a stand-in for
 * a real low-confidence heuristic (e.g. NameSplitRecognizer's single-token
 * case), so StageImportJob tests can assert the warning/messages path
 * generically without coupling to the real recognizers' exact rules.
 */
final class FakeWizardRecognizer implements RowRecognizer
{
    public function recognize(ImportRowContext $context, array $mapped): RecognitionResult
    {
        $email = trim((string) ($mapped['email'] ?? ''));

        if ($email === '' || ! str_contains($email, '@')) {
            return RecognitionResult::none();
        }

        $domain = substr($email, strpos($email, '@') + 1);

        if (str_starts_with($email, 'lowconf')) {
            return RecognitionResult::resolved(
                ['domain_hint' => $domain],
                needsReview: true,
                messages: ["Low-confidence email domain \"{$domain}\"."],
            );
        }

        return RecognitionResult::resolved(['domain_hint' => $domain]);
    }
}
