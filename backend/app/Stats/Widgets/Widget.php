<?php

declare(strict_types=1);

namespace App\Stats\Widgets;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A single panel widget (spec 0026). The implementations are the ONLY place
 * that knows the client-facing JSON shape: a definition composes typed
 * widgets, never hand-rolled arrays, so the contract cannot drift per module.
 *
 * JsonSerializable so a widget list can be handed straight to the response
 * envelope without a mapping step in the controller.
 *
 * @extends Arrayable<string, mixed>
 */
interface Widget extends Arrayable, JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
