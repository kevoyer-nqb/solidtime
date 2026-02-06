<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Timesheet;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Service\PermissionStore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Korridor\LaravelModelValidationRules\Rules\ExistsEloquent;

/**
 * @property Organization $organization Organization from model binding
 */
class TimesheetCellUpdateRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            // ID of the organization member
            'member_id' => [
                'required',
                'string',
                ExistsEloquent::make(Member::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Member> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->uuid(),
            ],
            // The date of the cell (Format: "Y-m-d", e.g. "2026-02-03")
            'date' => [
                'required',
                'date_format:Y-m-d',
            ],
            // Project ID (nullable for entries without a project)
            'project_id' => [
                'nullable',
                'string',
                'required_with:task_id',
                ExistsEloquent::make(Project::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Project> $builder */
                    $builder = $builder->whereBelongsTo($this->organization, 'organization');

                    $permissionStore = app(PermissionStore::class);
                    if (! $permissionStore->has($this->organization, 'time-entries:create:all')
                        && ! $permissionStore->has($this->organization, 'projects:view:all')) {
                        $builder = $builder->visibleByEmployee(Auth::user());
                    }

                    return $builder;
                })->uuid(),
            ],
            // Task ID (nullable for entries without a task)
            'task_id' => [
                'nullable',
                'string',
                ExistsEloquent::make(Task::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Task> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->uuid(),
                ExistsEloquent::make(Task::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Task> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization')
                        ->where('project_id', $this->input('project_id'));
                })->uuid()->withMessage(__('validation.task_belongs_to_project')),
            ],
            // Hours for the cell (0 to delete, > 0 to create/update)
            'hours' => [
                'required',
                'numeric',
                'min:0',
                'max:24',
            ],
        ];
    }
}
