<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\ProductIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseProductIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var ProductIncident $incident */
        $incident = $this->route('incident');

        return $organization !== null
            && $this->user()?->can('update', [$incident, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->currentOrganization();

        $memberRule = Rule::exists('organization_user', 'user_id')
            ->where(fn($query) => $query->where('organization_id', $organization?->id));

        return [
            'create_approval_task' => ['sometimes', 'boolean'],
            'assignee_user_id' => ['nullable', 'integer', $memberRule],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
