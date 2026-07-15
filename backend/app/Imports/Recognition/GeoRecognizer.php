<?php

namespace App\Imports\Recognition;

use App\Imports\ImportRowContext;
use App\Imports\Support\GeoResolutionResult;
use App\Imports\Support\GeoResolver;

/**
 * Resolves a row's mapped geo fields (country/region/province/city) to
 * `{country,state,province,city}_id` — the same column names
 * App\DataObjects\PersonalData\CreateAddress uses — via GeoResolver's fuzzy
 * mode (spec 0033 AC-005). A single unambiguous match assigns; 0 or >1
 * candidates flag the row for review instead of blocking it.
 */
final class GeoRecognizer implements RowRecognizer
{
    public const string COUNTRY_FIELD = 'country';

    public const string REGION_FIELD = 'region';

    public const string PROVINCE_FIELD = 'province';

    public const string CITY_FIELD = 'city';

    public function __construct(private readonly GeoResolver $geoResolver) {}

    public function recognize(ImportRowContext $context, array $mapped): RecognitionResult
    {
        $country = $this->value($mapped, self::COUNTRY_FIELD);
        $region = $this->value($mapped, self::REGION_FIELD);
        $province = $this->value($mapped, self::PROVINCE_FIELD);
        $city = $this->value($mapped, self::CITY_FIELD);

        // Step 1: nothing mapped at all -> nothing to resolve.
        if ($country === null && $region === null && $province === null && $city === null) {
            return RecognitionResult::none();
        }

        // Step 2: fuzzy-resolve the hierarchy and shape the resolved ids the
        // same way regardless of outcome, adding a review flag only when
        // ambiguous/unresolved.
        $result = $this->geoResolver->resolveFuzzy($country, $region, $province, $city);
        $resolved = [
            'country_id' => $result->countryId,
            'state_id' => $result->stateId,
            'province_id' => $result->provinceId,
            'city_id' => $result->cityId,
        ];

        if ($result->isResolved()) {
            return RecognitionResult::resolved($resolved);
        }

        return RecognitionResult::resolved($resolved, needsReview: true, messages: [$this->message($result)]);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function value(array $mapped, string $field): ?string
    {
        $value = trim((string) ($mapped[$field] ?? ''));

        return $value === '' ? null : $value;
    }

    private function message(GeoResolutionResult $result): string
    {
        if ($result->candidates === []) {
            return (string) $result->error;
        }

        $names = implode(', ', array_map(
            static fn (array $candidate): string => $candidate['name'],
            $result->candidates,
        ));

        return "{$result->error} Candidates: {$names}.";
    }
}
