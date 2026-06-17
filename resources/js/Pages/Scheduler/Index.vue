<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ tasks: Array });

const showForm = ref(false);
const form = useForm({ name: '', command: '', cron: '0 3 * * *' });
function create() {
    form.post('/scheduler', { onSuccess: () => { showForm.value = false; form.reset(); } });
}
const toggle = (t) => router.patch(`/scheduler/${t.id}/toggle`, {}, { preserveScroll: true });
const run = (t) => router.post(`/scheduler/${t.id}/run`, {}, { preserveScroll: true });
const destroy = (t) => { if (confirm(`Delete task "${t.name}"?`)) router.delete(`/scheduler/${t.id}`); };
const field = 'box-sizing:border-box;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="scheduler" title="Scheduler" :subtitle="`${tasks.length} task${tasks.length === 1 ? '' : 's'} · UTC`">
        <template #actions>
            <button type="button" @click="showForm = !showForm"
                style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit">
                <i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New task
            </button>
        </template>

        <form v-if="showForm" @submit.prevent="create" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: grid; grid-template-columns: 1fr 1.6fr 140px auto; gap: 10px; align-items: end">
            <label style="font-size: 11.5px; color: var(--cp-mut)">Name<input v-model="form.name" :style="field + ';width:100%;margin-top:5px'" placeholder="Nightly backup" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Command<input v-model="form.command" :style="field + ';width:100%;margin-top:5px'" placeholder="php artisan backup:run" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Cron<input v-model="form.cron" :style="field + ';width:100%;margin-top:5px;font-family:ui-monospace,monospace'" /></label>
            <button type="submit" :disabled="form.processing" style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Create</button>
        </form>
        <p v-if="form.errors.cron" style="color: var(--cp-red); font-size: 12px; margin: -8px 0 12px">{{ form.errors.cron }}</p>

        <div v-if="tasks.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div v-for="(t, i) in tasks" :key="t.id"
                :style="`display:flex;align-items:center;gap:12px;padding:12px 15px;font-size:13px;${i < tasks.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <span :style="`width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${t.last_status === 'failed' ? 'var(--cp-red)' : t.enabled ? 'var(--cp-grn)' : 'var(--cp-dim)'}`"></span>
                <div style="flex: 1; min-width: 0">
                    <div style="font-weight: 500">{{ t.name }}</div>
                    <div style="font-size: 11px; color: var(--cp-dim); font-family: ui-monospace, monospace">{{ t.cron }} · {{ t.command }}</div>
                </div>
                <button type="button" @click="run(t)" aria-label="Run now" style="border: 0; background: transparent; color: var(--cp-mut); cursor: pointer; padding: 0"><i class="ti ti-player-play" style="font-size: 16px" aria-hidden="true"></i></button>
                <button type="button" @click="toggle(t)" :aria-label="t.enabled ? 'Disable' : 'Enable'"
                    :style="`width:34px;height:19px;border:0;border-radius:999px;position:relative;cursor:pointer;flex-shrink:0;background:${t.enabled ? 'var(--cp-ind)' : 'var(--cp-ln2)'}`">
                    <span :style="`position:absolute;top:2px;width:15px;height:15px;border-radius:50%;background:#fff;${t.enabled ? 'right:2px' : 'left:2px'}`"></span>
                </button>
                <button type="button" @click="destroy(t)" aria-label="Delete task" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-trash" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
        </div>
        <div v-else style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 40px; text-align: center; color: var(--cp-dim); font-size: 13px">
            No scheduled tasks yet.
        </div>
    </AppLayout>
</template>
