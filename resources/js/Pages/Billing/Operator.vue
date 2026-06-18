<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    configured: Boolean, publishable: String, hasSecret: Boolean, hasWebhook: Boolean,
    connection: Object, webhookUrl: String, plans: Array,
});

const keys = useForm({ publishable: props.publishable || '', secret: '', webhook_secret: '' });
const saveKeys = () => keys.post('/billing/keys', { preserveScroll: true, onSuccess: () => { keys.secret = ''; keys.webhook_secret = ''; } });
const test = () => router.post('/billing/test', {}, { preserveScroll: true });
const savePrice = (p) => router.patch(`/billing/plans/${p.id}`, { stripe_price_id: p.stripe_price_id }, { preserveScroll: true });
const copied = ref(false);
const copy = () => { navigator.clipboard?.writeText(props.webhookUrl); copied.value = true; setTimeout(() => copied.value = false, 1500); };

const field = 'box-sizing:border-box;width:100%;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
const card = 'background:var(--cp-card);border:1px solid var(--cp-ln);border-radius:13px;padding:18px 20px;margin-bottom:14px';
const label = 'display:block;font-size:11.5px;color:var(--cp-mut);margin-bottom:10px';
</script>

<template>
    <AppLayout active="billing" title="Billing" subtitle="Connect Stripe to sell hosting">
        <div style="max-width: 620px">
            <!-- connection status -->
            <div :style="card + ';display:flex;align-items:center;gap:12px'">
                <i class="ti ti-brand-stripe" :style="`font-size:24px;color:${configured ? 'var(--cp-vio)' : 'var(--cp-dim)'}`" aria-hidden="true"></i>
                <div style="flex: 1">
                    <div style="font-size: 14px; font-weight: 600">{{ configured ? 'Stripe connected' : 'Stripe not configured' }}</div>
                    <div v-if="connection" :style="`font-size:11.5px;color:${connection.ok ? 'var(--cp-grn)' : 'var(--cp-red)'}`">{{ connection.message }}</div>
                    <div v-else style="font-size: 11.5px; color: var(--cp-dim)">Add your API keys below, then test the connection.</div>
                </div>
                <button v-if="hasSecret" type="button" @click="test" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; cursor: pointer; font-family: inherit">Test connection</button>
            </div>

            <!-- API keys -->
            <form @submit.prevent="saveKeys" :style="card">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 14px">API keys</div>
                <label :style="label">Publishable key<input v-model="keys.publishable" :style="field + ';margin-top:5px'" placeholder="pk_live_…" /></label>
                <label :style="label">Secret key <span v-if="hasSecret" style="color: var(--cp-grn)">· set</span><input v-model="keys.secret" type="password" :style="field + ';margin-top:5px'" :placeholder="hasSecret ? '•••••••• (leave blank to keep)' : 'sk_live_…'" /></label>
                <label :style="label + ';margin-bottom:0'">Webhook signing secret <span v-if="hasWebhook" style="color: var(--cp-grn)">· set</span><input v-model="keys.webhook_secret" type="password" :style="field + ';margin-top:5px'" :placeholder="hasWebhook ? '•••••••• (leave blank to keep)' : 'whsec_…'" /></label>
                <div style="display: flex; justify-content: flex-end; margin-top: 14px">
                    <button type="submit" :disabled="keys.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 18px; cursor: pointer; font-family: inherit">Save keys</button>
                </div>
            </form>

            <!-- webhook url -->
            <div :style="card">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 6px">Webhook endpoint</div>
                <div style="font-size: 11.5px; color: var(--cp-dim); margin-bottom: 10px">In Stripe → Developers → Webhooks, add this URL and subscribe to <code>checkout.session.completed</code> + <code>customer.subscription.*</code>. Paste the signing secret above.</div>
                <div style="display: flex; gap: 8px">
                    <input :value="webhookUrl" readonly :style="field + ';font-family:ui-monospace,monospace;font-size:12px'" />
                    <button type="button" @click="copy" style="white-space: nowrap; font-size: 12px; color: var(--cp-mut); background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 0 14px; cursor: pointer; font-family: inherit">{{ copied ? 'Copied' : 'Copy' }}</button>
                </div>
            </div>

            <!-- plan price mapping -->
            <div :style="card + ';margin-bottom:0'">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 4px">Plan pricing</div>
                <div style="font-size: 11.5px; color: var(--cp-dim); margin-bottom: 14px">Map each plan to a Stripe recurring Price ID. Plans without one can't be purchased.</div>
                <div v-for="p in plans" :key="p.id" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px">
                    <div style="width: 130px; flex-shrink: 0">
                        <div style="font-size: 13px; font-weight: 500">{{ p.name }}</div>
                        <div style="font-size: 11px; color: var(--cp-dim)">${{ (p.price_cents / 100).toFixed(0) }}/mo</div>
                    </div>
                    <input v-model="p.stripe_price_id" :style="field + ';font-family:ui-monospace,monospace;font-size:12px'" placeholder="price_…" @keyup.enter="savePrice(p)" />
                    <button type="button" @click="savePrice(p)" style="white-space: nowrap; font-size: 12px; color: var(--cp-mut); background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 8px 12px; cursor: pointer; font-family: inherit">Save</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
