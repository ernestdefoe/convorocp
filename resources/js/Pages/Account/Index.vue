<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    enabled: Boolean, pending: Boolean, qr: String, secret: String, recoveryCodes: Array,
});

const enableForm = useForm({});
const confirmForm = useForm({ code: '' });
const disableForm = useForm({ password: '' });

const enable = () => enableForm.post('/account/2fa/enable', { preserveScroll: true });
const confirm = () => confirmForm.post('/account/2fa/confirm', { preserveScroll: true });
const disable = () => { if (window.confirm('Disable two-factor authentication?')) disableForm.delete('/account/2fa', { preserveScroll: true }); };

const field = 'box-sizing:border-box;width:100%;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
const card = 'background:var(--cp-card);border:1px solid var(--cp-ln);border-radius:13px;padding:18px 20px';
</script>

<template>
    <AppLayout active="account" title="Account" subtitle="Security">
        <div :style="card + ';max-width:560px'">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 4px">
                <i class="ti ti-shield-lock" :style="`font-size:22px;color:${enabled ? 'var(--cp-grn)' : 'var(--cp-dim)'}`" aria-hidden="true"></i>
                <div style="flex: 1">
                    <div style="font-size: 15px; font-weight: 600">Two-factor authentication</div>
                    <div style="font-size: 12px; color: var(--cp-dim)">Require a 6-digit code from an authenticator app at login.</div>
                </div>
                <span v-if="enabled" style="font-size: 11px; font-weight: 600; color: var(--cp-grn); background: rgba(52,211,153,.16); padding: 3px 10px; border-radius: 999px">ON</span>
            </div>

            <!-- recovery codes shown once after confirming -->
            <div v-if="recoveryCodes && recoveryCodes.length" style="margin-top: 16px; background: rgba(251,191,36,.08); border: 1px solid rgba(251,191,36,.3); border-radius: 11px; padding: 14px">
                <div style="font-size: 12.5px; font-weight: 600; color: var(--cp-amb); margin-bottom: 6px">Save your recovery codes</div>
                <div style="font-size: 11.5px; color: var(--cp-mut); margin-bottom: 10px">Each can be used once if you lose your device. They won't be shown again.</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-family: ui-monospace, monospace; font-size: 12.5px">
                    <span v-for="c in recoveryCodes" :key="c" style="background: var(--cp-card2); padding: 5px 9px; border-radius: 7px">{{ c }}</span>
                </div>
            </div>

            <!-- setup (pending) -->
            <div v-if="pending" style="margin-top: 18px; border-top: 1px solid var(--cp-ln); padding-top: 18px">
                <div style="font-size: 12.5px; color: var(--cp-mut); margin-bottom: 12px">Scan this with your authenticator app, then enter the code to finish.</div>
                <div style="display: flex; gap: 18px; align-items: flex-start; flex-wrap: wrap">
                    <img v-if="qr" :src="qr" alt="2FA QR code" style="width: 160px; height: 160px; background: #fff; border-radius: 10px; padding: 8px" />
                    <div style="flex: 1; min-width: 200px">
                        <div style="font-size: 11px; color: var(--cp-dim)">Or enter this key manually</div>
                        <code style="display: block; font-size: 12px; color: var(--cp-mut); word-break: break-all; margin: 4px 0 14px">{{ secret }}</code>
                        <form @submit.prevent="confirm">
                            <input v-model="confirmForm.code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" :style="field" />
                            <div v-if="confirmForm.errors.code" style="font-size: 11px; color: var(--cp-red); margin-top: 6px">{{ confirmForm.errors.code }}</div>
                            <button type="submit" :disabled="confirmForm.processing" style="margin-top: 10px; font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 18px; cursor: pointer; font-family: inherit">Confirm &amp; enable</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- enable button -->
            <div v-else-if="!enabled" style="margin-top: 16px">
                <button type="button" @click="enable" :disabled="enableForm.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 18px; cursor: pointer; font-family: inherit">Enable two-factor</button>
            </div>

            <!-- disable -->
            <form v-else @submit.prevent="disable" style="margin-top: 18px; border-top: 1px solid var(--cp-ln); padding-top: 18px">
                <div style="font-size: 12.5px; color: var(--cp-mut); margin-bottom: 10px">Enter your password to turn off two-factor.</div>
                <div style="display: flex; gap: 8px">
                    <input v-model="disableForm.password" type="password" placeholder="Current password" :style="field" />
                    <button type="submit" :disabled="disableForm.processing" style="white-space: nowrap; font-size: 13px; color: var(--cp-red); background: transparent; border: 1px solid rgba(248,113,113,.4); border-radius: 9px; padding: 9px 16px; cursor: pointer; font-family: inherit">Disable</button>
                </div>
                <div v-if="disableForm.errors.password" style="font-size: 11px; color: var(--cp-red); margin-top: 6px">{{ disableForm.errors.password }}</div>
            </form>
        </div>
    </AppLayout>
</template>
