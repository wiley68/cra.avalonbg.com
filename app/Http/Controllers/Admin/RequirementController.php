<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRequirementRequest;
use App\Http\Requests\Admin\UpdateRequirementRequest;
use App\Models\Regulation;
use App\Models\Requirement;
use App\Models\RequirementVersion;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RequirementController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Requirement::class);

        return Inertia::render('admin/requirements/Index');
    }

    public function create(): Response
    {
        $this->authorize('create', Requirement::class);

        return Inertia::render('admin/requirements/Create', [
            'regulations' => $this->regulationOptions(),
        ]);
    }

    public function store(StoreRequirementRequest $request): RedirectResponse
    {
        $requirement = DB::transaction(function () use ($request) {
            $requirement = Requirement::query()->create([
                'regulation_id' => $request->integer('regulation_id'),
                'code' => $request->string('code')->toString(),
                'article_ref' => $request->input('article_ref'),
                'sort_order' => $request->integer('sort_order'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            RequirementVersion::query()->create([
                'requirement_id' => $requirement->id,
                'version' => 1,
                'requirement_text' => $request->string('requirement_text')->toString(),
                'requirement_text_bg' => $request->input('requirement_text_bg'),
                'plain_language' => $request->input('plain_language'),
                'plain_language_bg' => $request->input('plain_language_bg'),
                'applicability_notes' => $request->input('applicability_notes'),
                'applicability_notes_bg' => $request->input('applicability_notes_bg'),
                'suggested_controls_text' => $request->input('suggested_controls_text'),
                'suggested_controls_text_bg' => $request->input('suggested_controls_text_bg'),
                'required_evidence_text' => $request->input('required_evidence_text'),
                'required_evidence_text_bg' => $request->input('required_evidence_text_bg'),
                'published_at' => now(),
                'is_current' => true,
            ]);

            return $requirement;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('admin.requirements.created'),
        ]);

        return redirect()->route('admin.requirements.edit', $requirement);
    }

    public function edit(Requirement $requirement): Response
    {
        $this->authorize('update', $requirement);

        $requirement->load(['regulation', 'versions' => fn($q) => $q->orderByDesc('version')]);

        return Inertia::render('admin/requirements/Edit', [
            'requirement' => [
                'id' => $requirement->id,
                'regulation_id' => $requirement->regulation_id,
                'code' => $requirement->code,
                'article_ref' => $requirement->article_ref,
                'sort_order' => $requirement->sort_order,
                'is_active' => $requirement->is_active,
                'versions' => $requirement->versions->map(fn(RequirementVersion $version) => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'requirement_text' => $version->requirement_text,
                    'requirement_text_bg' => $version->requirement_text_bg,
                    'plain_language' => $version->plain_language,
                    'plain_language_bg' => $version->plain_language_bg,
                    'applicability_notes' => $version->applicability_notes,
                    'applicability_notes_bg' => $version->applicability_notes_bg,
                    'suggested_controls_text' => $version->suggested_controls_text,
                    'suggested_controls_text_bg' => $version->suggested_controls_text_bg,
                    'required_evidence_text' => $version->required_evidence_text,
                    'required_evidence_text_bg' => $version->required_evidence_text_bg,
                    'is_current' => $version->is_current,
                    'published_at' => $version->published_at?->toIso8601String(),
                ])->all(),
            ],
            'regulations' => $this->regulationOptions(),
        ]);
    }

    public function update(UpdateRequirementRequest $request, Requirement $requirement): RedirectResponse
    {
        DB::transaction(function () use ($request, $requirement) {
            $requirement->update([
                'regulation_id' => $request->integer('regulation_id'),
                'code' => $request->string('code')->toString(),
                'article_ref' => $request->input('article_ref'),
                'sort_order' => $request->integer('sort_order'),
                'is_active' => $request->boolean('is_active'),
            ]);

            $contentAttributes = [
                'requirement_text' => $request->string('requirement_text')->toString(),
                'requirement_text_bg' => $request->input('requirement_text_bg'),
                'plain_language' => $request->input('plain_language'),
                'plain_language_bg' => $request->input('plain_language_bg'),
                'applicability_notes' => $request->input('applicability_notes'),
                'applicability_notes_bg' => $request->input('applicability_notes_bg'),
                'suggested_controls_text' => $request->input('suggested_controls_text'),
                'suggested_controls_text_bg' => $request->input('suggested_controls_text_bg'),
                'required_evidence_text' => $request->input('required_evidence_text'),
                'required_evidence_text_bg' => $request->input('required_evidence_text_bg'),
            ];

            if ($request->boolean('create_new_version')) {
                $nextVersion = (int) $requirement->versions()->max('version') + 1;

                $requirement->versions()->update(['is_current' => false]);

                RequirementVersion::query()->create([
                    'requirement_id' => $requirement->id,
                    'version' => $nextVersion,
                    ...$contentAttributes,
                    'published_at' => now(),
                    'is_current' => true,
                ]);
            } else {
                /** @var RequirementVersion|null $current */
                $current = $requirement->versions()->where('is_current', true)->first()
                    ?? $requirement->versions()->orderByDesc('version')->first();

                if ($current instanceof RequirementVersion) {
                    $current->update($contentAttributes);
                }
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('admin.requirements.updated'),
        ]);

        return redirect()->route('admin.requirements.edit', $requirement);
    }

    /**
     * @return list<array{id: int, code: string, title: string}>
     */
    private function regulationOptions(): array
    {
        return Regulation::query()
            ->orderBy('code')
            ->get(['id', 'code', 'title'])
            ->map(fn(Regulation $regulation) => [
                'id' => $regulation->id,
                'code' => $regulation->code,
                'title' => $regulation->title,
            ])
            ->all();
    }
}
