<?php

namespace App\Http\Requests;

use App\Enums\IncidentCommunicationChannel;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentCustomerCommunicationRequest extends FormRequest
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
        $organization = $this->currentOrganization();
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'communicated_at' => ['required', 'date'],
            'channel' => ['required', Rule::enum(IncidentCommunicationChannel::class)],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(
                    fn($query) => $query->where('organization_id', $organization?->id),
                ),
            ],
            'audience' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'evidence_id' => [
                'nullable',
                'integer',
                Rule::exists('evidence', 'id')->where(
                    fn($query) => $query
                        ->where('organization_id', $organization?->id)
                        ->where('product_id', $product->id),
                ),
            ],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
