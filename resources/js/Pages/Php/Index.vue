<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ runtimes: Array });

const install = (r) => router.post(`/php/${r.id}/install`, {}, { preserveScroll: true });
const uninstall = (r) => { if (confirm(`Remove PHP ${r.version}? Sites using it must be moved first.`)) router.post(`/php/${r.id}/uninstall`, {}, { preserveScroll: true }); };

const badge = {
    installed: 'background:rgba(52,211,153,.16);color:var(--cp-grn)',
    available: 'background:var(--cp-soft);color:var(--cp-mut)',
    installing: 'background:rgba(251,191,36,.16);color:var(--cp-amb)',
    removing: 'background:rgba(248,113,113,.16);color:var(--cp-red)',
};
</script>

<template>
    <AppLayout active="php" title="PHP versions" subtitle="Install or remove PHP runtimes offered on this node">
        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div v-for="(r, i) in runtimes" :key="r.id"
                :style="`display:flex;align-items:center;gap:14px;padding:14px 16px;${i < runtimes.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <i class="ti ti-brand-php" style="font-size: 22px; color: var(--cp-vio)" aria-hidden="true"></i>
                <div style="flex: 1">
                    <div style="font-size: 14px; font-weight: 600">PHP {{ r.version }}</div>
                    <div style="font-size: 11.5px; color: var(--cp-dim)">php{{ r.version }}-fpm</div>
                </div>
                <span :style="`font-size:11px;font-weight:500;padding:3px 10px;border-radius:999px;text-transform:capitalize;${badge[r.status]}`">
                    {{ r.status === 'installing' ? 'Installing…' : r.status === 'removing' ? 'Removing…' : r.status }}
                </span>
                <button v-if="r.status === 'available'" type="button" @click="install(r)"
                    style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 14px; font-weight: 500; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px">
                    <i class="ti ti-download" style="font-size: 14px" aria-hidden="true"></i>Install
                </button>
                <button v-else-if="r.status === 'installed'" type="button" @click="uninstall(r)"
                    style="font-size: 12px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 14px; cursor: pointer; font-family: inherit">
                    Remove
                </button>
                <span v-else style="font-size: 12px; color: var(--cp-dim); width: 78px; text-align: center">working…</span>
            </div>
        </div>
        <p style="font-size: 12px; color: var(--cp-dim); margin-top: 14px; display: flex; align-items: center; gap: 7px">
            <i class="ti ti-info-circle" style="font-size: 15px" aria-hidden="true"></i>
            Installs run on the node in the background (apt) — refresh in a minute to see the status update.
        </p>
    </AppLayout>
</template>
