<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ backups: Array, sites: Array, databases: Array });

const showForm = ref(false);
const form = useForm({ kind: 'site', target: '' });
const targets = computed(() => form.kind === 'site' ? props.sites : props.databases.map((d) => d.name));
function create() {
    if (!form.target) form.target = targets.value[0] ?? '';
    form.post('/backups', { onSuccess: () => { showForm.value = false; form.reset(); } });
}
const destroy = (b) => { if (confirm('Delete this backup?')) router.delete(`/backups/${b.id}`); };
const human = (n) => (n == null ? '—' : n < 1024 ? n + ' B' : n < 1048576 ? (n / 1024).toFixed(1) + ' KB' : (n / 1048576).toFixed(1) + ' MB');
const field = 'box-sizing:border-box;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="backups" title="Backups" :subtitle="`${backups.length} backup${backups.length === 1 ? '' : 's'}`">
        <template #actions>
            <button type="button" @click="showForm = !showForm"
                style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit">
                <i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New backup
            </button>
        </template>

        <form v-if="showForm" @submit.prevent="create" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: grid; grid-template-columns: 130px 1fr auto; gap: 10px; align-items: end">
            <label style="font-size: 11.5px; color: var(--cp-mut)">Type<select v-model="form.kind" @change="form.target = ''" :style="field + ';margin-top:5px;display:block;width:100%'"><option value="site">Site</option><option value="database">Database</option></select></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Target<select v-model="form.target" :style="field + ';margin-top:5px;display:block;width:100%'"><option v-for="t in targets" :key="t" :value="t">{{ t }}</option></select></label>
            <button type="submit" :disabled="form.processing" style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Back up now</button>
        </form>

        <div v-if="backups.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div v-for="(b, i) in backups" :key="b.id"
                :style="`display:flex;align-items:center;gap:12px;padding:12px 15px;font-size:13px;${i < backups.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <i class="ti" :class="b.kind === 'site' ? 'ti-world' : 'ti-database'" :style="`font-size:16px;color:${b.kind === 'site' ? 'var(--cp-vio)' : 'var(--cp-cy)'}`" aria-hidden="true"></i>
                <div style="flex: 1; min-width: 0">
                    <div style="font-weight: 500">{{ b.target }}</div>
                    <div style="font-size: 11px; color: var(--cp-dim)">{{ b.kind }}<span v-if="b.engine"> · {{ b.engine }}</span> · {{ b.created }}</div>
                </div>
                <span style="font-size: 11.5px; color: var(--cp-dim); width: 70px; text-align: right">{{ human(b.size) }}</span>
                <span v-if="b.status !== 'done'" :style="`font-size:10.5px;font-weight:500;padding:2px 9px;border-radius:999px;${b.status === 'failed' ? 'background:rgba(248,113,113,.16);color:var(--cp-red)' : 'background:rgba(251,191,36,.16);color:var(--cp-amb)'}`">{{ b.status === 'pending' ? 'running…' : b.status }}</span>
                <a v-else :href="`/backups/${b.id}/download`" aria-label="Download" style="color: var(--cp-mut); text-decoration: none"><i class="ti ti-download" style="font-size: 16px" aria-hidden="true"></i></a>
                <button type="button" @click="destroy(b)" aria-label="Delete" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-trash" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
        </div>
        <div v-else style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 40px; text-align: center; color: var(--cp-dim); font-size: 13px">
            No backups yet.
        </div>
    </AppLayout>
</template>
