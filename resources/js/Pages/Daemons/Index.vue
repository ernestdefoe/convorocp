<script setup>
import { ref } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ daemons: Array });
const isOperator = usePage().props.auth?.user?.role === 'operator';

const showForm = ref(false);
const form = useForm({ name: '', command: '', restart_policy: 'always' });
function create() {
    form.post('/daemons', { onSuccess: () => { showForm.value = false; form.reset(); } });
}

const showAdopt = ref(false);
const adoptForm = useForm({ name: '', unit: '', command: '' });
function adopt() {
    adoptForm.post('/daemons/adopt', { onSuccess: () => { showAdopt.value = false; adoptForm.reset(); } });
}

const act = (d, action) => router.post(`/daemons/${d.id}/${action}`, {}, { preserveScroll: true });
const destroy = (d) => { if (confirm(d.adopted ? `Detach "${d.name}" from the panel? The real unit keeps running.` : `Delete daemon "${d.name}"?`)) router.delete(`/daemons/${d.id}`); };
const field = 'box-sizing:border-box;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="daemons" title="Daemons" :subtitle="`${daemons.filter(d => d.status === 'running').length} running · ${daemons.length} total`">
        <template #actions>
            <button v-if="isOperator" type="button" @click="showAdopt = !showAdopt"
                style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-family: inherit; margin-right: 8px">
                <i class="ti ti-link" style="font-size: 14px" aria-hidden="true"></i>Adopt existing
            </button>
            <button type="button" @click="showForm = !showForm"
                style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit">
                <i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New daemon
            </button>
        </template>

        <form v-if="showAdopt" @submit.prevent="adopt" style="background: var(--cp-card); border: 1px solid var(--cp-ind); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: grid; grid-template-columns: 1fr 1.4fr 1.6fr auto; gap: 10px; align-items: end">
            <div style="grid-column: 1 / -1; font-size: 11.5px; color: var(--cp-dim)">Register an existing systemd unit — the panel controls/monitors it without creating a duplicate.</div>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Name<input v-model="adoptForm.name" :style="field + ';width:100%;margin-top:5px'" placeholder="Convoro Horizon" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">systemd unit<input v-model="adoptForm.unit" :style="field + ';width:100%;margin-top:5px;font-family:ui-monospace,monospace'" placeholder="convoro-horizon.service" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Command <span style="color: var(--cp-dim)">(label, optional)</span><input v-model="adoptForm.command" :style="field + ';width:100%;margin-top:5px'" placeholder="php artisan horizon" /></label>
            <button type="submit" :disabled="adoptForm.processing" style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Adopt</button>
        </form>

        <form v-if="showForm" @submit.prevent="create" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: grid; grid-template-columns: 1fr 1.6fr 140px auto; gap: 10px; align-items: end">
            <label style="font-size: 11.5px; color: var(--cp-mut)">Name<input v-model="form.name" :style="field + ';width:100%;margin-top:5px'" placeholder="queue-worker" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Command<input v-model="form.command" :style="field + ';width:100%;margin-top:5px'" placeholder="php artisan queue:work" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Restart<select v-model="form.restart_policy" :style="field + ';margin-top:5px;display:block;width:100%'"><option value="always">always</option><option value="on-failure">on-failure</option><option value="never">never</option></select></label>
            <button type="submit" :disabled="form.processing" style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Create</button>
        </form>

        <div v-if="daemons.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div v-for="(d, i) in daemons" :key="d.id"
                :style="`display:flex;align-items:center;gap:12px;padding:12px 15px;font-size:13px;${i < daemons.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <span :style="`width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${d.status === 'running' ? 'var(--cp-grn)' : 'var(--cp-dim)'}`"></span>
                <div style="flex: 1; min-width: 0">
                    <div style="font-weight: 500">{{ d.name }} <span style="font-size: 11px; color: var(--cp-dim); font-weight: 400; font-family: ui-monospace, monospace">{{ d.command }}</span>
                        <span v-if="d.adopted" style="font-size: 10px; font-weight: 600; letter-spacing: .03em; color: var(--cp-vio); background: rgba(91,91,214,.16); padding: 1px 7px; border-radius: 999px; margin-left: 4px">ADOPTED</span>
                    </div>
                    <div v-if="d.adopted" style="font-size: 11px; color: var(--cp-dim); font-family: ui-monospace, monospace">{{ d.unit }}</div>
                </div>
                <span style="font-size: 11px; padding: 2px 8px; border-radius: 999px; background: var(--cp-soft); color: var(--cp-mut)">{{ d.restart_policy }}</span>
                <button v-if="d.status === 'running'" type="button" @click="act(d, 'restart')" aria-label="Restart" style="border: 0; background: transparent; color: var(--cp-mut); cursor: pointer; padding: 0"><i class="ti ti-refresh" style="font-size: 16px" aria-hidden="true"></i></button>
                <button type="button" @click="act(d, d.status === 'running' ? 'stop' : 'start')" :aria-label="d.status === 'running' ? 'Stop' : 'Start'" style="border: 0; background: transparent; cursor: pointer; padding: 0" :style="`color:${d.status === 'running' ? 'var(--cp-mut)' : 'var(--cp-grn)'}`"><i class="ti" :class="d.status === 'running' ? 'ti-player-pause' : 'ti-player-play'" style="font-size: 16px" aria-hidden="true"></i></button>
                <button type="button" @click="destroy(d)" aria-label="Delete daemon" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-trash" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
        </div>
        <div v-else style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 40px; text-align: center; color: var(--cp-dim); font-size: 13px">
            No daemons yet.
        </div>
    </AppLayout>
</template>
