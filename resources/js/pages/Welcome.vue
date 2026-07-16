<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, ShieldAlert } from '@lucide/vue';
import AppearanceSwitcher from '@/components/AppearanceSwitcher.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Panel, PanelDescription, PanelTitle } from '@/components/ui/panel';
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
                <Panel variant="success">
                    {{ t('welcome.chain.requirement') }}
                </Panel>
                <Panel variant="success">
                    {{ t('welcome.chain.risk') }}
                </Panel>
                <Panel variant="success">
                    {{ t('welcome.chain.control') }}
                </Panel>
                <Panel variant="success">
                    {{ t('welcome.chain.evidence') }}
                </Panel>
                <Panel variant="success">
                    {{ t('welcome.chain.release') }}
                </Panel>
            </section>

            <section>
                <h2 class="mb-5 text-2xl font-semibold">
                    {{ t('welcome.modules_title') }}
                </h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <Panel size="lg">
                        <PanelTitle>
                            {{ t('welcome.modules.organization_title') }}
                        </PanelTitle>
                        <PanelDescription>
                            {{ t('welcome.modules.organization_description') }}
                        </PanelDescription>
                    </Panel>
                    <Panel size="lg">
                        <PanelTitle>
                            {{ t('welcome.modules.products_title') }}
                        </PanelTitle>
                        <PanelDescription>
                            {{ t('welcome.modules.products_description') }}
                        </PanelDescription>
                    </Panel>
                    <Panel size="lg">
                        <PanelTitle>
                            {{ t('welcome.modules.risk_title') }}
                        </PanelTitle>
                        <PanelDescription>
                            {{ t('welcome.modules.risk_description') }}
                        </PanelDescription>
                    </Panel>
                    <Panel size="lg">
                        <PanelTitle>
                            {{ t('welcome.modules.vulnerabilities_title') }}
                        </PanelTitle>
                        <PanelDescription>
                            {{
                                t('welcome.modules.vulnerabilities_description')
                            }}
                        </PanelDescription>
                    </Panel>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <Panel variant="info" size="lg">
                    <PanelTitle>{{ t('welcome.dates_title') }}</PanelTitle>
                    <PanelDescription>
                        <ul class="space-y-2">
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="mt-0.5 size-4 shrink-0" />
                                {{ t('welcome.date_reporting') }}
                            </li>
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="mt-0.5 size-4 shrink-0" />
                                {{ t('welcome.date_requirements') }}
                            </li>
                        </ul>
                    </PanelDescription>
                </Panel>
                <Panel variant="important" size="lg">
                    <PanelTitle>{{ t('welcome.important_title') }}</PanelTitle>
                    <PanelDescription class="flex gap-2">
                        <ShieldAlert class="mt-0.5 size-4 shrink-0" />
                        <span>{{ t('welcome.disclaimer') }}</span>
                    </PanelDescription>
                </Panel>
            </section>
        </main>
    </div>
</template>
