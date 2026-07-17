<?php

namespace App\Models\Concerns;

use App\Support\Geo\GeoNameLocalizer;

/**
 * Gives a geo reference model (Country/State/Province/City) an Italian DISPLAY
 * name alongside its English `name` column. The column itself is never touched
 * — SQL matching (imports, set filters, sort) keeps hitting the English value;
 * only PHP-side reads that render to the UI call localizedName().
 */
trait LocalizesGeoName
{
    /**
     * The Italian display name (English deltas translated, everything else
     * unchanged). Null when the row has no name.
     */
    public function localizedName(): ?string
    {
        return GeoNameLocalizer::toItalian($this->name);
    }
}
