<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    accounts: Array, selected: Number, folder: String, folders: Array,
    messages: Array, open: Object, error: String, canRead: Boolean,
});

const composeOpen = ref(false);
const newboxOpen = ref(false);

const go = (params) => router.get('/mail', params, { preserveScroll: true });
const selectAccount = (id) => go({ account: id, folder: 'INBOX' });
const selectFolder = (f) => go({ account: props.selected, folder: f });
const openMsg = (uid) => go({ account: props.selected, folder: props.folder, uid });
const back = () => go({ account: props.selected, folder: props.folder });

const compose = useForm({ to: '', subject: '', body: '' });
function sendMail() {
    compose.post(`/mail/${props.selected}/send`, { preserveScroll: true, onSuccess: () => { composeOpen.value = false; compose.reset(); } });
}

const newbox = useForm({ local: '', domain: 'convorocp.local', password: '' });
function createBox() {
    newbox.post('/mail', { onSuccess: () => { newboxOpen.value = false; newbox.reset(); } });
}
const delBox = (a) => { if (confirm(`Delete mailbox ${a.email}? This removes the mailbox and all its mail.`)) router.delete(`/mail/${a.id}`); };

const field = 'box-sizing:border-box;width:100%;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
const folderIcon = { INBOX: 'ti-inbox', Sent: 'ti-send', Drafts: 'ti-file', Trash: 'ti-trash' };
</script>

<template>
    <AppLayout active="mail" title="Webmail" subtitle="Mailboxes on this server">
        <template #actions>
            <button type="button" @click="newboxOpen = true" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-family: inherit; margin-right: 8px"><i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New mailbox</button>
            <button type="button" :disabled="!selected || !canRead" @click="composeOpen = true" :style="`font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; font-family: inherit; ${(!selected || !canRead) ? 'opacity:.45;cursor:default' : 'cursor:pointer'}`"><i class="ti ti-pencil" style="font-size: 14px" aria-hidden="true"></i>Compose</button>
        </template>

        <div v-if="!accounts.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 48px; text-align: center">
            <i class="ti ti-mail-opened" style="font-size: 30px; color: var(--cp-dim)" aria-hidden="true"></i>
            <div style="font-size: 14px; font-weight: 600; margin: 12px 0 4px">No mailboxes yet</div>
            <div style="font-size: 12px; color: var(--cp-dim); margin-bottom: 16px">Create your first mailbox to start sending and receiving mail.</div>
            <button type="button" @click="newboxOpen = true" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 16px; cursor: pointer; font-family: inherit">Create mailbox</button>
        </div>

        <div v-else style="display: grid; grid-template-columns: 200px 320px 1fr; gap: 14px; height: calc(100vh - 150px)">
            <!-- accounts + folders -->
            <div style="display: flex; flex-direction: column; gap: 6px; overflow-y: auto">
                <select :value="selected" @change="selectAccount(Number($event.target.value))" :style="field + ';margin-bottom:6px'">
                    <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.email }}{{ a.owner && !a.mine ? ' — ' + a.owner : '' }}</option>
                </select>
                <a v-for="f in folders" :key="f" href="#" @click.prevent="selectFolder(f)"
                    :style="`display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:9px;font-size:13px;text-decoration:none;${f === folder ? 'background:rgba(91,91,214,.16);color:var(--cp-ink)' : 'color:var(--cp-mut)'}`">
                    <i class="ti" :class="folderIcon[f]" style="font-size: 16px" aria-hidden="true"></i>{{ f === 'INBOX' ? 'Inbox' : f }}
                </a>
                <div style="margin-top: auto; padding-top: 10px">
                    <button type="button" @click="delBox(accounts.find(a => a.id === selected))" style="font-size: 11px; color: var(--cp-dim); background: transparent; border: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-family: inherit"><i class="ti ti-trash" style="font-size: 13px" aria-hidden="true"></i>Delete this mailbox</button>
                </div>
            </div>

            <!-- message list -->
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow-y: auto">
                <div v-if="!canRead" style="padding: 30px 22px; text-align: center; font-size: 12.5px; color: var(--cp-dim); line-height: 1.6">
                    <i class="ti ti-lock" style="font-size: 26px; display: block; margin-bottom: 10px; color: var(--cp-mut)" aria-hidden="true"></i>
                    You can manage this mailbox (create, delete), but only its owner can read its email.
                </div>
                <template v-else>
                <div v-if="error" style="padding: 24px; text-align: center; font-size: 12.5px; color: var(--cp-red)">{{ error }}</div>
                <div v-else-if="!messages.length" style="padding: 36px; text-align: center; font-size: 12.5px; color: var(--cp-dim)">No messages in {{ folder === 'INBOX' ? 'Inbox' : folder }}.</div>
                <a v-for="(m, i) in messages" :key="m.uid" href="#" @click.prevent="openMsg(m.uid)"
                    :style="`display:block;padding:11px 14px;text-decoration:none;color:inherit;${i < messages.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}${open && open.uid === m.uid ? 'background:rgba(91,91,214,.12)' : ''}`">
                    <div style="display: flex; align-items: center; gap: 6px">
                        <span v-if="!m.seen" style="width: 7px; height: 7px; border-radius: 50%; background: var(--cp-ind); flex-shrink: 0"></span>
                        <span :style="`flex:1;min-width:0;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;${m.seen ? '' : 'font-weight:600'}`">{{ m.from }}</span>
                        <span style="font-size: 10.5px; color: var(--cp-dim); flex-shrink: 0">{{ m.date }}</span>
                    </div>
                    <div :style="`font-size:12.5px;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;${m.seen ? 'color:var(--cp-mut)' : ''}`">{{ m.subject }}</div>
                </a>
                </template>
            </div>

            <!-- reading pane -->
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow-y: auto; padding: 0">
                <div v-if="open" style="display: flex; flex-direction: column; height: 100%">
                    <div style="padding: 16px 18px; border-bottom: 1px solid var(--cp-ln)">
                        <div style="font-size: 16px; font-weight: 600; margin-bottom: 8px">{{ open.subject }}</div>
                        <div style="font-size: 12px; color: var(--cp-mut)">{{ open.from }}</div>
                        <div style="font-size: 11px; color: var(--cp-dim)">to {{ open.to }} · {{ open.date }}</div>
                    </div>
                    <div style="padding: 18px; font-size: 13px; line-height: 1.6; overflow-y: auto">
                        <div v-if="open.html" v-html="open.html"></div>
                        <pre v-else style="white-space: pre-wrap; font-family: inherit; margin: 0">{{ open.text }}</pre>
                    </div>
                </div>
                <div v-else style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--cp-dim); font-size: 13px">
                    Select a message to read
                </div>
            </div>
        </div>

        <!-- compose modal -->
        <div v-if="composeOpen" @click.self="composeOpen = false" style="position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 50">
            <form @submit.prevent="sendMail" style="background: var(--cp-side); border: 1px solid var(--cp-ln); border-radius: 14px; padding: 20px; width: 560px; max-width: 92vw">
                <div style="font-size: 15px; font-weight: 600; margin-bottom: 14px">New message</div>
                <div style="font-size: 11px; color: var(--cp-dim); margin-bottom: 10px">From {{ accounts.find(a => a.id === selected)?.email }}</div>
                <input v-model="compose.to" type="email" required placeholder="To" :style="field + ';margin-bottom:8px'" />
                <input v-model="compose.subject" placeholder="Subject" :style="field + ';margin-bottom:8px'" />
                <textarea v-model="compose.body" required rows="9" placeholder="Write your message…" :style="field + ';resize:vertical'"></textarea>
                <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px">
                    <button type="button" @click="composeOpen = false" style="font-size: 13px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 9px; padding: 8px 14px; cursor: pointer; font-family: inherit">Cancel</button>
                    <button type="submit" :disabled="compose.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 8px 18px; cursor: pointer; font-family: inherit">{{ compose.processing ? 'Sending…' : 'Send' }}</button>
                </div>
            </form>
        </div>

        <!-- new mailbox modal -->
        <div v-if="newboxOpen" @click.self="newboxOpen = false" style="position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 50">
            <form @submit.prevent="createBox" style="background: var(--cp-side); border: 1px solid var(--cp-ln); border-radius: 14px; padding: 20px; width: 440px; max-width: 92vw">
                <div style="font-size: 15px; font-weight: 600; margin-bottom: 14px">New mailbox</div>
                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 10px">
                    <input v-model="newbox.local" required placeholder="name" :style="field" />
                    <span style="color: var(--cp-dim)">@</span>
                    <input v-model="newbox.domain" required placeholder="domain.com" :style="field" />
                </div>
                <input v-model="newbox.password" type="password" required placeholder="Mailbox password (no spaces or colons)" :style="field" />
                <div v-if="newbox.errors.local || newbox.errors.domain || newbox.errors.password" style="font-size: 11px; color: var(--cp-red); margin-top: 8px">{{ newbox.errors.local || newbox.errors.domain || newbox.errors.password }}</div>
                <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px">
                    <button type="button" @click="newboxOpen = false" style="font-size: 13px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 9px; padding: 8px 14px; cursor: pointer; font-family: inherit">Cancel</button>
                    <button type="submit" :disabled="newbox.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 8px 18px; cursor: pointer; font-family: inherit">Create</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
