<script setup>
import { ref } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ databases: Array, engines: Object });
const isOperator = usePage().props.auth?.user?.role === 'operator';

const engineKeys = Object.keys(props.engines);
const showForm = ref(false);
const form = useForm({ name: '', engine: engineKeys[0] });
function create() {
    form.post('/databases', { onSuccess: () => { showForm.value = false; form.reset(); } });
}
function destroy(d) {
    if (confirm(`Drop database "${d.name}"? This cannot be undone.`)) {
        router.delete(`/databases/${d.id}`);
    }
}
const engineColor = (e) => ({ mariadb: 'var(--cp-cy)', mysql: 'var(--cp-cy)', pgsql: 'var(--cp-grn)', sqlite: 'var(--cp-vio)' }[e] || 'var(--cp-mut)');
const field = 'box-sizing:border-box;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="databases" title="Databases" :subtitle="`${databases.length} database${databases.length === 1 ? '' : 's'}`">
        <template #actions>
            <button type="button" @click="showForm = !showForm"
                style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit">
                <i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New database
            </button>
        </template>

        <form v-if="showForm" @submit.prevent="create" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap">
            <label style="flex: 1; min-width: 200px; font-size: 12px; color: var(--cp-mut)">Name
                <input v-model="form.name" :style="field + ';width:100%;margin-top:5px'" placeholder="my_app" />
            </label>
            <label style="font-size: 12px; color: var(--cp-mut)">Engine
                <select v-model="form.engine" :style="field + ';margin-top:5px;display:block'">
                    <option v-for="(label, key) in engines" :key="key" :value="key">{{ label }}</option>
                </select>
            </label>
            <button type="submit" :disabled="form.processing"
                style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Create</button>
        </form>
        <p v-if="form.errors.name" style="color: var(--cp-red); font-size: 12px; margin: -8px 0 12px">{{ form.errors.name }}</p>

        <div v-if="databases.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div v-for="(d, i) in databases" :key="d.id"
                :style="`display:flex;align-items:center;gap:11px;padding:12px 15px;font-size:13px;${i < databases.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <i class="ti ti-database" :style="`font-size:17px;color:${engineColor(d.engine)}`" aria-hidden="true"></i>
                <span style="flex: 1; font-weight: 500; font-family: ui-monospace, monospace">{{ d.name }}</span>
                <span v-if="isOperator && d.owner" style="font-size: 11.5px; color: var(--cp-dim)">{{ d.owner }}</span>
                <span style="font-size: 11px; color: var(--cp-mut); font-family: ui-monospace, monospace">{{ d.db_user }}</span>
                <span style="font-size: 11px; padding: 2px 8px; border-radius: 999px; background: var(--cp-soft); color: var(--cp-mut)">{{ engines[d.engine] }}</span>
                <button type="button" @click="destroy(d)" aria-label="Drop database" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-trash" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
        </div>
        <div v-else style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 40px; text-align: center; color: var(--cp-dim); font-size: 13px">
            No databases yet.
        </div>
    </AppLayout>
</template>
