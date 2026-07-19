<?php

namespace App\Http\Requests;

use App\Enums\ControlAutomationLevel;
use App\Enums\ControlFrequency;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null
            && $this->user()?->can('create', [\App\Models\Control::class, $organization]);
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
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('controls', 'code')->where(
                    fn($query) => $query->where('organization_id', $organization?->id),
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'name_bg' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'description_bg' => ['nullable', 'string'],
            'owner_user_id' => ['nullable', 'integer', $memberRule],
            'implementation_guidance' => ['nullable', 'string'],
            'implementation_guidance_bg' => ['nullable', 'string'],
            'automation_level' => ['required', Rule::enum(ControlAutomationLevel::class)],
            'frequency' => ['required', Rule::enum(ControlFrequency::class)],
            'is_active' => ['sometimes', 'boolean'],
            'requirement_ids' => ['nullable', 'array'],
            'requirement_ids.*' => ['integer', Rule::exists('requirements', 'id')],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
