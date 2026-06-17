<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ customers: Array });
</script>

<template>
    <AppLayout active="customers" title="Customers" :subtitle="`${customers.length} customer${customers.length === 1 ? '' : 's'}`">
        <div v-if="customers.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div style="display: grid; grid-template-columns: 1.6fr 90px 60px 70px 70px 28px; gap: 10px; padding: 9px 15px; border-bottom: 1px solid var(--cp-ln); font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: var(--cp-dim)">
                <span>Customer</span><span>Plan</span><span>Sites</span><span>DBs</span><span style="text-align: right">MRR</span><span></span>
            </div>
            <Link v-for="(c, i) in customers" :key="c.id" :href="`/customers/${c.id}`"
                :style="`display:grid;grid-template-columns:1.6fr 90px 60px 70px 70px 28px;gap:10px;align-items:center;padding:11px 15px;font-size:12.5px;text-decoration:none;color:var(--cp-ink);${i < customers.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <div style="display: flex; align-items: center; gap: 10px; min-width: 0">
                    <span style="width: 28px; height: 28px; border-radius: 50%; background: rgba(91,91,214,.2); color: var(--cp-vio); display: flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 600; flex-shrink: 0">{{ c.initials }}</span>
                    <div style="min-width: 0">
                        <div style="font-weight: 500">{{ c.name }}</div>
                        <div style="font-size: 11px; color: var(--cp-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ c.email }}</div>
                    </div>
                </div>
                <span style="font-size: 11px; font-weight: 500; padding: 2px 8px; border-radius: 999px; background: var(--cp-soft); color: var(--cp-mut); justify-self: start">{{ c.plan }}</span>
                <span style="color: var(--cp-mut)">{{ c.sites }}</span>
                <span style="color: var(--cp-mut)">{{ c.databases }}</span>
                <span style="text-align: right">{{ c.mrr }}</span>
                <i class="ti ti-chevron-right" style="color: var(--cp-dim)" aria-hidden="true"></i>
            </Link>
        </div>
        <div v-else style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 40px; text-align: center; color: var(--cp-dim); font-size: 13px">
            No customers yet. They'll appear here after signing up.
        </div>
    </AppLayout>
</template>
