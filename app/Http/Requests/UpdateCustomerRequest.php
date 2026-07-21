<?php

namespace App\Http\Requests;

use App\Enums\CustomerCriticality;
use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return $organization !== null
            && $customer instanceof Customer
            && $this->user()?->can('update', [$customer, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'external_ref' => ['nullable', 'string', 'max:120'],
            'primary_contact' => ['nullable', 'string', 'max:255'],
            'criticality' => ['required', Rule::enum(CustomerCriticality::class)],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
