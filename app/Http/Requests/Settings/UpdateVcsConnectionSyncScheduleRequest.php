<?php

namespace App\Http\Requests\Settings;

use App\Enums\VcsSyncSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVcsConnectionSyncScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $user?->currentOrganization();
        $connection = $this->route('connection');

        return $user !== null
            && $organization !== null
            && $connection !== null
            && $connection->organization_id === $organization->id
            && $user->canManageProducts($organization);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sync_schedule' => ['required', Rule::enum(VcsSyncSchedule::class)],
        ];
    }
}
