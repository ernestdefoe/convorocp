<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ ticket: Object, messages: Array, isOperator: Boolean });

const reply = useForm({ body: '' });
const send = () => reply.post(`/tickets/${props.ticket.id}/reply`, { preserveScroll: true, onSuccess: () => reply.reset() });
const setStatus = (status) => router.patch(`/tickets/${props.ticket.id}/status`, { status }, { preserveScroll: true });

const statusTone = { open: 'var(--cp-grn)', pending: 'var(--cp-amb)', closed: 'var(--cp-dim)' };
const field = 'box-sizing:border-box;width:100%;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:10px 12px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="tickets" :title="ticket.subject" subtitle="Support ticket">
        <template #actions>
            <a href="/tickets" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; text-decoration: none; margin-right: 8px">← All tickets</a>
            <select v-if="isOperator" :value="ticket.status" @change="setStatus($event.target.value)" style="font-size: 12px; background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 10px; color: var(--cp-ink); font-family: inherit; cursor: pointer">
                <option value="open">Open</option>
                <option value="pending">Pending</option>
                <option value="closed">Closed</option>
            </select>
            <button v-else-if="ticket.status !== 'closed'" type="button" @click="setStatus('closed')" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; cursor: pointer; font-family: inherit">Close ticket</button>
            <button v-else type="button" @click="setStatus('open')" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; cursor: pointer; font-family: inherit">Reopen</button>
        </template>

        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; font-size: 12px; color: var(--cp-dim)">
            <span :style="`font-weight:600;padding:2px 10px;border-radius:999px;text-transform:capitalize;background:rgba(91,91,214,.12);color:${statusTone[ticket.status]}`">{{ ticket.status }}</span>
            <span style="text-transform: capitalize">{{ ticket.priority }} priority</span>
            <span v-if="isOperator && ticket.who">· {{ ticket.who }}</span>
        </div>

        <div style="max-width: 720px; display: flex; flex-direction: column; gap: 12px; margin-bottom: 18px">
            <div v-for="m in messages" :key="m.id"
                :style="`border:1px solid var(--cp-ln);border-radius:12px;padding:13px 15px;${m.staff ? 'background:rgba(91,91,214,.07);border-color:rgba(91,91,214,.25)' : 'background:var(--cp-card)'}`">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 7px">
                    <span style="font-size: 12.5px; font-weight: 600">{{ m.author }}</span>
                    <span v-if="m.staff" style="font-size: 9.5px; font-weight: 700; color: var(--cp-vio); background: rgba(91,91,214,.16); padding: 1px 7px; border-radius: 999px; letter-spacing: 0.03em">STAFF</span>
                    <span style="margin-left: auto; font-size: 11px; color: var(--cp-dim)">{{ m.at }}</span>
                </div>
                <div style="font-size: 13px; line-height: 1.55; white-space: pre-wrap; color: var(--cp-mut)">{{ m.body }}</div>
            </div>
        </div>

        <form v-if="ticket.status !== 'closed'" @submit.prevent="send" style="max-width: 720px">
            <textarea v-model="reply.body" rows="4" placeholder="Write a reply…" :style="field + ';resize:vertical'"></textarea>
            <div style="display: flex; justify-content: flex-end; margin-top: 10px">
                <button type="submit" :disabled="reply.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 20px; cursor: pointer; font-family: inherit">{{ reply.processing ? 'Sending…' : 'Reply' }}</button>
            </div>
        </form>
        <div v-else style="max-width: 720px; font-size: 12.5px; color: var(--cp-dim); text-align: center; padding: 14px; border: 1px dashed var(--cp-ln); border-radius: 12px">This ticket is closed. Reopen it to add a reply.</div>
    </AppLayout>
</template>
