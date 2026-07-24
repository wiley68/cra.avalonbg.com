<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductJiraLinkRequest extends FormRequest
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
        return [
            'project_key' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
        ];
    }
}
