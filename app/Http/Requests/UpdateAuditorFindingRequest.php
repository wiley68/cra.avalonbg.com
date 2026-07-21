<?php

namespace App\Http\Requests;

use App\Enums\AuditorFindingSeverity;
use App\Models\AuditorFinding;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAuditorFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var AuditorFinding $finding */
        $finding = $this->route('finding');

        return $organization !== null
            && $this->user()?->can('update', [$finding, $organization]);
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
