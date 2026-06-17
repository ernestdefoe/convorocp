<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ rules: Array, enabled: Boolean });

const showForm = ref(false);
const form = useForm({ port: 8080, proto: 'tcp', action: 'allow', note: '' });
function add() { form.post('/security/rules', { onSuccess: () => { showForm.value = false; form.reset(); } }); }
const remove = (r) => { if (confirm(`Remove rule ${r.action} ${r.port}/${r.proto}?`)) router.delete(`/security/rules/${r.id}`); };
const toggle = () => router.post('/security/toggle', {}, { preserveScroll: true });
const field = 'box-sizing:border-box;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="security" title="Security" subtitle="Firewall (ufw)">
        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 16px; margin-bottom: 14px; display: flex; align-items: center; gap: 12px">
            <i class="ti ti-shield-lock" :style="`font-size:22px;color:${enabled ? 'var(--cp-grn)' : 'var(--cp-dim)'}`" aria-hidden="true"></i>
            <div style="flex: 1">
                <div style="font-size: 14px; font-weight: 600">Firewall {{ enabled ? 'enabled' : 'disabled' }}</div>
                <div style="font-size: 11.5px; color: var(--cp-dim)">Enabling always allows 22/80/443 first so SSH and web stay reachable.</div>
            </div>
            <button type="button" @click="toggle" :aria-label="enabled ? 'Disable firewall' : 'Enable firewall'"
                :style="`width:46px;height:25px;border:0;border-radius:999px;position:relative;cursor:pointer;flex-shrink:0;background:${enabled ? 'var(--cp-grn)' : 'var(--cp-ln2)'}`">
                <span :style="`position:absolute;top:2px;width:21px;height:21px;border-radius:50%;background:#fff;${enabled ? 'right:2px' : 'left:2px'}`"></span>
            </button>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px">
            <div style="flex: 1; font-size: 13px; font-weight: 600">Rules</div>
            <button type="button" @click="showForm = !showForm" style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit"><i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>Add rule</button>
        </div>

        <form v-if="showForm" @submit.prevent="add" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: grid; grid-template-columns: 90px 90px 90px 1fr auto; gap: 10px; align-items: end">
            <label style="font-size: 11.5px; color: var(--cp-mut)">Port<input v-model.number="form.port" type="number" :style="field + ';width:100%;margin-top:5px'" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Proto<select v-model="form.proto" :style="field + ';margin-top:5px;display:block;width:100%'"><option value="tcp">tcp</option><option value="udp">udp</option></select></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Action<select v-model="form.action" :style="field + ';margin-top:5px;display:block;width:100%'"><option value="allow">allow</option><option value="deny">deny</option></select></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Note<input v-model="form.note" :style="field + ';width:100%;margin-top:5px'" placeholder="optional" /></label>
            <button type="submit" :disabled="form.processing" style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 14px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Add</button>
        </form>

        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div v-for="(r, i) in rules" :key="r.id"
                :style="`display:flex;align-items:center;gap:12px;padding:11px 15px;font-size:13px;${i < rules.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <span :style="`font-size:11px;font-weight:600;padding:2px 9px;border-radius:6px;${r.action === 'allow' ? 'background:rgba(52,211,153,.16);color:var(--cp-grn)' : 'background:rgba(248,113,113,.16);color:var(--cp-red)'}`">{{ r.action }}</span>
                <span style="font-family: ui-monospace, monospace; font-weight: 500">{{ r.port }}/{{ r.proto }}</span>
                <span style="flex: 1; font-size: 11.5px; color: var(--cp-dim)">{{ r.note }}</span>
                <button type="button" @click="remove(r)" aria-label="Remove" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-trash" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
        </div>
    </AppLayout>
</template>
