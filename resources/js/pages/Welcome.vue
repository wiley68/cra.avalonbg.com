<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, ShieldAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
</script>

<template>
    <Head title="CRA Compliance Workspace" />

    <div class="min-h-screen bg-background">
        <header class="border-b">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
                <p class="text-sm font-semibold">Avalon CRA Compliance Workspace</p>
                <Button as-child>
                    <Link :href="login()">Вход</Link>
                </Button>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl space-y-16 px-6 py-12">
            <section class="space-y-5">
                <p class="text-sm font-medium text-muted-foreground">Product security lifecycle for CRA readiness</p>
                <h1 class="max-w-4xl text-4xl font-semibold tracking-tight">
                    CRA Compliance Workspace за малки и средни софтуерни производители
                </h1>
                <p class="max-w-3xl text-base text-muted-foreground">
                    Организира продуктово портфолио, рискове, контроли, доказателства и release approvals в една
                    проследима структура за ежедневна работа и одитна готовност.
                </p>
                <Button as-child size="lg">
                    <Link :href="login()" class="inline-flex items-center gap-2">
                        Вход в Workspace
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
            </section>

            <section class="grid gap-4 md:grid-cols-5">
                <div class="rounded-lg border p-4 text-sm">Regulatory requirement</div>
                <div class="rounded-lg border p-4 text-sm">Risk assessment</div>
                <div class="rounded-lg border p-4 text-sm">Control mapping</div>
                <div class="rounded-lg border p-4 text-sm">Evidence package</div>
                <div class="rounded-lg border p-4 text-sm">Release decision</div>
            </section>

            <section>
                <h2 class="mb-5 text-2xl font-semibold">Ключови модули</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg border p-5">
                        <p class="font-medium">Organization и Users</p>
                        <p class="mt-2 text-sm text-muted-foreground">Роли, права, одобрения и проследима отговорност.</p>
                    </div>
                    <div class="rounded-lg border p-5">
                        <p class="font-medium">Product Register и Versions</p>
                        <p class="mt-2 text-sm text-muted-foreground">Портфолио, версии, support периоди и release история.</p>
                    </div>
                    <div class="rounded-lg border p-5">
                        <p class="font-medium">Risk, Controls и Evidence</p>
                        <p class="mt-2 text-sm text-muted-foreground">Връзка между изискване, риск, контрол и доказателство.</p>
                    </div>
                    <div class="rounded-lg border p-5">
                        <p class="font-medium">Vulnerabilities и Reporting readiness</p>
                        <p class="mt-2 text-sm text-muted-foreground">Следене на срокове, corrective actions и подготовка за докладване.</p>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border p-5">
                    <p class="mb-3 text-sm font-semibold">CRA ключови дати</p>
                    <ul class="space-y-2 text-sm text-muted-foreground">
                        <li class="flex items-start gap-2"><CheckCircle2 class="mt-0.5 size-4" /> 11 септември 2026 — reporting obligations</li>
                        <li class="flex items-start gap-2"><CheckCircle2 class="mt-0.5 size-4" /> 11 декември 2027 — основни CRA изисквания</li>
                    </ul>
                </div>
                <div class="rounded-lg border p-5">
                    <p class="mb-3 text-sm font-semibold">Важно</p>
                    <p class="flex gap-2 text-sm text-muted-foreground">
                        <ShieldAlert class="mt-0.5 size-4 shrink-0" />
                        Платформата не обещава автоматично юридическо съответствие. Тя подпомага процесите, решенията и
                        evidence management за CRA readiness.
                    </p>
                </div>
            </section>
        </main>
    </div>
</template>

