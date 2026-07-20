<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRepositoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $product = $this->route('product');
        $organization = $user?->currentOrganization();

        return $user !== null
            && $organization !== null
            && $product !== null
            && $product->organization_id === $organization->id
            && $user->can('update', [$product, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->user()?->currentOrganization();

        return [
            'connection_id' => [
                'required',
                'integer',
                Rule::exists('organization_vcs_connections', 'id')
                    ->where('organization_id', $organization?->id),
            ],
            'repository' => ['required', 'string', 'max:255'],
        ];
    }
}
