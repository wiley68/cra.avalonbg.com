<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ScrollText } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { index as policiesIndex } from '@/routes/policies';

const props = defineProps<{
    types: string[];
}>();

const { t } = useTranslations();

const typeLabel = (value: string): string => {
    const key = `policies.types.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const hrefForType = (type: string): string =>
    policiesIndex({ query: { policy_type: type } }).url;
</script>

<template>
    <section
        v-if="props.types.length > 0"
        class="space-y-3 rounded-lg border p-6"
    >
        <div>
            <h2
                class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ t('policies.related_title') }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ t('policies.related_help') }}
            </p>
        </div>

        <ul class="flex flex-wrap gap-2">
            <li v-for="type in props.types" :key="type">
                <Button as-child variant="outline" size="sm">
                    <Link :href="hrefForType(type)">
                        <ScrollText class="h-4 w-4" />
                        {{ typeLabel(type) }}
                    </Link>
                </Button>
            </li>
        </ul>
    </section>
</template>
