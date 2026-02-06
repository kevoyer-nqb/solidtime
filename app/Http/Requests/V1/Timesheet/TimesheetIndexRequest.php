<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Timesheet;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * @property Organization $organization Organization from model binding
 */
class TimesheetIndexRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            // Start of the week (Format: "Y-m-d", e.g. "2026-02-02")
            'week_start' => [
                'required',
                'date_format:Y-m-d',
            ],
            // End of the week (Format: "Y-m-d", e.g. "2026-02-08")
            'week_end' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:week_start',
            ],
        ];
    }
}
