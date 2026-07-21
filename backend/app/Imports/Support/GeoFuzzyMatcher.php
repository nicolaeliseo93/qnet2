<?php

namespace App\Imports\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The single-level matching mechanics behind GeoResolver::resolveFuzzy()
 * (spec 0033 AC-005): exact, case-insensitive match first (like
 * GeoResolver::findByName(), but distinguishing NOT-FOUND from AMBIGUOUS so
 * both can carry candidates); a level with ZERO exact matches falls back to
 * a similarity score (similar_text()) against every row in the given scope.
 * Extracted out of GeoResolver purely to keep that class within the file
 * size guideline — GeoResolver still owns the hierarchy walk (country ->
 * state -> province -> city) and calls this once per level.
 */
final class GeoFuzzyMatcher
{
    /** Minimum similar_text() percent (0-100) for a candidate to be accepted. */
    private const int THRESHOLD_PERCENT = 82;

    /** Max candidates surfaced per ambiguous level. */
    private const int CANDIDATE_LIMIT = 5;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  ?int  $preferCountryId  Home country used to break a cross-country
     *                                 homonym tie (see homeCountryWinner()); null
     *                                 disables the tiebreak.
     * @return array{model: ?TModel, candidates: array<int, array{id: int, name: string}>}
     */
    public function match(Builder $query, string $name, ?int $preferCountryId = null): array
    {
        $rows = $query->get();
        $target = mb_strtolower(trim($name));

        $exact = $rows->filter(
            static fn (Model $row): bool => mb_strtolower((string) $row->getAttribute('name')) === $target
        );

        if ($exact->count() === 1) {
            return ['model' => $exact->first(), 'candidates' => []];
        }

        if ($exact->count() > 1) {
            $winner = $this->homeCountryWinner($exact, $preferCountryId);

            return $winner !== null
                ? ['model' => $winner, 'candidates' => []]
                : ['model' => null, 'candidates' => $this->toCandidates($exact)];
        }

        // Zero exact matches: score every row in scope by similarity and keep
        // only those at/above the acceptance threshold.
        $scored = $rows
            ->map(fn (Model $row): array => ['row' => $row, 'score' => $this->similarity($target, (string) $row->getAttribute('name'))])
            ->sortByDesc('score')
            ->values();

        $accepted = $scored->filter(fn (array $entry): bool => $entry['score'] >= self::THRESHOLD_PERCENT);

        if ($accepted->count() === 1) {
            return ['model' => $accepted->first()['row'], 'candidates' => []];
        }

        if ($accepted->count() > 1) {
            $winner = $this->homeCountryWinner($accepted->pluck('row'), $preferCountryId);

            if ($winner !== null) {
                return ['model' => $winner, 'candidates' => []];
            }

            return ['model' => null, 'candidates' => $this->toCandidates($accepted->take(self::CANDIDATE_LIMIT)->pluck('row'))];
        }

        // Nothing crossed the threshold: surface the closest few as
        // suggestions rather than an empty candidate list.
        return ['model' => null, 'candidates' => $this->toCandidates($scored->take(self::CANDIDATE_LIMIT)->pluck('row'))];
    }

    /**
     * Home-country tiebreak: an ambiguous set spanning several countries (a
     * bare homonym like "Rome", matching Italy AND the US) collapses to the
     * single candidate that belongs to the configured home country, when
     * exactly one does. Rows without a `country_id` (the Country level itself)
     * never match, so this is a no-op there; more than one home-country
     * candidate stays ambiguous, since the tie is real.
     *
     * @param  Collection<int, Model>  $tied
     */
    private function homeCountryWinner(Collection $tied, ?int $preferCountryId): ?Model
    {
        if ($preferCountryId === null) {
            return null;
        }

        $inHome = $tied->filter(
            static fn (Model $row): bool => (int) $row->getAttribute('country_id') === $preferCountryId
        );

        return $inHome->count() === 1 ? $inHome->first() : null;
    }

    private function similarity(string $normalizedTarget, string $candidateName): float
    {
        similar_text($normalizedTarget, mb_strtolower(trim($candidateName)), $percent);

        return $percent;
    }

    /**
     * @param  Collection<int, Model>  $models
     * @return array<int, array{id: int, name: string}>
     */
    private function toCandidates(Collection $models): array
    {
        return $models
            ->map(static fn (Model $model): array => ['id' => (int) $model->getAttribute('id'), 'name' => (string) $model->getAttribute('name')])
            ->values()
            ->all();
    }
}
