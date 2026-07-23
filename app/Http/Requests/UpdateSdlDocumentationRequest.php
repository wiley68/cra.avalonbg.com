<?php

namespace App\Http\Requests;

use App\Enums\UserSecurityInstructionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SdlRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSdlDocumentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var SdlRun|null $run */
        $run = $this->route('sdlRun');

        return $organization !== null
            && $run instanceof SdlRun
            && $this->user()?->can('update', [$run, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'user_security_instruction_id' => [
                'nullable',
                'integer',
                Rule::exists('user_security_instructions', 'id')->where(
                    fn($query) => $query
                        ->where('product_id', $product->id)
                        ->where('status', UserSecurityInstructionStatus::Published->value),
                ),
            ],
            'tech_doc_delta_reviewed' => ['sometimes', 'boolean'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
