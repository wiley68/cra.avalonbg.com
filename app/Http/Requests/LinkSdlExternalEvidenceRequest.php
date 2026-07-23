<?php

namespace App\Http\Requests;

use App\Enums\SdlStage;
use App\Models\Organization;
use App\Models\SdlRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkSdlExternalEvidenceRequest extends FormRequest
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
        return [
            'url' => ['required', 'string', 'url', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'stage' => ['nullable', Rule::enum(SdlStage::class)],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}
