<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\TimesheetGrid;

use App\Http\Requests\V1\BaseFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * @property \App\Models\Organization $organization Organization from model binding
 */
class TimesheetGridIndexRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            // Date to determine the week (Format: Y-m-d, Example: "2026-02-10")
            'date' => [
                'nullable',
                'string',
                'date_format:Y-m-d',
            ],
        ];
    }
}
