<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\OpportunityProductLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "funzione aziendale" + "categoria prodotto" row against an Opportunity
 * (spec 0040, amendment rev.3): SUBSTITUTES the former single
 * `business_function_id`/`product_category_id` columns on `opportunities`
 * with a one-to-many collection — the same business function may repeat
 * across rows paired with a different category, but the exact pair is
 * unique (`opportunity_id`, `business_function_id`, `product_category_id`).
 * No activity log on this row (pure child collection of the Opportunity,
 * which already logs its own changes).
 */
#[Fillable(['opportunity_id', 'business_function_id', 'product_category_id'])]
class OpportunityProductLine extends BaseModel
{
    /** @use HasFactory<OpportunityProductLineFactory> */
    use HasFactory;

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
