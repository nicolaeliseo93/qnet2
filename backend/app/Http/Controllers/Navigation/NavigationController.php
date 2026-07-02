<?php

namespace App\Http\Controllers\Navigation;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\NavigationItemResource;
use App\Services\NavigationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends BaseApiController
{
    public function __construct(private readonly NavigationService $navigation) {}

    /**
     * Backend-driven navigation for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->navigation->for($request->user());

        return $this->ok(NavigationItemResource::collection($items));
    }
}
