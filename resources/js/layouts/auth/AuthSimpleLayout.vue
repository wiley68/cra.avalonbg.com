<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';

const props = defineProps<{
    title?: string;
    description?: string;
    titleKey?: string;
    descriptionKey?: string;
}>();

const { t } = useTranslations();

const displayTitle = computed(() =>
    props.titleKey ? t(props.titleKey) : (props.title ?? ''),
);

const displayDescription = computed(() =>
    props.descriptionKey ? t(props.descriptionKey) : (props.description ?? ''),
);
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="absolute top-4 right-4">
            <LocaleSwitcher />
        </div>

        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="mb-1 flex h-9 w-9 items-center justify-center rounded-md"
                        >
                            <AppLogoIcon
                                class="size-9 fill-current text-foreground dark:text-white"
                            />
                        </div>
                        <span class="sr-only">{{ displayTitle }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ displayTitle }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ displayDescription }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
