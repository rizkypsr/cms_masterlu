<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';
import { request } from '@/routes/password';
import { store } from '@/routes/login';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <AuthSplitLayout title="Master LU CMS">
        <Head title="Log in" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-5">
                <div class="grid gap-1.5">
                    <Label for="login" class="text-xs text-muted-foreground">
                        Email atau Username
                    </Label>
                    <Input
                        id="login"
                        type="text"
                        name="login"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="username"
                        placeholder="email atau username"
                        class="rounded-full border-gray-300 px-4 py-5"
                    />
                    <InputError :message="errors.login" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="password" class="text-xs text-muted-foreground">
                        Password
                    </Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="••••••"
                        class="rounded-full border-gray-300 px-4 py-5"
                    />
                    <InputError :message="errors.password" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full rounded-full bg-amber-400 py-5 text-sm font-semibold uppercase tracking-wider text-black hover:bg-amber-500"
                    :tabindex="3"
                    :disabled="processing"
                    data-test="login-button"
                    size="lg"
                >
                    <Spinner v-if="processing" />
                    Login Sekarang
                </Button>
            </div>
        </Form>
    </AuthSplitLayout>
</template>
