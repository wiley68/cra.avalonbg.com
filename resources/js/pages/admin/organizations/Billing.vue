<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, BadgeCheck, Pencil, Users } from '@lucide/vue';
import { ref } from 'vue';
import BillingDocumentsPanel from '@/components/billing/BillingDocumentsPanel.vue';
import HeaderActionButton from '@/components/HeaderActionButton.vue';
import PageFormHeader from '@/components/PageFormHeader.vue';
import { Button } from '@/components/ui/button';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import {
    activateBilling as activateBillingRoute,
    billing as organizationsBilling,
    edit as organizationsEdit,
    index as organizationsIndex,
} from '@/routes/admin/organizations';
import {
    destroy as destroyBillingDocument,
    download as downloadBillingDocument,
    send as sendBillingDocument,
    store as storeBillingDocument,
} from '@/routes/admin/organizations/billing-documents';
import { index as organizationUsersIndex } from '@/routes/admin/organizations/users';

type OrganizationPayload = {
    id: number;
    name: string;
    slug: string;
    billing_email: string | null;
    subscription_plan: string | null;
    billing_status: string;
    billing_interval: string | null;
    payment_method: string | null;
    billing_activated_at: string | null;
};

type PendingBankPayment = {
    id: number;
    subscription_plan: string;
    billing_interval: string;
    amount_eur: number;
    currency: string;
    payment_reference: string;
    status: string;
    created_at: string | null;
} | null;

type BillingDocumentItem = {
    id: number;
    type: string;
    title: string;
    source_filename: string;
    size_bytes: number;
    mime_type: string | null;
    sent_at: string | null;
    sent_to_email: string | null;
    notes: string | null;
    created_at: string | null;
};

const props = defineProps<{
    organization: OrganizationPayload;
    pendingBankPayment: PendingBankPayment;
    canActivateBilling: boolean;
    billingDocuments: BillingDocumentItem[];
    documentRecipientEmail: string | null;
    documentTypes: string[];
}>();

const { t } = useTranslations();
const activating = ref(false);

usePageBreadcrumbs(() => [
    { titleKey: 'nav.organizations', href: organizationsIndex() },
    {
        title: props.organization.name,
        href: organizationsEdit(props.organization.id),
    },
    {
        titleKey: 'admin.organizations.billing_page_title',
        href: organizationsBilling(props.organization.id),
    },
]);

const planLabel = (value: string | null): string =>
    t(`admin.organizations.plans.${value ?? 'free'}`);

const activateBilling = () => {
    activating.value = true;
    router.post(
        activateBillingRoute(props.organization.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                activating.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="t('admin.organizations.billing_page_title')" />

    <div class="mx-auto w-full max-w-2xl space-y-6">
        <PageFormHeader>
            <div>
                <h1 class="text-xl font-semibold">
                    {{ t('admin.organizations.billing_page_title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ organization.name }}
                </p>
            </div>
            <template #actions>
                <HeaderActionButton
                    is-back
                    :label="t('common.back')"
                    :href="organizationsIndex()"
                >
                    <ArrowLeft class="h-4 w-4" />
                </HeaderActionButton>
                <HeaderActionButton
                    :label="t('common.edit')"
                    :href="organizationsEdit(organization.id)"
                >
                    <Pencil class="h-4 w-4" />
                </HeaderActionButton>
                <HeaderActionButton
                    :label="t('nav.users')"
                    :href="organizationUsersIndex(organization.id)"
                >
                    <Users class="h-4 w-4" />
                </HeaderActionButton>
            </template>
        </PageFormHeader>

        <div class="space-y-4 rounded-lg border p-5">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ t('admin.organizations.subscription_plan') }}
                    </p>
                    <p class="font-medium">
                        {{ planLabel(organization.subscription_plan) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ t('admin.organizations.billing_email') }}
                    </p>
                    <p class="font-medium">
                        {{ organization.billing_email || '—' }}
                    </p>
                </div>
            </div>

            <div class="space-y-3 rounded-md border bg-muted/30 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium">
                            {{ t('admin.organizations.billing_section') }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                t(
                                    'billing.status.' +
                                        organization.billing_status,
                                )
                            }}
                            <span v-if="organization.billing_interval">
                                · {{ organization.billing_interval }}
                            </span>
                        </p>
                    </div>
                    <Button
                        v-if="canActivateBilling"
                        type="button"
                        variant="outline"
                        :disabled="activating"
                        @click="activateBilling"
                    >
                        <BadgeCheck class="h-4 w-4" />
                        {{ t('admin.organizations.activate_billing') }}
                    </Button>
                </div>

                <div
                    v-if="pendingBankPayment"
                    class="space-y-1 text-sm text-muted-foreground"
                >
                    <p>
                        {{ t('billing.payment_reference') }}:
                        <span class="font-mono text-foreground">{{
                            pendingBankPayment.payment_reference
                        }}</span>
                    </p>
                    <p>
                        {{ t('billing.amount') }}: €{{
                            pendingBankPayment.amount_eur
                        }}
                    </p>
                </div>
            </div>
        </div>

        <BillingDocumentsPanel
            :documents="billingDocuments"
            :can-manage="true"
            :document-types="documentTypes"
            :recipient-email="documentRecipientEmail"
            :store-url="storeBillingDocument(organization.id).url"
            :download-url="
                (id) =>
                    downloadBillingDocument({
                        organization: organization.id,
                        document: id,
                    }).url
            "
            :send-url="
                (id) =>
                    sendBillingDocument({
                        organization: organization.id,
                        document: id,
                    }).url
            "
            :destroy-url="
                (id) =>
                    destroyBillingDocument({
                        organization: organization.id,
                        document: id,
                    }).url
            "
        />
    </div>
</template>
