<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ configured: Boolean, subscription: Object, plans: Array, flash: Object });

const subscribe = (plan) => router.post(`/billing/checkout/${plan.id}`);
const portal = () => router.post('/billing/portal');

const money = (c) => '$' + (c / 100).toFixed(0);
const card = 'background:var(--cp-card);border:1px solid var(--cp-ln);border-radius:13px;padding:18px 20px';
const statusTone = { active: 'var(--cp-grn)', trialing: 'var(--cp-grn)', past_due: 'var(--cp-amb)', canceled: 'var(--cp-red)' };
</script>

<template>
    <AppLayout active="billing" title="Billing" subtitle="Your subscription">
        <div style="max-width: 720px">
            <div v-if="flash && flash.success" style="background: rgba(52,211,153,.1); border: 1px solid rgba(52,211,153,.3); border-radius: 11px; padding: 12px 15px; margin-bottom: 14px; font-size: 12.5px; color: var(--cp-grn)">Payment received — your subscription is being activated.</div>
            <div v-if="flash && flash.canceled" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 11px; padding: 12px 15px; margin-bottom: 14px; font-size: 12.5px; color: var(--cp-mut)">Checkout canceled — no charge was made.</div>

            <div v-if="!configured" :style="card + ';text-align:center;color:var(--cp-dim);font-size:13px'">
                <i class="ti ti-credit-card-off" style="font-size: 26px" aria-hidden="true"></i>
                <div style="margin-top: 10px">Billing isn't available yet. Please check back soon.</div>
            </div>

            <template v-else>
                <!-- current subscription -->
                <div :style="card + ';margin-bottom:18px;display:flex;align-items:center;gap:14px'">
                    <div style="flex: 1">
                        <div style="font-size: 12px; color: var(--cp-dim)">Current plan</div>
                        <div style="font-size: 18px; font-weight: 600">{{ subscription.plan || 'No active plan' }}</div>
                        <div v-if="subscription.renews" style="font-size: 11.5px; color: var(--cp-dim)">Renews {{ subscription.renews }}</div>
                    </div>
                    <span v-if="subscription.status" :style="`font-size:11px;font-weight:600;padding:3px 11px;border-radius:999px;text-transform:capitalize;background:rgba(91,91,214,.14);color:${statusTone[subscription.status] || 'var(--cp-mut)'}`">{{ subscription.status.replace('_', ' ') }}</span>
                    <button v-if="subscription.active" type="button" @click="portal" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 8px 14px; cursor: pointer; font-family: inherit">Manage</button>
                </div>

                <!-- plans -->
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 12px">{{ subscription.active ? 'Change plan' : 'Choose a plan' }}</div>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px">
                    <div v-for="p in plans" :key="p.id" :style="card">
                        <div style="font-size: 14px; font-weight: 600">{{ p.name }}</div>
                        <div style="font-size: 22px; font-weight: 700; letter-spacing: -0.02em; margin: 6px 0 12px">{{ money(p.price_cents) }}<span style="font-size: 12px; font-weight: 400; color: var(--cp-dim)">/mo</span></div>
                        <ul style="list-style: none; padding: 0; margin: 0 0 14px; font-size: 12px; color: var(--cp-mut); display: flex; flex-direction: column; gap: 5px">
                            <li>{{ p.sites_limit }} site{{ p.sites_limit === 1 ? '' : 's' }}</li>
                            <li>{{ p.db_limit }} database{{ p.db_limit === 1 ? '' : 's' }}</li>
                            <li>{{ p.email_limit }} mailboxes</li>
                            <li>{{ (p.disk_mb / 1024).toFixed(0) }} GB disk</li>
                        </ul>
                        <button type="button" @click="subscribe(p)" style="width: 100%; font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px; cursor: pointer; font-family: inherit; font-weight: 500">Subscribe</button>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
