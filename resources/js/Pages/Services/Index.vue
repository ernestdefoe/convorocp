<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ services: Array });

const control = (svc, action) => router.post('/services/control', { service: svc.name, action }, { preserveScroll: true });
const install = (svc) => router.post('/services/install', { service: svc.name }, { preserveScroll: true });

const dot = (s) => (s === 'active' ? 'var(--cp-grn)' : s === 'failed' ? 'var(--cp-red)' : 'var(--cp-dim)');
</script>

<template>
    <AppLayout active="services" title="Services" subtitle="System services on this node">
        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div v-for="(s, i) in services" :key="s.name"
                :style="`display:flex;align-items:center;gap:12px;padding:13px 16px;font-size:13px;${i < services.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <span :style="`width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${dot(s.status)}`"></span>
                <div style="flex: 1; min-width: 0">
                    <div style="font-weight: 500">{{ s.label }}</div>
                    <div style="font-size: 11px; color: var(--cp-dim); font-family: ui-monospace, monospace">{{ s.name }} · {{ s.installed ? s.status : 'not installed' }}</div>
                </div>
                <template v-if="s.installed">
                    <button type="button" @click="control(s, 'restart')" :disabled="s.status !== 'active'"
                        style="font-size: 12px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 8px; padding: 6px 12px; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px"
                        :style="s.status !== 'active' ? 'opacity:.4' : ''">
                        <i class="ti ti-refresh" style="font-size: 14px" aria-hidden="true"></i>Restart
                    </button>
                    <button v-if="s.status === 'active'" type="button" @click="control(s, 'stop')"
                        style="font-size: 12px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 8px; padding: 6px 12px; cursor: pointer; font-family: inherit">Stop</button>
                    <button v-else type="button" @click="control(s, 'start')"
                        style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 6px 12px; font-weight: 500; cursor: pointer; font-family: inherit">Start</button>
                </template>
                <button v-else-if="s.installable" type="button" @click="install(s)"
                    style="font-size: 12px; color: #fff; background: var(--cp-grn); border: 0; border-radius: 8px; padding: 6px 12px; font-weight: 500; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px">
                    <i class="ti ti-download" style="font-size: 14px" aria-hidden="true"></i>Install
                </button>
                <span v-else style="font-size: 11.5px; color: var(--cp-dim)">Not installed</span>
            </div>
        </div>
        <p style="font-size: 12px; color: var(--cp-dim); margin-top: 14px; display: flex; align-items: center; gap: 7px">
            <i class="ti ti-info-circle" style="font-size: 15px" aria-hidden="true"></i>
            Actions run on the node via the agent. Status refreshes on page load.
        </p>
    </AppLayout>
</template>
