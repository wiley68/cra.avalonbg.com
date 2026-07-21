<?php

namespace App\Http\Requests;

use App\Enums\AuditorFindingStatus;
use App\Models\AuditorFinding;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAuditorFindingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var AuditorFinding $finding */
        $finding = $this->route('finding');

        return $organization !== null
            && $this->user()?->can('updateStatus', [$finding, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(AuditorFindingStatus::class)],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
