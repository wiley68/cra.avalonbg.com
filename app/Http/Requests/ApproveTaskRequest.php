<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class ApproveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();
        /** @var Task $task */
        $task = $this->route('task');

        return $organization instanceof Organization
            && $task instanceof Task
            && $this->user()?->can('approve', [$task, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'approval_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
