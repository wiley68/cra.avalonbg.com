<?php

namespace App\Http\Requests\Settings;

use App\Enums\IntegrationSyncSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationSyncScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $user?->currentOrganization();
        $integration = $this->route('integration');

        return $user !== null
            && $organization !== null
            && $integration !== null
            && $integration->organization_id === $organization->id
            && $user->canManageProducts($organization);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sync_schedule' => ['required', Rule::enum(IntegrationSyncSchedule::class)],
        ];
    }
}
