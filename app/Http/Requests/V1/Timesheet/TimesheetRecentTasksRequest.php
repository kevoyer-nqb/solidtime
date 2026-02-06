<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Timesheet;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * @property Organization $organization Organization from model binding
 */
class TimesheetRecentTasksRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            // Maximum number of recent tasks to return
            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
        ];
    }
}
