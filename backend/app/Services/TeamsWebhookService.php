<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TeamsWebhookService
{
    private const EXPANDABLE_FACT_KEYS = [
        'stack_trace',
        'trace',
        'stacktrace',
    ];

    /**
     * Return whether the Teams webhook integration is enabled.
     */
    public function enabled(): bool
    {
        return (bool) config('services.teams.enabled')
            && !empty(config('services.teams.webhook_errors'));
    }

    /**
     * Send an info message using a neutral grey style.
     */
    public function sendInfo(
        string $title,
        string $message,
        array $facts = []
    ): bool
    {
        return $this->sendStyledText('info', $title, $message, $facts);
    }

    /**
     * Send a success message using a green style.
     */
    public function sendSuccess(
        string $title,
        string $message,
        array $facts = []
    ): bool
    {
        return $this->sendStyledText('success', $title, $message, $facts);
    }

    /**
     * Send an error message using a red style.
     */
    public function sendError(
        string $title,
        string $message,
        array $facts = []
    ): bool
    {
        return $this->sendStyledText('error', $title, $message, $facts);
    }

    /**
     * Send a styled text card based on the provided message level.
     */
    private function sendStyledText(
        string $level,
        string $title,
        string $message,
        array $facts = []
    ): bool
    {
        [$regularFacts, $expandableFacts] = $this->splitFacts($facts);

        $body = [
            [
                'type' => 'Container',
                'style' => $this->resolveContainerStyle($level),
                'items' => [
                    [
                        'type' => 'TextBlock',
                        'size' => 'Medium',
                        'weight' => 'Bolder',
                        'text' => $title,
                        'wrap' => true,
                    ],
                    [
                        'type' => 'TextBlock',
                        'text' => $message,
                        'wrap' => true,
                    ],
                ],
            ],
        ];

        if (!empty($regularFacts)) {
            $body[] = [
                'type' => 'FactSet',
                'facts' => collect($regularFacts)
                    ->map(fn($value, $key) => [
                        'title' => (string) $key,
                        'value' => (string) $value,
                    ])
                    ->values()
                    ->all(),
            ];
        }

        if (!empty($expandableFacts)) {
            foreach ($expandableFacts as $key => $value) {
                $body = array_merge($body, $this->buildExpandableSection((string) $key, (string) $value));
            }
        }

        return $this->sendAdaptiveCard($body, $title, $level);
    }

    /**
     * Send a custom Adaptive Card payload to the configured Teams channel.
     */
    private function sendAdaptiveCard(array $body, ?string $summary = null, string $level = 'error'): bool
    {
        return $this->send([
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl' => null,
                    'content' => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'body' => $body,
                    ],
                ],
            ],
            'summary' => $summary,
        ], $level);
    }

    /**
     * Send the raw payload to Teams and log failures.
     */
    private function send(array $payload, string $level): bool
    {
        $webhookUrl = $this->resolveWebhookUrl($level);

        if (!(bool) config('services.teams.enabled') || empty($webhookUrl)) {
            Log::warning('Teams webhook is disabled or not configured.', [
                'level' => $level,
            ]);

            return false;
        }

        try {
            $response = Http::timeout((int) config('services.teams.timeout', 10))
                ->acceptJson()
                ->asJson()
                ->post($webhookUrl, $payload);

            if ($response->failed()) {
                Log::error('Teams webhook request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $exception) {
            Log::error('Teams webhook request crashed.', [
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);

            return false;
        }
    }

    private function resolveWebhookUrl(string $level): ?string
    {
        return match (strtolower($level)) {
            'error' => config('services.teams.webhook_errors'),
            default => config('services.teams.webhook_info'),
        };
    }

    /**
     * Map the message level to the corresponding Adaptive Card style.
     */
    private function resolveContainerStyle(string $level): string
    {
        return match (strtolower($level)) {
            'success' => 'good',
            'error' => 'attention',
            default => 'emphasis',
        };
    }

    /**
     * Split regular facts from large values that should be hidden behind a toggle.
     */
    private function splitFacts(array $facts): array
    {
        $regularFacts = [];
        $expandableFacts = [];

        foreach ($facts as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $stringValue = is_scalar($value) || is_null($value)
                ? (string) $value
                : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if (in_array($normalizedKey, self::EXPANDABLE_FACT_KEYS, true) || mb_strlen($stringValue) > 500) {
                $expandableFacts[$key] = $stringValue;
                continue;
            }

            $regularFacts[$key] = $stringValue;
        }

        return [$regularFacts, $expandableFacts];
    }

    /**
     * Build an expandable card section for long diagnostic text.
     */
    private function buildExpandableSection(string $label, string $value): array
    {
        $sectionId = 'details_' . md5($label . $value);
        $preview = mb_substr($value, 0, 600);

        if (mb_strlen($value) > 600) {
            $preview .= '...';
        }

        return [
            [
                'type' => 'TextBlock',
                'text' => (string) $label,
                'weight' => 'Bolder',
                'spacing' => 'Medium',
                'wrap' => true,
            ],
            [
                'type' => 'TextBlock',
                'text' => $preview,
                'wrap' => true,
                'maxLines' => 4,
                'isSubtle' => true,
                'fontType' => 'Monospace',
            ],
            [
                'type' => 'ActionSet',
                'actions' => [
                    [
                        'type' => 'Action.ToggleVisibility',
                        'title' => 'Show details',
                        'targetElements' => [$sectionId],
                    ],
                ],
            ],
            [
                'type' => 'Container',
                'id' => $sectionId,
                'isVisible' => false,
                'items' => [
                    [
                        'type' => 'TextBlock',
                        'text' => $value,
                        'wrap' => true,
                        'fontType' => 'Monospace',
                    ],
                ],
            ],
        ];
    }
}
