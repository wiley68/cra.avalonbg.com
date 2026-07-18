<?php

namespace App\Http\Requests;

use App\Enums\ProductControlStatus;
use App\Models\Organization;
use App\Models\ProductControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var ProductControl $productControl */
        $productControl = $this->route('product_control');

        return $organization !== null
            && $this->user()?->can('update', [$productControl, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ProductControlStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
