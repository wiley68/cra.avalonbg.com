<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssistantDocumentAnalyseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();
        /** @var Product $product */
        $product = $this->route('product');

        return $organization !== null
            && $product->organization_id === $organization->id
            && ($this->user()?->can('view', [$product, $organization]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = max(64, (int) config('ai.analyse_max_upload_kb', 2048));

        return [
            'file' => [
                'required',
                'file',
                'max:' . $maxKb,
                'extensions:txt,md,markdown,csv,json,xml,html,htm,log',
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
