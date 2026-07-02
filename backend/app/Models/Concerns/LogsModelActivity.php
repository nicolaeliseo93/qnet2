<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Standard activity-log wiring for Eloquent models.
 *
 * Every model that needs auditing should `use LogsModelActivity`. By default it
 * logs the model's fillable attributes, excluding the hidden ones (passwords,
 * tokens, ...), and only when they actually change. The log name defaults to the
 * model's table name.
 *
 * Override getActivitylogOptions() in the model when custom behaviour is needed.
 */
trait LogsModelActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept($this->getHidden())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getTable());
    }
}
