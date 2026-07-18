<?php

namespace App\Http\Requests;

use App\Enums\TaskApprovalStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null
            && $this->user()?->can('create', [Task::class, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->currentOrganization();
        /** @var Product $product */
        $product = $this->route('product');

        $memberRule = Rule::exists('organization_user', 'user_id')
            ->where(fn($query) => $query->where('organization_id', $organization?->id));

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'assignee_user_id' => ['nullable', 'integer', $memberRule],
            'due_at' => ['nullable', 'date'],
            'subject_type' => ['nullable', 'string', Rule::in(array_keys(TaskService::subjectTypeMap()))],
            'subject_id' => ['nullable', 'integer', 'required_with:subject_type'],
            'approval_status' => [
                'nullable',
                Rule::enum(TaskApprovalStatus::class),
                Rule::in([
                    TaskApprovalStatus::NotRequired->value,
                    TaskApprovalStatus::Pending->value,
                ]),
            ],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
