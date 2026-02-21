<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Notification;

use App\Enums\NotificationType;
use App\Http\Requests\V1\BaseFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class NotificationPreferenceUpdateRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string|ValidationRule|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        return [
            'notification_type' => [
                'required',
                'string',
                Rule::in(array_column(NotificationType::cases(), 'value')),
            ],
            'email_enabled' => [
                'required',
                'boolean',
            ],
        ];
    }
}
