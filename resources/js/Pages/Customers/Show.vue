<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ customer: Object, sites: Array, databases: Array });
</script>

<template>
    <AppLayout active="customers" :title="customer.name" subtitle="Customer">
        <Link href="/customers" style="display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; color: var(--cp-mut); text-decoration: none; margin-bottom: 14px">
            <i class="ti ti-chevron-left" style="font-size: 15px" aria-hidden="true"></i>All customers
        </Link>

        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 15px 16px; margin-bottom: 14px; display: flex; align-items: center; gap: 13px">
            <span style="width: 44px; height: 44px; border-radius: 50%; background: rgba(91,91,214,.2); color: var(--cp-vio); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600">{{ customer.initials }}</span>
            <div style="flex: 1">
                <div style="font-size: 15px; font-weight: 600">{{ customer.name }}</div>
                <div style="font-size: 12px; color: var(--cp-dim)">{{ customer.email }} · since {{ customer.since }}</div>
            </div>
            <span style="font-size: 11.5px; font-weight: 500; padding: 4px 11px; border-radius: 999px; background: rgba(91,91,214,.14); color: var(--cp-vio)">{{ customer.plan }} plan</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px">
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
                <div style="font-size: 13px; font-weight: 600; padding: 11px 14px; border-bottom: 1px solid var(--cp-ln)">Sites ({{ sites.length }})</div>
                <div v-for="(s, i) in sites" :key="s.id" :style="`display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:12.5px;${i < sites.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--cp-grn)"></span>
                    <span style="flex: 1; font-weight: 500">{{ s.domain }}</span>
                    <span style="font-size: 11px; color: var(--cp-dim)">{{ s.runtime === 'php' ? 'PHP ' + s.php_version : s.runtime }}</span>
                </div>
                <div v-if="!sites.length" style="padding: 20px; text-align: center; color: var(--cp-dim); font-size: 12px">No sites</div>
            </div>
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
                <div style="font-size: 13px; font-weight: 600; padding: 11px 14px; border-bottom: 1px solid var(--cp-ln)">Databases ({{ databases.length }})</div>
                <div v-for="(d, i) in databases" :key="d.id" :style="`display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:12.5px;${i < databases.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                    <i class="ti ti-database" style="font-size: 15px; color: var(--cp-cy)" aria-hidden="true"></i>
                    <span style="flex: 1; font-weight: 500; font-family: ui-monospace, monospace">{{ d.name }}</span>
                    <span style="font-size: 11px; color: var(--cp-dim)">{{ d.engine }}</span>
                </div>
                <div v-if="!databases.length" style="padding: 20px; text-align: center; color: var(--cp-dim); font-size: 12px">No databases</div>
            </div>
        </div>
    </AppLayout>
</template>
