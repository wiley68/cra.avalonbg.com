<?php

namespace App\Http\Requests;

use App\Enums\AuditorFindingSeverity;
use App\Models\AuditorFinding;
use App\Models\AuditorReviewPackage;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuditorFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var AuditorReviewPackage $package */
        $package = $this->route('package');

        return $organization !== null
            && $this->user()?->can('create', [AuditorFinding::class, $package, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'severity' => ['required', 'string', Rule::enum(AuditorFindingSeverity::class)],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
