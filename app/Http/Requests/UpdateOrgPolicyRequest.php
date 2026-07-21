<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\OrgPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrgPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var OrgPolicy $policy */
        $policy = $this->route('org_policy');

        return $organization !== null
            && $policy instanceof OrgPolicy
            && $this->user()?->can('update', [$policy, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'version_label' => ['required', 'string', 'max:40'],
            'body' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
