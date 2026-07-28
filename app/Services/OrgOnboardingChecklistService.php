<?php

namespace App\Services;

use App\Models\OrgPolicy;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;

class OrgOnboardingChecklistService
{
    /**
     * @return array{
     *     visible: bool,
     *     can_dismiss: bool,
     *     dismiss_href: string|null,
     *     items: list<array{
     *         key: string,
     *         label_key: string,
     *         href: string,
     *         done: bool,
     *         optional: bool
     *     }>
     * }|null
     */
    public function build(Organization $organization, User $user): ?array
    {
        if ($organization->onboarding_checklist_dismissed_at !== null) {
            return [
                'visible' => false,
                'can_dismiss' => false,
                'dismiss_href' => null,
                'items' => [],
            ];
        }

        $items = [];

        $items[] = [
            'key' => 'settings',
            'label_key' => 'dashboard.onboarding.items.settings',
            'href' => route('profile.edit'),
            'done' => $this->settingsDone($organization, $user),
            'optional' => false,
        ];

        if ($user->canManageUsers($organization)) {
            $items[] = [
                'key' => 'users',
                'label_key' => 'dashboard.onboarding.items.users',
                'href' => route('users.index'),
                'done' => $organization->users()->count() >= 2,
                'optional' => false,
            ];
        }

        if ($user->canViewControls($organization)) {
            $items[] = [
                'key' => 'controls',
                'label_key' => 'dashboard.onboarding.items.controls',
                'href' => route('controls.index'),
                'done' => $organization->controls()->exists(),
                'optional' => false,
            ];
        }

        if ($user->canViewProducts($organization)) {
            $items[] = [
                'key' => 'policies',
                'label_key' => 'dashboard.onboarding.items.policies',
                'href' => route('policies.index'),
                'done' => OrgPolicy::query()
                    ->where('organization_id', $organization->id)
                    ->exists(),
                'optional' => false,
            ];

            $items[] = [
                'key' => 'customers',
                'label_key' => 'dashboard.onboarding.items.customers',
                'href' => route('customers.index'),
                'done' => $organization->customers()->exists(),
                'optional' => true,
            ];
        }

        $canDismiss = $user->can('update', $organization);

        return [
            'visible' => $items !== [],
            'can_dismiss' => $canDismiss,
            'dismiss_href' => $canDismiss
                ? route('dashboard.onboarding.dismiss')
                : null,
            'items' => $items,
        ];
    }

    public function dismiss(Organization $organization): void
    {
        if ($organization->onboarding_checklist_dismissed_at !== null) {
            return;
        }

        $organization->update([
            'onboarding_checklist_dismissed_at' => Carbon::now(),
        ]);
    }

    private function settingsDone(Organization $organization, User $user): bool
    {
        if (
            $user->updated_at !== null && $user->created_at !== null
            && $user->updated_at->gt($user->created_at)
        ) {
            return true;
        }

        return $organization->updated_at !== null
            && $organization->created_at !== null
            && $organization->updated_at->gt($organization->created_at);
    }
}
