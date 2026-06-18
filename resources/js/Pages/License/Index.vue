<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ license: Object, isOperator: Boolean, subscribeUrl: String });

const page = usePage();
const status = computed(() => page.props.flash?.status);
const error = computed(() => page.props.flash?.error);

const form = useForm({ key: '' });
function save() {
    form.post('/license', { preserveScroll: true, onSuccess: () => form.reset() });
}
function recheck() {
    router.post('/license/recheck', {}, { preserveScroll: true });
}

const banner = computed(() => {
    const l = props.license;
    if (l.licensed) return { tone: 'grn', icon: 'ti-rosette-discount-check', text: 'Licensed — thanks for supporting ConvoroCP.' };
    if (l.in_trial) return { tone: 'amb', icon: 'ti-clock', text: `Free trial — ${l.trial_days_left} day${l.trial_days_left === 1 ? '' : 's'} left (ends ${l.trial_ends_at}).` };
    return { tone: 'red', icon: 'ti-lock', text: 'Your trial has ended. Enter a license key to unlock the panel.' };
});
const toneColor = (t) => ({ grn: 'var(--cp-grn)', amb: 'var(--cp-amb)', red: 'var(--cp-red)' }[t]);

const card = 'background:var(--cp-card);border:1px solid var(--cp-ln);border-radius:13px;padding:16px 18px;margin-bottom:14px';
const field = 'box-sizing:border-box;width:100%;margin-top:6px;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:10px 12px;font-size:14px;font-family:ui-monospace,monospace;letter-spacing:.04em';
const btn = 'font-size:12.5px;color:#fff;background:var(--cp-ind);border:0;border-radius:9px;padding:10px 18px;font-weight:500;cursor:pointer;font-family:inherit';
</script>

<template>
    <AppLayout active="license" title="License" subtitle="ConvoroCP subscription">
        <!-- status banner -->
        <div :style="`${card};display:flex;align-items:center;gap:12px;border-color:${toneColor(banner.tone)}`">
            <i class="ti" :class="banner.icon" :style="`font-size:22px;color:${toneColor(banner.tone)}`" aria-hidden="true"></i>
            <div style="flex: 1; font-size: 13.5px; font-weight: 500">{{ banner.text }}</div>
            <a v-if="!license.licensed" :href="subscribeUrl" target="_blank" rel="noopener" :style="btn + ';text-decoration:none;display:inline-block'">Subscribe — $10/mo</a>
        </div>

        <div v-if="status" style="background: rgba(52,211,153,.14); border: 1px solid var(--cp-grn); color: var(--cp-grn); border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px">{{ status }}</div>
        <div v-if="error" style="background: rgba(248,113,113,.14); border: 1px solid var(--cp-red); color: var(--cp-red); border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px">{{ error }}</div>

        <!-- key entry -->
        <div :style="card">
            <div style="font-size: 14px; font-weight: 600; margin-bottom: 4px">License key</div>
            <div style="font-size: 12px; color: var(--cp-dim); margin-bottom: 12px">Paste the <code style="font-family: ui-monospace, monospace">CONV-…</code> key from your subscription receipt. The panel verifies it against the store and re-checks daily.</div>

            <div v-if="license.has_key" style="font-size: 12.5px; color: var(--cp-mut); margin-bottom: 12px">
                On file: <code style="font-family: ui-monospace, monospace; color: var(--cp-ink)">{{ license.key_masked }}</code>
                <span v-if="license.last_check" style="color: var(--cp-dim)"> · last checked {{ license.last_check }}</span>
            </div>

            <template v-if="isOperator">
                <label style="font-size: 11.5px; color: var(--cp-mut)">Enter / replace key
                    <input v-model="form.key" :style="field" placeholder="CONV-XXXX-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" />
                </label>
                <p v-if="form.errors.key" style="color: var(--cp-red); font-size: 12px; margin: 6px 0 0">{{ form.errors.key }}</p>
                <div style="margin-top: 14px; display: flex; gap: 10px">
                    <button type="button" @click="save" :disabled="form.processing || !form.key" :style="btn">{{ form.processing ? 'Verifying…' : 'Save & verify' }}</button>
                    <button v-if="license.has_key" type="button" @click="recheck"
                        style="font-size: 12.5px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 9px; padding: 10px 16px; cursor: pointer; font-family: inherit">Re-check now</button>
                </div>
            </template>
            <div v-else style="font-size: 12.5px; color: var(--cp-dim)">Only the operator can manage the license.</div>
        </div>

        <p style="font-size: 11.5px; color: var(--cp-dim)">
            No subscription yet? <a :href="subscribeUrl" target="_blank" rel="noopener" style="color: var(--cp-vio); text-decoration: none">Start a plan at convoro.co</a> — every ConvoroCP install gets a 30-day free trial.
        </p>
    </AppLayout>
</template>
