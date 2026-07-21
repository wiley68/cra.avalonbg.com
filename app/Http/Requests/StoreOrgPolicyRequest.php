<?php

namespace App\Http\Requests;

use App\Enums\PolicyType;
use App\Models\Organization;
use App\Models\OrgPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrgPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null
            && $this->user()?->can('create', [OrgPolicy::class, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'policy_type' => ['required', Rule::enum(PolicyType::class)],
            'title' => ['required_without:use_template', 'nullable', 'string', 'max:255'],
            'version_label' => ['required_without:use_template', 'nullable', 'string', 'max:40'],
            'body' => ['required_without:use_template', 'nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'supersedes_id' => ['nullable', 'integer'],
            'use_template' => ['sometimes', 'boolean'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
