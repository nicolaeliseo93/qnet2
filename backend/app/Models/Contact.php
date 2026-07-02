<?php

namespace App\Models;

use App\Enums\ContactTypeEnum;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Reusable, polymorphic contact — a single reachable channel (phone, email,
 * website, ...) owned by any entity. Attach with the HasContacts trait
 * (morphMany on `contactable`):
 *
 *     class PersonalData extends BaseModel
 *     {
 *         use HasContacts;
 *     }
 *
 *     $card->contacts;                 // all channels
 *     $card->primaryContact('email');  // the preferred one of a type
 *
 * The "at most one primary per owner+type" invariant is owned by ContactService.
 *
 * @property ContactTypeEnum $type
 * @property string|null $label
 * @property string $value
 * @property bool $is_primary
 */
class Contact extends BaseModel
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, LogsModelActivity;

    protected $fillable = [
        'type',
        'label',
        'value',
        'is_primary',
    ];

    protected $casts = [
        'type' => ContactTypeEnum::class,
        'label' => 'string',
        'value' => 'string',
        'is_primary' => 'bool',
    ];

    /**
     * The channel payload (email / phone / PEC, ...) is personal data. Hiding it
     * keeps it out of the activity log (LogsModelActivity excludes $hidden) and
     * out of default JSON serialization; it stays readable via the attribute and
     * through an explicit, authorized resource when one is added.
     *
     * @var list<string>
     */
    protected $hidden = [
        'value',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }
}
