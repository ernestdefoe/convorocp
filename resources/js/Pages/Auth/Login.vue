<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
const field = 'width:100%;box-sizing:border-box;margin-top:6px;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:10px 12px;font-size:14px;font-family:inherit';
</script>

<template>
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: var(--cp-bg)">
        <div style="width: 380px; max-width: 100%">
            <div style="display: flex; align-items: center; gap: 11px; justify-content: center; margin-bottom: 22px">
                <div style="width: 34px; height: 34px; border-radius: 10px; background: var(--cp-ind); display: flex; align-items: center; justify-content: center">
                    <i class="ti ti-sailboat" style="font-size: 20px; color: #fff" aria-hidden="true"></i>
                </div>
                <span style="font-size: 20px; font-weight: 600; letter-spacing: -0.02em">ConvoroCP</span>
            </div>
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 16px; padding: 24px">
                <div style="font-size: 16px; font-weight: 600; margin-bottom: 18px">Sign in</div>
                <form @submit.prevent="submit">
                    <label style="display: block; font-size: 12.5px; color: var(--cp-mut); font-weight: 500">Email
                        <input v-model="form.email" type="email" autocomplete="username" :style="field" />
                    </label>
                    <p v-if="form.errors.email" style="color: var(--cp-red); font-size: 12px; margin: 6px 0 0">{{ form.errors.email }}</p>
                    <label style="display: block; font-size: 12.5px; color: var(--cp-mut); font-weight: 500; margin-top: 14px">Password
                        <input v-model="form.password" type="password" autocomplete="current-password" :style="field" />
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--cp-mut); margin-top: 14px">
                        <input v-model="form.remember" type="checkbox" /> Remember me
                    </label>
                    <button type="submit" :disabled="form.processing"
                        style="width: 100%; margin-top: 18px; background: var(--cp-ind); color: #fff; border: 0; border-radius: 10px; padding: 11px; font-size: 14px; font-weight: 500; cursor: pointer; font-family: inherit">
                        {{ form.processing ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>
            </div>
            <p style="text-align: center; font-size: 11.5px; color: var(--cp-dim); margin-top: 16px; line-height: 1.6">
                Demo · operator@convorocp.test / client@convorocp.test<br>password: <span style="font-family: ui-monospace, monospace">password</span>
            </p>
        </div>
    </div>
</template>
