<script setup>
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    enabled: { type: Boolean, default: false },
    url: { type: String, default: '' },
});

const frame = ref(null);
const reload = () => { if (frame.value) frame.value.src = frame.value.src; };
</script>

<template>
    <AppLayout active="monitoring" title="Monitoring" subtitle="Server metrics, history & alerts">
        <template #actions>
            <a v-if="enabled" :href="url" target="_blank" rel="noopener" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; text-decoration: none">
                <i class="ti ti-external-link" style="font-size: 14px" aria-hidden="true"></i>Open in new tab
            </a>
            <button v-if="enabled" type="button" @click="reload" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-family: inherit">
                <i class="ti ti-refresh" style="font-size: 14px" aria-hidden="true"></i>Refresh
            </button>
        </template>

        <template v-if="enabled">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 11.5px; color: var(--cp-dim)">
                <i class="ti ti-info-circle" style="font-size: 14px" aria-hidden="true"></i>
                First visit? Sign in once with your monitoring account, then it stays signed in.
            </div>

            <div style="background: #0a0a0f; border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden; height: calc(100vh - 170px); min-height: 380px">
                <iframe ref="frame" :src="url" title="Server monitoring" style="width: 100%; height: 100%; border: 0; display: block"></iframe>
            </div>
        </template>

        <template v-else>
            <div style="max-width: 640px; background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 26px">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px">
                    <i class="ti ti-activity-heartbeat" style="font-size: 22px; color: var(--cp-ind)" aria-hidden="true"></i>
                    <h2 style="margin: 0; font-size: 16px; color: var(--cp-fg)">Monitoring isn&rsquo;t set up yet</h2>
                </div>
                <p style="margin: 0 0 14px; font-size: 13px; color: var(--cp-mut); line-height: 1.6">
                    ConvoroCP embeds a self-hosted <strong style="color: var(--cp-fg)">Beszel</strong> dashboard for historical
                    CPU, memory, disk and network graphs, per-container metrics, and alerts.
                    Run the Beszel hub on this node and expose it on an operator-gated TLS vhost, then enable it.
                </p>
                <ol style="margin: 0 0 4px; padding-left: 20px; font-size: 12.5px; color: var(--cp-dim); line-height: 1.9">
                    <li>Follow <code style="color: var(--cp-mut)">deploy/beszel-setup.md</code> to run the hub + agent and add the gated vhost.</li>
                    <li>Set <code style="color: var(--cp-mut)">CONVOROCP_MONITORING_ENABLED=true</code> (and the port/URL) in <code style="color: var(--cp-mut)">.env</code>.</li>
                    <li>Reload the panel &mdash; this screen becomes the live dashboard.</li>
                </ol>
            </div>
        </template>
    </AppLayout>
</template>
