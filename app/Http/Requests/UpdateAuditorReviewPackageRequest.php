<?php

namespace App\Http\Requests;

use App\Models\AuditorReviewPackage;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAuditorReviewPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var AuditorReviewPackage $package */
        $package = $this->route('package');

        return $organization !== null
            && $this->user()?->can('update', [$package, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'evidence_ids' => ['nullable', 'array'],
            'evidence_ids.*' => ['integer', 'exists:evidence,id'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
