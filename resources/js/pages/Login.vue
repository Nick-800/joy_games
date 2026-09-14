<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Gamepad2, Mail, Lock, AlertCircle } from 'lucide-vue-next';

const form = useForm({
    email: 'cashier@joygames.ly',
    password: 'password',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => {
            form.reset('password');
        },
    });
}
</script>

<template>
    <Head title="Login" />

    <div class="min-h-screen bg-surface-canvas text-text-primary flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="flex flex-col items-center gap-3 mb-8">
                <div class="w-14 h-14 rounded-2xl bg-brand-primary flex items-center justify-center shadow-lg">
                    <Gamepad2 class="w-8 h-8" />
                </div>
                <h1 class="text-2xl font-semibold tracking-tight">JOY GAMES</h1>
                <span class="text-xs px-2 py-0.5 rounded-full bg-surface-overlay text-text-muted font-semibold border border-surface-border-subtle uppercase tracking-wide">
                    PS5 Lounge Operations
                </span>
            </div>

            <div class="bg-surface-elevated border border-surface-border rounded-2xl shadow-xl p-6 sm:p-8">
                <h2 class="text-lg font-semibold mb-1">Sign in to continue</h2>
                <p class="text-xs text-text-muted mb-6">Use your cashier or admin credentials.</p>

                <form @submit.prevent="submit" class="flex flex-col gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-1.5">
                            Email
                        </label>
                        <div class="relative">
                            <Mail class="w-4 h-4 text-text-muted absolute left-3 top-3" />
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                class="w-full pl-9 pr-3 py-2.5 bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary text-sm focus:outline-none focus:border-brand-primary placeholder:text-text-muted"
                                placeholder="cashier@joygames.ly"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-1.5">
                            Password
                        </label>
                        <div class="relative">
                            <Lock class="w-4 h-4 text-text-muted absolute left-3 top-3" />
                            <input
                                v-model="form.password"
                                type="password"
                                required
                                autocomplete="current-password"
                                class="w-full pl-9 pr-3 py-2.5 bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary text-sm focus:outline-none focus:border-brand-primary placeholder:text-text-muted"
                                placeholder="password"
                            />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-xs text-text-secondary cursor-pointer">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="w-4 h-4 rounded border-surface-border bg-surface-canvas text-brand-primary focus:ring-brand-primary"
                        />
                        Remember me on this device
                    </label>

                    <div
                        v-if="form.errors.email"
                        class="p-3 rounded-lg bg-status-rogue/10 border border-status-rogue/30 text-xs text-status-rogue flex items-center gap-2"
                    >
                        <AlertCircle class="w-4 h-4 shrink-0" />
                        <span>{{ form.errors.email }}</span>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 rounded-lg bg-brand-primary hover:bg-brand-primary-hover disabled:opacity-50 text-text-primary font-semibold text-sm transition flex items-center justify-center gap-2 cursor-pointer"
                    >
                        {{ form.processing ? 'Signing in...' : 'Sign In' }}
                    </button>
                </form>

                <div class="mt-6 pt-5 border-t border-surface-border-subtle">
                    <p class="text-xs text-text-muted text-center">
                        Default users:
                        <span class="font-mono text-text-secondary">cashier@joygames.ly / password</span>
                        ·
                        <span class="font-mono text-text-secondary">admin@joygames.ly / password</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
