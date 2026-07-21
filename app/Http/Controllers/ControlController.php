<?php

namespace App\Http\Controllers;

use App\Enums\ControlAutomationLevel;
use App\Enums\ControlFrequency;
use App\Http\Requests\StoreControlRequest;
use App\Http\Requests\UpdateControlRequest;
use App\Models\Control;
use App\Models\Organization;
use App\Models\Requirement;
use App\Services\ControlService;
use App\Support\RelatedPolicyTypes;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ControlController extends Controller
{
    public function __construct(
        private readonly ControlService $controls,
    ) {
    }

    public function index(): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('viewAny', [Control::class, $organization]);

        return Inertia::render('controls/Index', [
            'organization' => $this->organizationPayload($organization),
            'canManage' => request()->user()->canManageControls($organization),
        ]);
    }

    public function create(): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('create', [Control::class, $organization]);

        return Inertia::render('controls/Create', [
            'organization' => $this->organizationPayload($organization),
            'members' => $this->memberOptions($organization),
            'requirements' => $this->requirementOptions(),
            'options' => $this->enumOptions(),
        ]);
    }

    public function store(StoreControlRequest $request): RedirectResponse
    {
        $organization = $this->currentOrganization();

        $control = $this->controls->create(
            $organization,
            [
                'code' => $request->string('code')->toString(),
                'name' => $request->string('name')->toString(),
                'description' => $request->input('description'),
                'owner_user_id' => $request->input('owner_user_id') ? (int) $request->input('owner_user_id') : null,
                'implementation_guidance' => $request->input('implementation_guidance'),
                'automation_level' => ControlAutomationLevel::from($request->string('automation_level')->toString()),
                'frequency' => ControlFrequency::from($request->string('frequency')->toString()),
                'is_active' => $request->boolean('is_active', true),
            ],
            $request->input('requirement_ids', []),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('controls.created'),
        ]);

        return redirect()->route('controls.edit', $control);
    }

    public function edit(Control $control): Response
    {
        $organization = $this->currentOrganization();
        $this->assertControlInOrganization($control, $organization);
        $this->authorize('view', [$control, $organization]);

        $control->load(['owner', 'requirements']);

        return Inertia::render('controls/Edit', [
            'organization' => $this->organizationPayload($organization),
            'control' => [
                'id' => $control->id,
                'code' => $control->code,
                'name' => $control->name,
                'description' => $control->description,
                'owner_user_id' => $control->owner_user_id,
                'implementation_guidance' => $control->implementation_guidance,
                'automation_level' => $control->automation_level->value,
                'frequency' => $control->frequency->value,
                'is_active' => $control->is_active,
                'source' => $control->source->value,
                'requirement_ids' => $control->requirements->pluck('id')->all(),
            ],
            'members' => $this->memberOptions($organization),
            'requirements' => $this->requirementOptions(),
            'options' => $this->enumOptions(),
            'relatedPolicyTypes' => RelatedPolicyTypes::forControl(
                $control->code,
                $control->requirements->pluck('code')->all(),
            ),
            'canManage' => request()->user()->canManageControls($organization),
        ]);
    }

    public function update(UpdateControlRequest $request, Control $control): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertControlInOrganization($control, $organization);

        $this->controls->update(
            $control,
            [
                'code' => $request->string('code')->toString(),
                'name' => $request->string('name')->toString(),
                'description' => $request->input('description'),
                'owner_user_id' => $request->input('owner_user_id') ? (int) $request->input('owner_user_id') : null,
                'implementation_guidance' => $request->input('implementation_guidance'),
                'automation_level' => ControlAutomationLevel::from($request->string('automation_level')->toString()),
                'frequency' => ControlFrequency::from($request->string('frequency')->toString()),
                'is_active' => $request->boolean('is_active', true),
            ],
            $request->input('requirement_ids', []),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('controls.updated'),
        ]);

        return redirect()->route('controls.edit', $control);
    }

    public function destroy(Control $control): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertControlInOrganization($control, $organization);
        $this->authorize('delete', [$control, $organization]);

        $this->controls->delete($control);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('controls.deleted'),
        ]);

        return redirect()->route('controls.index');
    }

    public function refreshStarter(): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('create', [Control::class, $organization]);

        $result = $this->controls->seedStarterCatalogue($organization, refreshExisting: true);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('controls.starter_refreshed', [
                'created' => (string) $result['created'],
                'updated' => (string) $result['updated'],
            ]),
        ]);

        return redirect()->route('controls.index');
    }

    private function currentOrganization(): Organization
    {
        $organization = request()->user()?->currentOrganization();

        if ($organization === null) {
            abort(403, 'No organization membership.');
        }

        return $organization;
    }

    private function assertControlInOrganization(Control $control, Organization $organization): void
    {
        if ($control->organization_id !== $organization->id) {
            abort(404);
        }
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function organizationPayload(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
        ];
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    private function memberOptions(Organization $organization): array
    {
        return $organization->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, code: string, article_ref: string|null}>
     */
    private function requirementOptions(): array
    {
        return Requirement::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'code', 'article_ref'])
            ->map(fn(Requirement $requirement) => [
                'id' => $requirement->id,
                'code' => $requirement->code,
                'article_ref' => $requirement->article_ref,
            ])
            ->all();
    }

    /**
     * @return array{automation_levels: list<string>, frequencies: list<string>}
     */
    private function enumOptions(): array
    {
        return [
            'automation_levels' => array_column(ControlAutomationLevel::cases(), 'value'),
            'frequencies' => array_column(ControlFrequency::cases(), 'value'),
        ];
    }
}
