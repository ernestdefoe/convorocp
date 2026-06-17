<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ records: Array, types: Array });

const showForm = ref(false);
const form = useForm({ domain: '', type: 'A', name: '@', value: '', ttl: 3600 });
function create() {
    form.post('/dns', { onSuccess: () => { showForm.value = false; form.reset(); } });
}
function destroy(r) {
    if (confirm(`Delete ${r.type} record for ${r.name}?`)) {
        router.delete(`/dns/${r.id}`);
    }
}
const typeColor = (t) => ({ A: 'var(--cp-cy)', AAAA: 'var(--cp-vio)', CNAME: 'var(--cp-ind)', MX: 'var(--cp-amb)', TXT: 'var(--cp-grn)' }[t] || 'var(--cp-mut)');
const field = 'box-sizing:border-box;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="dns" title="DNS records" :subtitle="`${records.length} record${records.length === 1 ? '' : 's'}`">
        <template #actions>
            <button type="button" @click="showForm = !showForm"
                style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit">
                <i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>Add record
            </button>
        </template>

        <form v-if="showForm" @submit.prevent="create" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: grid; grid-template-columns: 1.4fr 90px 1fr 1.6fr 80px auto; gap: 10px; align-items: end">
            <label style="font-size: 11.5px; color: var(--cp-mut)">Domain<input v-model="form.domain" :style="field + ';width:100%;margin-top:5px'" placeholder="example.com" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Type<select v-model="form.type" :style="field + ';margin-top:5px;display:block;width:100%'"><option v-for="t in types" :key="t" :value="t">{{ t }}</option></select></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Name<input v-model="form.name" :style="field + ';width:100%;margin-top:5px'" placeholder="@" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Value<input v-model="form.value" :style="field + ';width:100%;margin-top:5px'" placeholder="86.48.28.240" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">TTL<input v-model.number="form.ttl" type="number" :style="field + ';width:100%;margin-top:5px'" /></label>
            <button type="submit" :disabled="form.processing" style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 14px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Add</button>
        </form>

        <div v-if="records.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div style="display: grid; grid-template-columns: 60px 1fr 1fr 1.6fr 56px 32px; gap: 10px; padding: 9px 15px; border-bottom: 1px solid var(--cp-ln); font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: var(--cp-dim)">
                <span>Type</span><span>Domain</span><span>Name</span><span>Value</span><span>TTL</span><span></span>
            </div>
            <div v-for="(r, i) in records" :key="r.id"
                :style="`display:grid;grid-template-columns:60px 1fr 1fr 1.6fr 56px 32px;gap:10px;align-items:center;padding:11px 15px;font-size:12.5px;${i < records.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <span :style="`font-size:11px;font-weight:600;padding:2px 7px;border-radius:6px;justify-self:start;background:var(--cp-soft);color:${typeColor(r.type)}`">{{ r.type }}</span>
                <span style="font-family: ui-monospace, monospace; color: var(--cp-mut); overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ r.domain }}</span>
                <span style="font-family: ui-monospace, monospace">{{ r.name }}</span>
                <span style="font-family: ui-monospace, monospace; color: var(--cp-mut); overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ r.value }}</span>
                <span style="font-family: ui-monospace, monospace; color: var(--cp-dim)">{{ r.ttl }}</span>
                <button type="button" @click="destroy(r)" aria-label="Delete record" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-trash" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
        </div>
        <div v-else style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 40px; text-align: center; color: var(--cp-dim); font-size: 13px">
            No DNS records yet.
        </div>
    </AppLayout>
</template>
