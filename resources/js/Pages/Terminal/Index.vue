<script setup>
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const frame = ref(null);
const reload = () => { if (frame.value) frame.value.src = frame.value.src; };
</script>

<template>
    <AppLayout active="terminal" title="Terminal" subtitle="Root shell on this node">
        <template #actions>
            <button type="button" @click="reload" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-family: inherit"><i class="ti ti-refresh" style="font-size: 14px" aria-hidden="true"></i>New session</button>
        </template>

        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 11.5px; color: var(--cp-dim)">
            <i class="ti ti-alert-triangle" style="font-size: 14px; color: var(--cp-amb)" aria-hidden="true"></i>
            This is a live <strong style="color: var(--cp-mut)">root</strong> shell. Commands run immediately on the server.
        </div>

        <div style="background: #0a0a0f; border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden; height: calc(100vh - 170px); min-height: 360px">
            <iframe ref="frame" src="/tty/" title="Web terminal" style="width: 100%; height: 100%; border: 0; display: block" allow="clipboard-read; clipboard-write"></iframe>
        </div>
    </AppLayout>
</template>
