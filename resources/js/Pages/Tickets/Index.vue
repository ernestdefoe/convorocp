<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ tickets: Array, isOperator: Boolean });

const showForm = ref(false);
const form = useForm({ subject: '', priority: 'normal', body: '' });
const create = () => form.post('/tickets', { onSuccess: () => { showForm.value = false; form.reset(); } });
const open = (t) => router.get(`/tickets/${t.id}`);

const statusTone = { open: 'var(--cp-grn)', pending: 'var(--cp-amb)', closed: 'var(--cp-dim)' };
const prioTone = { high: 'var(--cp-red)', normal: 'var(--cp-mut)', low: 'var(--cp-dim)' };
const field = 'box-sizing:border-box;width:100%;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="tickets" title="Support" :subtitle="`${tickets.length} ticket${tickets.length === 1 ? '' : 's'}`">
        <template #actions v-if="!isOperator">
            <button type="button" @click="showForm = !showForm" style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit"><i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New ticket</button>
        </template>

        <form v-if="showForm" @submit.prevent="create" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 16px 18px; margin-bottom: 14px">
            <div style="display: flex; gap: 10px; margin-bottom: 10px">
                <input v-model="form.subject" placeholder="Subject" :style="field" />
                <select v-model="form.priority" :style="field + ';width:130px'"><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option></select>
            </div>
            <textarea v-model="form.body" rows="5" placeholder="Describe your issue…" :style="field + ';resize:vertical'"></textarea>
            <div v-if="form.errors.subject || form.errors.body" style="font-size: 11px; color: var(--cp-red); margin-top: 8px">{{ form.errors.subject || form.errors.body }}</div>
            <div style="display: flex; justify-content: flex-end; margin-top: 12px">
                <button type="submit" :disabled="form.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 18px; cursor: pointer; font-family: inherit">Submit</button>
            </div>
        </form>

        <div v-if="tickets.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <a v-for="(t, i) in tickets" :key="t.id" href="#" @click.prevent="open(t)"
                :style="`display:flex;align-items:center;gap:12px;padding:12px 15px;font-size:13px;text-decoration:none;color:inherit;${i < tickets.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <span :style="`width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${statusTone[t.status]}`"></span>
                <div style="flex: 1; min-width: 0">
                    <div style="font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ t.subject }}</div>
                    <div style="font-size: 11px; color: var(--cp-dim)"><span v-if="t.who">{{ t.who }} · </span>{{ t.updated }}</div>
                </div>
                <span :style="`font-size:10.5px;font-weight:600;text-transform:capitalize;color:${prioTone[t.priority]}`">{{ t.priority }}</span>
                <span :style="`font-size:10.5px;font-weight:600;padding:2px 9px;border-radius:999px;text-transform:capitalize;background:rgba(91,91,214,.12);color:${statusTone[t.status]}`">{{ t.status }}</span>
            </a>
        </div>
        <div v-else style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 40px; text-align: center; color: var(--cp-dim); font-size: 13px">
            {{ isOperator ? 'No tickets from customers yet.' : 'No tickets yet. Open one if you need help.' }}
        </div>
    </AppLayout>
</template>
