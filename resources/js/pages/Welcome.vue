<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, ShieldAlert } from '@lucide/vue';
import AppearanceSwitcher from '@/components/AppearanceSwitcher.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { login } from '@/routes';

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('welcome.head_title')" />

    <div class="min-h-screen bg-background">
        <header class="border-b">
            <div
                class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-4"
            >
                <p class="text-sm font-semibold">{{ t('welcome.brand') }}</p>
                <div class="flex items-center gap-3">
                    <LocaleSwitcher />
                    <AppearanceSwitcher />
                    <Button as-child>
                        <Link :href="login()">{{ t('welcome.sign_in') }}</Link>
                    </Button>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl space-y-16 px-6 py-12">
            <section class="space-y-5">
                <p class="text-sm font-medium text-muted-foreground">
                    {{ t('welcome.eyebrow') }}
                </p>
                <h1 class="max-w-4xl text-4xl font-semibold tracking-tight">
                    {{ t('welcome.title') }}
                </h1>
                <p class="max-w-3xl text-base text-muted-foreground">
                    {{ t('welcome.description') }}
                </p>
                <Button as-child size="lg">
                    <Link
                        :href="login()"
                        class="inline-flex items-center gap-2"
                    >
                        {{ t('welcome.cta') }}
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
            </section>

            <section class="grid gap-4 md:grid-cols-5">
                <div class="rounded-lg border p-4 text-sm">
                    {{ t('welcome.chain.requirement') }}
                </div>
                <div class="rounded-lg border p-4 text-sm">
                    {{ t('welcome.chain.risk') }}
                </div>
                <div class="rounded-lg border p-4 text-sm">
                    {{ t('welcome.chain.control') }}
                </div>
                <div class="rounded-lg border p-4 text-sm">
                    {{ t('welcome.chain.evidence') }}
                </div>
                <div class="rounded-lg border p-4 text-sm">
                    {{ t('welcome.chain.release') }}
                </div>
            </section>

            <section>
                <h2 class="mb-5 text-2xl font-semibold">
                    {{ t('welcome.modules_title') }}
                </h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg border p-5">
                        <p class="font-medium">
                            {{ t('welcome.modules.organization_title') }}
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ t('welcome.modules.organization_description') }}
                        </p>
                    </div>
                    <div class="rounded-lg border p-5">
                        <p class="font-medium">
                            {{ t('welcome.modules.products_title') }}
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ t('welcome.modules.products_description') }}
                        </p>
                    </div>
                    <div class="rounded-lg border p-5">
                        <p class="font-medium">
                            {{ t('welcome.modules.risk_title') }}
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ t('welcome.modules.risk_description') }}
                        </p>
                    </div>
                    <div class="rounded-lg border p-5">
                        <p class="font-medium">
                            {{ t('welcome.modules.vulnerabilities_title') }}
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{
                                t('welcome.modules.vulnerabilities_description')
                            }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border p-5">
                    <p class="mb-3 text-sm font-semibold">
                        {{ t('welcome.dates_title') }}
                    </p>
                    <ul class="space-y-2 text-sm text-muted-foreground">
                        <li class="flex items-start gap-2">
                            <CheckCircle2 class="mt-0.5 size-4" />
                            {{ t('welcome.date_reporting') }}
                        </li>
                        <li class="flex items-start gap-2">
                            <CheckCircle2 class="mt-0.5 size-4" />
                            {{ t('welcome.date_requirements') }}
                        </li>
                    </ul>
                </div>
                <div class="rounded-lg border p-5">
                    <p class="mb-3 text-sm font-semibold">
                        {{ t('welcome.important_title') }}
                    </p>
                    <p class="flex gap-2 text-sm text-muted-foreground">
                        <ShieldAlert class="mt-0.5 size-4 shrink-0" />
                        {{ t('welcome.disclaimer') }}
                    </p>
                </div>
            </section>
        </main>
    </div>
</template>
