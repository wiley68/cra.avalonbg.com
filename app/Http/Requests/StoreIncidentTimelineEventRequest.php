<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\ProductIncident;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentTimelineEventRequest extends FormRequest
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
        return [
            'occurred_at' => ['required', 'date'],
            'label' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
