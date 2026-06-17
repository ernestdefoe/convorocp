<script setup>
import { ref } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ containers: Array });
const isOperator = usePage().props.auth?.user?.role === 'operator';

const showForm = ref(false);
const form = useForm({ name: '', image: '', container_port: 80, domain: '', restart_policy: 'unless-stopped' });
function create() {
    form.post('/containers', { onSuccess: () => { showForm.value = false; form.reset(); } });
}
const act = (c, a) => router.post(`/containers/${c.id}/${a}`, {}, { preserveScroll: true });
const destroy = (c) => { if (confirm(`Remove container "${c.name}"?`)) router.delete(`/containers/${c.id}`); };
const field = 'box-sizing:border-box;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="containers" title="Docker" :subtitle="`${containers.length} container${containers.length === 1 ? '' : 's'} · Docker Hub`">
        <template #actions>
            <button type="button" @click="showForm = !showForm"
                style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit">
                <i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>Deploy container
            </button>
        </template>

        <form v-if="showForm" @submit.prevent="create" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px">
            <div style="display: grid; grid-template-columns: 1fr 1.5fr 90px; gap: 10px">
                <label style="font-size: 11.5px; color: var(--cp-mut)">Name<input v-model="form.name" :style="field + ';width:100%;margin-top:5px'" placeholder="my-app" /></label>
                <label style="font-size: 11.5px; color: var(--cp-mut)">Docker Hub image<input v-model="form.image" :style="field + ';width:100%;margin-top:5px'" placeholder="nginxdemos/hello:latest" /></label>
                <label style="font-size: 11.5px; color: var(--cp-mut)">Port<input v-model.number="form.container_port" type="number" :style="field + ';width:100%;margin-top:5px'" /></label>
            </div>
            <div style="display: grid; grid-template-columns: 1.5fr 1fr auto; gap: 10px; align-items: end; margin-top: 10px">
                <label style="font-size: 11.5px; color: var(--cp-mut)">Domain <span style="color: var(--cp-dim)">(optional — reverse proxy + SSL)</span><input v-model="form.domain" :style="field + ';width:100%;margin-top:5px'" placeholder="app.example.com" /></label>
                <label style="font-size: 11.5px; color: var(--cp-mut)">Restart<select v-model="form.restart_policy" :style="field + ';margin-top:5px;display:block;width:100%'"><option value="unless-stopped">unless-stopped</option><option value="always">always</option><option value="on-failure">on-failure</option><option value="no">no</option></select></label>
                <button type="submit" :disabled="form.processing" style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Deploy</button>
            </div>
            <p v-if="form.errors.image" style="color: var(--cp-red); font-size: 12px; margin: 8px 0 0">{{ form.errors.image }}</p>
        </form>

        <div v-if="containers.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div v-for="(c, i) in containers" :key="c.id"
                :style="`display:flex;align-items:center;gap:12px;padding:12px 15px;font-size:13px;${i < containers.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <i class="ti ti-brand-docker" :style="`font-size:19px;color:${c.status === 'running' ? 'var(--cp-cy)' : 'var(--cp-dim)'}`" aria-hidden="true"></i>
                <div style="flex: 1; min-width: 0">
                    <div style="font-weight: 500">{{ c.name }} <span style="font-size: 11px; color: var(--cp-dim); font-weight: 400; font-family: ui-monospace, monospace">{{ c.image }}</span></div>
                    <div style="font-size: 11px; color: var(--cp-dim)">{{ c.domain || ('127.0.0.1:' + c.host_port) }} → :{{ c.container_port }}</div>
                </div>
                <span v-if="isOperator && c.owner" style="font-size: 11.5px; color: var(--cp-dim)">{{ c.owner }}</span>
                <span :style="`font-size:10.5px;font-weight:500;padding:2px 9px;border-radius:999px;${c.status === 'running' ? 'background:rgba(52,211,153,.16);color:var(--cp-grn)' : 'background:var(--cp-soft);color:var(--cp-mut)'}`">{{ c.status }}</span>
                <button type="button" @click="act(c, c.status === 'running' ? 'stop' : 'start')" :aria-label="c.status === 'running' ? 'Stop' : 'Start'" style="border: 0; background: transparent; cursor: pointer; padding: 0" :style="`color:${c.status === 'running' ? 'var(--cp-mut)' : 'var(--cp-grn)'}`"><i class="ti" :class="c.status === 'running' ? 'ti-player-pause' : 'ti-player-play'" style="font-size: 16px" aria-hidden="true"></i></button>
                <button type="button" @click="destroy(c)" aria-label="Remove" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-trash" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
        </div>
        <div v-else style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 40px; text-align: center; color: var(--cp-dim); font-size: 13px">
            No containers yet. Deploy any image from Docker Hub.
        </div>
    </AppLayout>
</template>
