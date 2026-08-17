<script setup>
import { Head } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';

const props = defineProps({
    stack: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Stack" />

    <div class="flex min-h-screen items-center justify-center bg-neutral-50 p-6 dark:bg-neutral-950">
        <div class="w-full max-w-md">
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-50">
                Accountable
            </h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                La stack tourne. Rien n'est implémenté&nbsp;: à toi de dire quoi construire.
            </p>

            <dl class="mt-8 divide-y divide-neutral-200 dark:divide-neutral-800">
                <div
                    v-for="(version, name) in props.stack"
                    :key="name"
                    class="flex items-center justify-between py-2.5"
                >
                    <dt class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                        <Check class="size-4 text-emerald-600 dark:text-emerald-500" />
                        {{ name }}
                    </dt>
                    <dd class="font-mono text-sm text-neutral-500 dark:text-neutral-400">
                        {{ version }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</template>
