<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronUp,
    Pencil,
    Trash2,
    WandSparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { setProductModuleOrigin } from '@/composables/useProductModuleBack';
import { useTranslations } from '@/composables/useTranslations';
import {
    aggregateProductModuleStatus,
    canAccessProductModule,
    productModules,
    productModuleStatusClass,
    productModuleStatusReasonLabel,
} from '@/pages/products/columns';
import type {
    ProductListItem,
    ProductModuleStatus,
} from '@/pages/products/columns';
import { edit as editProduct } from '@/routes/products';
import { show as wizardShow } from '@/routes/products/wizard';

const props = defineProps<{
    product: ProductListItem;
    canManage: boolean;
}>();

const emit = defineEmits<{
    delete: [productId: number];
}>();

const { t } = useTranslations();
const page = usePage();
const authUser = computed(() => page.props.auth.user);

const modulesExpanded = ref(false);

const titleStatus = computed(() =>
    aggregateProductModuleStatus(props.product.module_statuses),
);

const cardModules = computed(() =>
    productModules.filter((module) => module.key !== 'assistant'),
);

const assistantModule = computed(
    () => productModules.find((module) => module.key === 'assistant') ?? null,
);

const canAccessAssistant = computed(() =>
    assistantModule.value
        ? canAccessProductModule(assistantModule.value, authUser.value)
        : false,
);

const showFooter = computed(() => props.canManage || canAccessAssistant.value);

const modulesToggleLabel = computed(() =>
    modulesExpanded.value
        ? t('products.hide_all_modules')
        : t('products.show_all_modules'),
);

const moduleStatus = (key: string): ProductModuleStatus =>
    props.product.module_statuses?.[key] ?? 'empty';

const moduleCount = (key: string): number | null => {
    const count = props.product.module_counts?.[key];

    if (count === undefined || count <= 0) {
        return null;
    }

    return count;
};

const moduleReasonLabel = (key: string): string =>
    productModuleStatusReasonLabel(
        t,
        props.product.module_status_reasons?.[key],
        moduleStatus(key),
    );

const toggleModules = (): void => {
    modulesExpanded.value = !modulesExpanded.value;
};

const openModule = (href: string): void => {
    setProductModuleOrigin(props.product.id, 'index');
    router.visit(href);
};

const openEdit = (): void => {
    router.visit(editProduct(props.product.id).url);
};

const openWizard = (): void => {
    router.visit(wizardShow(props.product.id).url);
};
</script>

<template>
    <Card class="gap-0 overflow-hidden py-0">
        <CardHeader class="gap-2 border-b px-4 py-4 [.border-b]:pb-4">
            <CardTitle
                class="text-base leading-snug"
                :class="productModuleStatusClass(titleStatus)"
            >
                <Link
                    :href="wizardShow(product.id).url"
                    class="hover:underline"
                >
                    {{ product.name }}
                </Link>
            </CardTitle>
            <Button
                variant="outline"
                size="sm"
                class="w-full cursor-pointer"
                @click="openWizard"
            >
                <WandSparkles class="size-3.5" />
                {{ t('products.wizard_link') }}
            </Button>
        </CardHeader>

        <TooltipProvider :delay-duration="300">
            <CardContent class="space-y-0 p-0">
                <button
                    type="button"
                    class="grid w-full cursor-pointer grid-cols-[minmax(0,1fr)_auto] items-center gap-2 rounded-none px-4 py-1.5 text-left transition-colors hover:bg-muted/60"
                    :aria-expanded="modulesExpanded"
                    @click="toggleModules"
                >
                    <span
                        class="truncate text-sm font-medium text-muted-foreground hover:text-foreground"
                    >
                        {{ modulesToggleLabel }}
                    </span>
                    <span
                        class="inline-flex h-7 w-13 shrink-0 items-center justify-center text-muted-foreground"
                        aria-hidden="true"
                    >
                        <ChevronUp v-if="modulesExpanded" class="size-4" />
                        <ChevronDown v-else class="size-4" />
                    </span>
                </button>

                <div v-if="modulesExpanded" class="divide-y border-t">
                    <button
                        v-for="module in cardModules"
                        :key="module.key"
                        type="button"
                        class="grid w-full grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2 px-4 py-2.5 text-left transition-colors"
                        :class="
                            canAccessProductModule(module, authUser)
                                ? 'cursor-pointer hover:bg-muted/60'
                                : 'cursor-not-allowed opacity-50'
                        "
                        :disabled="!canAccessProductModule(module, authUser)"
                        :title="
                            canAccessProductModule(module, authUser)
                                ? undefined
                                : t('common.no_access')
                        "
                        @click="
                            canAccessProductModule(module, authUser) &&
                            openModule(module.href(product.id))
                        "
                    >
                        <component
                            :is="module.icon"
                            class="size-4 shrink-0"
                            :class="
                                productModuleStatusClass(
                                    moduleStatus(module.key),
                                )
                            "
                        />
                        <div class="min-w-0">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <p
                                        class="w-fit max-w-full truncate text-sm leading-tight font-medium"
                                        :class="
                                            productModuleStatusClass(
                                                moduleStatus(module.key),
                                            )
                                        "
                                    >
                                        {{ t(module.labelKey) }}
                                    </p>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="top"
                                    class="max-w-xs text-left leading-relaxed"
                                >
                                    {{ moduleReasonLabel(module.key) }}
                                </TooltipContent>
                            </Tooltip>
                            <p
                                class="truncate text-xs leading-tight text-muted-foreground"
                            >
                                {{ t(module.descriptionKey) }}
                            </p>
                        </div>
                        <span
                            v-if="moduleCount(module.key) !== null"
                            class="inline-flex h-7 min-w-7 shrink-0 items-center justify-center text-xs text-muted-foreground/70 tabular-nums"
                        >
                            {{ moduleCount(module.key) }}
                        </span>
                    </button>
                </div>
            </CardContent>

            <CardFooter
                v-if="showFooter"
                class="justify-between gap-2 border-t px-4 py-3 [.border-t]:pt-3"
            >
                <Tooltip v-if="assistantModule">
                    <TooltipTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon-sm"
                            class="cursor-pointer"
                            :disabled="!canAccessAssistant"
                            :aria-label="t(assistantModule.labelKey)"
                            @click="
                                canAccessAssistant &&
                                openModule(assistantModule.href(product.id))
                            "
                        >
                            <component
                                :is="assistantModule.icon"
                                class="size-4"
                            />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent
                        side="top"
                        class="max-w-xs text-left leading-relaxed"
                    >
                        {{ t(assistantModule.descriptionKey) }}
                    </TooltipContent>
                </Tooltip>
                <span v-else />

                <div v-if="canManage" class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="cursor-pointer"
                        @click="openEdit"
                    >
                        <Pencil class="size-3.5" />
                        {{ t('common.edit') }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="cursor-pointer text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="emit('delete', product.id)"
                    >
                        <Trash2 class="size-3.5" />
                        {{ t('common.delete') }}
                    </Button>
                </div>
            </CardFooter>
        </TooltipProvider>
    </Card>
</template>
