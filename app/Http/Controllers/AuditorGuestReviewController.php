<?php

namespace App\Http\Controllers;

use App\Enums\AuditorFindingSeverity;
use App\Enums\AuditorFindingStatus;
use App\Models\AuditorFinding;
use App\Models\AuditorReviewPackage;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Services\AuditorFindingService;
use App\Services\AuditorReviewPackageService;
use App\Services\ProductReadinessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditorGuestReviewController extends Controller
{
    public function __construct(
        private readonly AuditorReviewPackageService $packages,
        private readonly AuditorFindingService $findings,
        private readonly ProductReadinessService $readiness,
    ) {
    }

    public function show(Request $request, string $token): Response
    {
        $package = $this->packages->findPackageByGuestToken($token);

        if ($package === null) {
            abort(404, 'This guest review link is invalid or has expired.');
        }

        $this->packages->touchGuestLinkAccess($package);

        $package->loadMissing([
            'organization:id,name,slug',
            'product',
            'creator:id,name',
            'evidence:id,title,type,freshness_status,confidentiality',
            'findings' => fn($query) => $query
                ->with('creator:id,name')
                ->orderByDesc('id'),
        ]);

        $product = $package->product;
        abort_if($product === null, 404);

        $product->load([
            'productOwner:id,name,email',
            'securityContact:id,name,email',
            'versions' => fn($query) => $query
                ->orderByDesc('release_date')
                ->orderByDesc('id')
                ->limit(5),
            'supportPeriods' => fn($query) => $query
                ->orderBy('type')
                ->orderByDesc('id'),
        ]);

        $organization = $package->organization
            ?? Organization::query()->findOrFail($package->organization_id);

        return Inertia::render('auditor/GuestShow', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'package' => $this->detailPayload($package),
            'product' => $this->passportProductPayload($product),
            'report' => $this->readiness->build($product),
            'findings' => $package->findings
                ->map(fn(AuditorFinding $finding) => $this->findings->payload($finding))
                ->values()
                ->all(),
            'findingOptions' => [
                'severities' => array_map(
                    fn(AuditorFindingSeverity $severity) => $severity->value,
                    AuditorFindingSeverity::cases(),
                ),
                'statuses' => array_map(
                    fn(AuditorFindingStatus $status) => $status->value,
                    AuditorFindingStatus::cases(),
                ),
            ],
            'guest' => [
                'expires_at' => $package->guest_token_expires_at?->toIso8601String(),
                'view_only' => true,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(AuditorReviewPackage $package): array
    {
        return [
            'id' => $package->id,
            'title' => $package->title,
            'status' => $package->status->value,
            'notes' => $package->notes,
            'product_id' => $package->product_id,
            'product_name' => $package->product?->name ?? '',
            'product_slug' => $package->product?->slug ?? '',
            'shared_at' => $package->shared_at?->toIso8601String(),
            'closed_at' => $package->closed_at?->toIso8601String(),
            'created_by_name' => $package->creator?->name,
            'evidence_ids' => $package->evidence->pluck('id')->map(fn($id) => (int) $id)->all(),
            'evidence' => $package->evidence
                ->map(fn(Evidence $evidence) => [
                    'id' => $evidence->id,
                    'title' => $evidence->title,
                    'type' => $evidence->type->value,
                    'freshness_status' => $evidence->freshness_status?->value,
                    'confidentiality' => $evidence->confidentiality?->value,
                ])
                ->values()
                ->all(),
            'is_editable' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function passportProductPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'manufacturer' => $product->manufacturer,
            'trademark' => $product->trademark,
            'product_type' => $product->product_type?->value,
            'licensing_model' => $product->licensing_model?->value,
            'scope_status' => $product->scope_status?->value,
            'classification_status' => $product->classification_status?->value,
            'intended_purpose' => $product->intended_purpose,
            'product_owner' => $product->productOwner
                ? [
                    'id' => $product->productOwner->id,
                    'name' => $product->productOwner->name,
                    'email' => $product->productOwner->email,
                ]
                : null,
            'security_contact' => $product->securityContact
                ? [
                    'id' => $product->securityContact->id,
                    'name' => $product->securityContact->name,
                    'email' => $product->securityContact->email,
                ]
                : null,
            'versions' => $product->versions->map(fn($version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'state' => $version->state?->value,
                'support_status' => $version->support_status?->value,
                'release_date' => $version->release_date?->toDateString(),
            ])->values()->all(),
            'support_periods' => $product->supportPeriods->map(fn($period) => [
                'id' => $period->id,
                'type' => $period->type->value,
                'start_basis' => $period->start_basis->value,
                'duration_months' => $period->duration_months,
                'effective_starts_at' => $period->effectiveStartsAt()?->toDateString(),
                'effective_ends_at' => $period->effectiveEndsAt()?->toDateString(),
                'schedule_resolved' => $period->scheduleResolved(),
                'basis' => $period->basis,
                'is_extended' => $period->is_extended,
            ])->values()->all(),
        ];
    }
}
