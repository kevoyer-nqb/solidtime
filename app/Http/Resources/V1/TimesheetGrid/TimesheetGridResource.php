<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\TimesheetGrid;

use App\Http\Resources\V1\BaseResource;
use Illuminate\Http\Request;

/**
 * Placeholder resource for potential future use.
 * Currently the TimesheetGridController returns raw JSON
 * since the grid data structure is custom (not model-based).
 */
class TimesheetGridResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
