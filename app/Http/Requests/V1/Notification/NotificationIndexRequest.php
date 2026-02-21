<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Notification;

use App\Http\Requests\V1\BaseFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class NotificationIndexRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [];
    }
}
