<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { setProductModuleOrigin } from '@/composables/useProductModuleBack';
import { useTranslations } from '@/composables/useTranslations';
import {
    productEnumLabel,
    productModules,
    productModuleStatusClass,
} from '@/pages/products/columns';
import type {
    ProductListItem,
    ProductModuleStatus,
} from '@/pages/products/columns';
import { edit as editProduct } from '@/routes/products';

const props = defineProps<{
    product: ProductListItem;
    canManage: boolean;
}>();

const emit = defineEmits<{
    delete: [productId: number];
}>();

const { t } = useTranslations();

const typeLabel = computed(() =>
    productEnumLabel(t, 'types', props.product.product_type),
);

const scopeLabel = computed(() =>
    productEnumLabel(t, 'scope', props.product.scope_status),
);

const classificationLabel = computed(() =>
    productEnumLabel(t, 'classification', props.product.classification_status),
);

const moduleStatus = (key: string): ProductModuleStatus =>
    props.product.module_statuses?.[key] ?? 'empty';

const openModule = (href: string): void => {
    setProductModuleOrigin(props.product.id, 'index');
    router.visit(href);
};

const openEdit = (): void => {
    router.visit(editProduct(props.product.id).url);
};
</script>

<template>
    <Card class="gap-0 overflow-hidden py-0">
        <CardHeader class="gap-2 border-b px-4 py-4 [.border-b]:pb-4">
            <CardTitle class="text-base leading-snug">
                {{ product.name }}
            </CardTitle>
            <CardDescription class="text-xs leading-relaxed">
                <span
                    >{{ t('products.columns.product_type') }}:
                    {{ typeLabel }}</span
                >
                <span class="mx-1.5 text-border">·</span>
                <span
                    >{{ t('products.columns.scope_status') }}:
                    {{ scopeLabel }}</span
                >
                <span class="mx-1.5 text-border">·</span>
                <span
                    >{{ t('products.columns.classification_status') }}:
                    {{ classificationLabel }}</span
                >
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-2 px-4 py-3">
            <div
                v-for="module in productModules"
                :key="module.key"
                class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2"
            >
                <component
                    :is="module.icon"
                    class="size-4 shrink-0"
                    :class="productModuleStatusClass(moduleStatus(module.key))"
                />
                <div class="min-w-0">
                    <p
                        class="truncate text-sm leading-tight font-medium"
                        :class="
                            productModuleStatusClass(moduleStatus(module.key))
                        "
                    >
                        {{ t(module.labelKey) }}
                    </p>
                    <p
                        class="truncate text-xs leading-tight text-muted-foreground"
                    >
                        {{ t(module.descriptionKey) }}
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-7 px-2 text-xs"
                    @click="openModule(module.href(product.id))"
                >
                    {{ t('dashboard.open') }}
                </Button>
            </div>
        </CardContent>

        <CardFooter
            v-if="canManage"
            class="justify-end gap-2 border-t px-4 py-3 [.border-t]:pt-3"
        >
            <Button variant="outline" size="sm" @click="openEdit">
                <Pencil class="mr-1.5 size-3.5" />
                {{ t('common.edit') }}
            </Button>
            <Button
                variant="destructive"
                size="sm"
                @click="emit('delete', product.id)"
            >
                <Trash2 class="mr-1.5 size-3.5" />
                {{ t('common.delete') }}
            </Button>
        </CardFooter>
    </Card>
</template>
