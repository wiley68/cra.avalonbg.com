<?php

namespace Database\Seeders;

use App\Enums\ControlSource;
use App\Models\Control;
use App\Models\Organization;
use App\Models\Requirement;
use App\Support\StarterControlCatalogue;
use Illuminate\Database\Seeder;

class ControlCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        Organization::query()->each(function (Organization $organization): void {
            $this->seedForOrganization($organization);
        });
    }

    /**
     * Seed or refresh starter controls for an organization.
     *
     * - Creates missing starter codes as starter_template.
     * - Updates existing starter_template rows from the catalogue.
     * - Never overwrites custom controls.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function seedForOrganization(Organization $organization, bool $refreshExisting = true): array
    {
        $requirementIdsByCode = Requirement::query()
            ->whereIn('code', StarterControlCatalogue::allLinkedRequirementCodes())
            ->pluck('id', 'code');

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach (StarterControlCatalogue::items() as $item) {
            $existing = Control::query()
                ->where('organization_id', $organization->id)
                ->where('code', $item['code'])
                ->first();

            $content = [
                'name' => $item['name'],
                'name_bg' => $item['name_bg'],
                'description' => $item['description'],
                'description_bg' => $item['description_bg'],
                'implementation_guidance' => $item['implementation_guidance'],
                'implementation_guidance_bg' => $item['implementation_guidance_bg'],
                'automation_level' => $item['automation_level'],
                'frequency' => $item['frequency'],
                'is_active' => true,
                'source' => ControlSource::StarterTemplate,
            ];

            $ids = collect($item['requirement_codes'])
                ->map(fn(string $code) => $requirementIdsByCode->get($code))
                ->filter()
                ->values()
                ->all();

            if ($existing === null) {
                $control = Control::query()->create([
                    'organization_id' => $organization->id,
                    'code' => $item['code'],
                    ...$content,
                ]);
                $control->requirements()->sync($ids);
                $created++;

                continue;
            }

            if ($existing->source !== ControlSource::StarterTemplate) {
                $skipped++;

                continue;
            }

            if (!$refreshExisting) {
                $skipped++;

                continue;
            }

            $existing->update($content);
            $existing->requirements()->sync($ids);
            $updated++;
        }

        return compact('created', 'updated', 'skipped');
    }
}
