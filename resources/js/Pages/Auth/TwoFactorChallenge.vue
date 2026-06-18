<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const brand = computed(() => usePage().props.brand ?? { name: 'ConvoroCP', logo: null });
const useRecovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });

function submit() {
    form.transform((d) => useRecovery.value ? { recovery_code: d.recovery_code } : { code: d.code })
        .post('/two-factor-challenge', { onFinish: () => form.reset('code', 'recovery_code') });
}
const field = 'width:100%;box-sizing:border-box;margin-top:6px;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:10px 12px;font-size:14px;font-family:inherit';
</script>

<template>
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: var(--cp-bg)">
        <div style="width: 380px; max-width: 100%">
            <div style="display: flex; align-items: center; gap: 11px; justify-content: center; margin-bottom: 22px">
                <img v-if="brand.logo" :src="brand.logo" :alt="brand.name" style="width: 34px; height: 34px; border-radius: 10px; object-fit: contain" />
                <div v-else style="width: 34px; height: 34px; border-radius: 10px; background: var(--cp-ind); display: flex; align-items: center; justify-content: center">
                    <i class="ti ti-shield-lock" style="font-size: 20px; color: #fff" aria-hidden="true"></i>
                </div>
                <span style="font-size: 20px; font-weight: 600; letter-spacing: -0.02em">{{ brand.name }}</span>
            </div>
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 16px; padding: 24px">
                <div style="font-size: 16px; font-weight: 600; margin-bottom: 6px">Two-factor authentication</div>
                <div style="font-size: 12.5px; color: var(--cp-dim); margin-bottom: 18px">{{ useRecovery ? 'Enter one of your recovery codes.' : 'Enter the 6-digit code from your authenticator app.' }}</div>
                <form @submit.prevent="submit">
                    <label v-if="!useRecovery" style="display: block; font-size: 12.5px; color: var(--cp-mut); font-weight: 500">Authentication code
                        <input v-model="form.code" inputmode="numeric" autocomplete="one-time-code" autofocus placeholder="123456" :style="field" />
                    </label>
                    <label v-else style="display: block; font-size: 12.5px; color: var(--cp-mut); font-weight: 500">Recovery code
                        <input v-model="form.recovery_code" autocomplete="one-time-code" placeholder="xxxxx-xxxxx" :style="field" />
                    </label>
                    <p v-if="form.errors.code" style="color: var(--cp-red); font-size: 12px; margin: 6px 0 0">{{ form.errors.code }}</p>
                    <button type="submit" :disabled="form.processing"
                        style="width: 100%; margin-top: 18px; background: var(--cp-ind); color: #fff; border: 0; border-radius: 10px; padding: 11px; font-size: 14px; font-weight: 500; cursor: pointer; font-family: inherit">
                        {{ form.processing ? 'Verifying…' : 'Verify' }}
                    </button>
                </form>
                <button type="button" @click="useRecovery = !useRecovery" style="width: 100%; margin-top: 12px; background: transparent; border: 0; color: var(--cp-vio); font-size: 12.5px; cursor: pointer; font-family: inherit">
                    {{ useRecovery ? 'Use an authentication code' : 'Use a recovery code instead' }}
                </button>
            </div>
            <p style="text-align: center; font-size: 12px; color: var(--cp-mut); margin-top: 16px">
                <a href="/login" style="color: var(--cp-vio); text-decoration: none">Back to sign in</a>
            </p>
        </div>
    </div>
</template>
