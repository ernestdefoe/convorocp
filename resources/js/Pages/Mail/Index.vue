<script setup>
import { ref, computed, nextTick } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    accounts: Array, selected: Number, canRead: Boolean,
    folder: String, folders: Array, messages: Array, open: Object,
    error: String, search: String, page: Number, hasMore: Boolean,
});

const composeOpen = ref(false);
const newboxOpen = ref(false);
const moveOpen = ref(false);
const bodyEl = ref(null);
const searchInput = ref(props.search || '');

const params = (extra = {}) => ({ account: props.selected, folder: props.folder, ...extra });
const go = (extra) => router.get('/mail', params(extra), { preserveScroll: true });
const selectAccount = (id) => router.get('/mail', { account: id, folder: 'INBOX' });
const selectFolder = (f) => router.get('/mail', { account: props.selected, folder: f });
const openMsg = (uid) => go({ uid });
const back = () => router.get('/mail', params(), { preserveScroll: false });
const runSearch = () => router.get('/mail', { account: props.selected, folder: props.folder, q: searchInput.value }, { preserveScroll: true });
const clearSearch = () => { searchInput.value = ''; runSearch(); };
const gotoPage = (p) => router.get('/mail', params({ q: props.search, page: Math.max(1, p) }), { preserveScroll: true });

// ── Compose ───────────────────────────────────────────────────────────────
const compose = useForm({ to: '', cc: '', bcc: '', subject: '', body: '', html: true, in_reply_to: '', attachments: [] });
const showCc = ref(false);
const attachNames = computed(() => Array.from(compose.attachments || []).map((f) => f.name));

function openCompose({ to = '', cc = '', subject = '', body = '', inReplyTo = '' } = {}) {
    compose.reset();
    compose.to = to; compose.cc = cc; compose.subject = subject; compose.in_reply_to = inReplyTo;
    showCc.value = !!cc;
    composeOpen.value = true;
    nextTick(() => { if (bodyEl.value) bodyEl.value.innerHTML = body; });
}
function onFiles(e) { compose.attachments = Array.from(e.target.files || []); }
function exec(cmd) { document.execCommand(cmd, false, null); bodyEl.value?.focus(); }
function makeLink() { const u = prompt('Link URL'); if (u) document.execCommand('createLink', false, u); }

function sendMail() {
    compose.body = bodyEl.value ? bodyEl.value.innerHTML : '';
    compose.post(`/mail/${props.selected}/send`, {
        preserveScroll: true, forceFormData: true,
        onSuccess: () => { composeOpen.value = false; compose.reset(); },
    });
}

// ── Reply / forward (quote the open message) ────────────────────────────────
const stripRe = (s, p) => { const r = new RegExp(`^(${p}:\\s*)+`, 'i'); return (s || '').replace(r, ''); };
function quoted(o) {
    const inner = o.html || ('<pre style="white-space:pre-wrap;font-family:inherit">' + escapeHtml(o.text || '') + '</pre>');
    return `<br><br><blockquote style="margin:0 0 0 8px;padding-left:12px;border-left:2px solid #ccc;color:#666">`
        + `<div style="font-size:12px;color:#888;margin-bottom:6px">On ${escapeHtml(o.date || '')}, ${escapeHtml(o.from || '')} wrote:</div>${inner}</blockquote>`;
}
function escapeHtml(s) { return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
const reply = () => openCompose({ to: props.open.fromRaw, subject: 'Re: ' + stripRe(props.open.subject, 'Re'), body: quoted(props.open), inReplyTo: props.open.messageId });
const replyAll = () => {
    const self = currentEmail();
    const all = [props.open.fromRaw, ...splitEmails(props.open.to), ...splitEmails(props.open.cc)]
        .filter((e) => e && e.toLowerCase() !== self.toLowerCase());
    openCompose({ to: [...new Set(all)].join(', '), subject: 'Re: ' + stripRe(props.open.subject, 'Re'), body: quoted(props.open), inReplyTo: props.open.messageId });
};
const forward = () => openCompose({ subject: 'Fwd: ' + stripRe(props.open.subject, 'Fwd'), body: quoted(props.open) });
function splitEmails(s) { return (s || '').match(/[^\s<>,;]+@[^\s<>,;]+/g) || []; }
function currentEmail() { return props.accounts.find((a) => a.id === props.selected)?.email || ''; }

// ── Message actions ─────────────────────────────────────────────────────────
const act = (action, target = null) => router.post(`/mail/${props.selected}/message`, { uid: props.open.uid, folder: props.folder, action, target }, {
    preserveScroll: true, onSuccess: () => { if (action === 'delete' || action === 'move') back(); },
});
const moveTo = (path) => { moveOpen.value = false; act('move', path); };
const attUrl = (i) => `/mail/${props.selected}/attachment?folder=${encodeURIComponent(props.folder)}&uid=${props.open.uid}&index=${i}`;
const fmtSize = (b) => b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';

const newbox = useForm({ local: '', domain: 'convorocp.local', password: '' });
function createBox() { newbox.post('/mail', { onSuccess: () => { newboxOpen.value = false; newbox.reset(); } }); }
const delBox = (a) => { if (a && confirm(`Delete mailbox ${a.email}? This removes the mailbox and all its mail.`)) router.delete(`/mail/${a.id}`); };

const field = 'box-sizing:border-box;width:100%;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
const folderIcon = (p) => ({ INBOX: 'ti-inbox', Sent: 'ti-send', Drafts: 'ti-file', Trash: 'ti-trash', Junk: 'ti-alert-octagon' }[p] || 'ti-folder');
const selAccount = computed(() => props.accounts.find((a) => a.id === props.selected));
</script>

<template>
    <AppLayout active="mail" title="Webmail" subtitle="Mailboxes on this server">
        <template #actions>
            <button type="button" @click="newboxOpen = true" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-family: inherit; margin-right: 8px"><i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New mailbox</button>
            <button type="button" :disabled="!selected || !canRead" @click="openCompose()" :style="`font-size:12px;color:#fff;background:var(--cp-ind);border:0;border-radius:8px;padding:7px 12px;display:inline-flex;align-items:center;gap:5px;font-weight:500;font-family:inherit;${(!selected||!canRead)?'opacity:.45;cursor:default':'cursor:pointer'}`"><i class="ti ti-pencil" style="font-size: 14px" aria-hidden="true"></i>Compose</button>
        </template>

        <div v-if="!accounts.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 48px; text-align: center">
            <i class="ti ti-mail-opened" style="font-size: 30px; color: var(--cp-dim)" aria-hidden="true"></i>
            <div style="font-size: 14px; font-weight: 600; margin: 12px 0 4px">No mailboxes yet</div>
            <div style="font-size: 12px; color: var(--cp-dim); margin-bottom: 16px">Create your first mailbox to start sending and receiving mail.</div>
            <button type="button" @click="newboxOpen = true" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 16px; cursor: pointer; font-family: inherit">Create mailbox</button>
        </div>

        <div v-else style="display: grid; grid-template-columns: 210px 340px 1fr; gap: 14px; height: calc(100vh - 150px)">
            <!-- accounts + folders -->
            <div style="display: flex; flex-direction: column; gap: 4px; overflow-y: auto">
                <select :value="selected" @change="selectAccount(Number($event.target.value))" :style="field + ';margin-bottom:8px'">
                    <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.email }}{{ a.owner && !a.mine ? ' — ' + a.owner : '' }}</option>
                </select>
                <a v-for="f in folders" :key="f.path" href="#" @click.prevent="selectFolder(f.path)"
                    :style="`display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:9px;font-size:13px;text-decoration:none;${f.path === folder ? 'background:rgba(91,91,214,.16);color:var(--cp-ink)' : 'color:var(--cp-mut)'}`">
                    <i class="ti" :class="folderIcon(f.path)" style="font-size: 16px" aria-hidden="true"></i>
                    <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ f.name }}</span>
                    <span v-if="f.unread" style="font-size: 10.5px; font-weight: 600; color: #fff; background: var(--cp-ind); border-radius: 99px; padding: 1px 6px; min-width: 16px; text-align: center">{{ f.unread }}</span>
                </a>
                <div v-if="selAccount && selAccount.mine" style="margin-top: auto; padding-top: 10px">
                    <button type="button" @click="delBox(selAccount)" style="font-size: 11px; color: var(--cp-dim); background: transparent; border: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-family: inherit"><i class="ti ti-trash" style="font-size: 13px" aria-hidden="true"></i>Delete this mailbox</button>
                </div>
            </div>

            <!-- message list -->
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; display: flex; flex-direction: column; overflow: hidden">
                <div v-if="canRead" style="padding: 10px; border-bottom: 1px solid var(--cp-ln); position: relative">
                    <i class="ti ti-search" style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); font-size: 14px; color: var(--cp-dim)" aria-hidden="true"></i>
                    <input v-model="searchInput" @keyup.enter="runSearch" placeholder="Search this folder…" :style="field + ';padding-left:30px'" />
                    <button v-if="search" type="button" @click="clearSearch" style="position: absolute; right: 18px; top: 50%; transform: translateY(-50%); background: transparent; border: 0; color: var(--cp-dim); cursor: pointer"><i class="ti ti-x" style="font-size: 14px" aria-hidden="true"></i></button>
                </div>
                <div style="flex: 1; overflow-y: auto">
                    <div v-if="!canRead" style="padding: 30px 22px; text-align: center; font-size: 12.5px; color: var(--cp-dim); line-height: 1.6">
                        <i class="ti ti-lock" style="font-size: 26px; display: block; margin-bottom: 10px; color: var(--cp-mut)" aria-hidden="true"></i>
                        You can manage this mailbox (create, delete), but only its owner can read its email.
                    </div>
                    <template v-else>
                        <div v-if="error" style="padding: 24px; text-align: center; font-size: 12.5px; color: var(--cp-red)">{{ error }}</div>
                        <div v-else-if="!messages.length" style="padding: 36px; text-align: center; font-size: 12.5px; color: var(--cp-dim)">{{ search ? 'No messages match your search.' : 'No messages here.' }}</div>
                        <a v-for="(m, i) in messages" :key="m.uid" href="#" @click.prevent="openMsg(m.uid)"
                            :style="`display:block;padding:11px 14px;text-decoration:none;color:inherit;border-bottom:1px solid var(--cp-ln);${open && open.uid === m.uid ? 'background:rgba(91,91,214,.12)' : ''}`">
                            <div style="display: flex; align-items: center; gap: 6px">
                                <span v-if="!m.seen" style="width: 7px; height: 7px; border-radius: 50%; background: var(--cp-ind); flex-shrink: 0"></span>
                                <span :style="`flex:1;min-width:0;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;${m.seen ? '' : 'font-weight:600'}`">{{ m.from }}</span>
                                <i v-if="m.attachments" class="ti ti-paperclip" style="font-size: 13px; color: var(--cp-dim)" aria-hidden="true"></i>
                                <span style="font-size: 10.5px; color: var(--cp-dim); flex-shrink: 0">{{ m.date }}</span>
                            </div>
                            <div :style="`font-size:12.5px;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;${m.seen ? 'color:var(--cp-mut)' : ''}`">{{ m.subject }}</div>
                        </a>
                        <div v-if="page > 1 || hasMore" style="padding: 10px; display: flex; align-items: center; justify-content: center; gap: 12px; border-top: 1px solid var(--cp-ln)">
                            <button type="button" :disabled="page <= 1" @click="gotoPage(page - 1)" :style="`font-size:12px;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:8px;padding:6px 12px;font-family:inherit;${page <= 1 ? 'opacity:.4;cursor:default;color:var(--cp-dim)' : 'cursor:pointer;color:var(--cp-mut)'}`">Prev</button>
                            <span style="font-size: 11.5px; color: var(--cp-dim)">Page {{ page }}</span>
                            <button type="button" :disabled="!hasMore" @click="gotoPage(page + 1)" :style="`font-size:12px;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:8px;padding:6px 12px;font-family:inherit;${!hasMore ? 'opacity:.4;cursor:default;color:var(--cp-dim)' : 'cursor:pointer;color:var(--cp-mut)'}`">Next</button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- reading pane -->
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden; display: flex; flex-direction: column">
                <div v-if="open" style="display: flex; flex-direction: column; height: 100%">
                    <div style="padding: 14px 18px; border-bottom: 1px solid var(--cp-ln)">
                        <div style="display: flex; align-items: flex-start; gap: 10px">
                            <div style="flex: 1; min-width: 0">
                                <div style="font-size: 16px; font-weight: 600; margin-bottom: 8px">{{ open.subject || '(no subject)' }}</div>
                                <div style="font-size: 12px; color: var(--cp-mut)">{{ open.from }}</div>
                                <div style="font-size: 11px; color: var(--cp-dim)">to {{ open.to }}<span v-if="open.cc"> · cc {{ open.cc }}</span> · {{ open.date }}</div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 6px; margin-top: 12px; flex-wrap: wrap">
                            <button type="button" @click="reply" style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 6px 12px; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px"><i class="ti ti-arrow-back-up" style="font-size: 14px" aria-hidden="true"></i>Reply</button>
                            <button type="button" @click="replyAll" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 6px 12px; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px"><i class="ti ti-arrow-back-up-double" style="font-size: 14px" aria-hidden="true"></i>Reply all</button>
                            <button type="button" @click="forward" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 6px 12px; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px"><i class="ti ti-arrow-forward-up" style="font-size: 14px" aria-hidden="true"></i>Forward</button>
                            <div style="flex: 1"></div>
                            <button type="button" @click="act('unseen')" title="Mark unread" style="font-size: 12px; color: var(--cp-dim); background: transparent; border: 1px solid var(--cp-ln); border-radius: 8px; padding: 6px 9px; cursor: pointer; font-family: inherit"><i class="ti ti-mail" style="font-size: 14px" aria-hidden="true"></i></button>
                            <button type="button" @click="moveOpen = !moveOpen" title="Move" style="font-size: 12px; color: var(--cp-dim); background: transparent; border: 1px solid var(--cp-ln); border-radius: 8px; padding: 6px 9px; cursor: pointer; font-family: inherit; position: relative"><i class="ti ti-folder-share" style="font-size: 14px" aria-hidden="true"></i>
                                <div v-if="moveOpen" @click.stop style="position: absolute; right: 0; top: 110%; z-index: 20; background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 9px; padding: 5px; min-width: 150px; box-shadow: 0 8px 24px rgba(0,0,0,.3)">
                                    <a v-for="f in folders.filter(x => x.path !== folder)" :key="f.path" href="#" @click.prevent="moveTo(f.path)" style="display: block; padding: 7px 10px; border-radius: 7px; font-size: 12.5px; color: var(--cp-mut); text-decoration: none">{{ f.name }}</a>
                                </div>
                            </button>
                            <button type="button" @click="act('delete')" title="Delete" style="font-size: 12px; color: var(--cp-red); background: transparent; border: 1px solid var(--cp-ln); border-radius: 8px; padding: 6px 9px; cursor: pointer; font-family: inherit"><i class="ti ti-trash" style="font-size: 14px" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div style="padding: 18px; font-size: 13px; line-height: 1.6; overflow-y: auto; flex: 1">
                        <div v-if="open.attachments && open.attachments.length" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid var(--cp-ln)">
                            <a v-for="att in open.attachments" :key="att.index" :href="attUrl(att.index)" style="display: inline-flex; align-items: center; gap: 7px; background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 9px; padding: 7px 11px; font-size: 12px; color: var(--cp-ink); text-decoration: none">
                                <i class="ti ti-paperclip" style="font-size: 14px; color: var(--cp-dim)" aria-hidden="true"></i>
                                <span style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ att.name }}</span>
                                <span style="color: var(--cp-dim)">{{ fmtSize(att.size) }}</span>
                            </a>
                        </div>
                        <div v-if="open.html" v-html="open.html"></div>
                        <pre v-else style="white-space: pre-wrap; font-family: inherit; margin: 0">{{ open.text }}</pre>
                    </div>
                </div>
                <div v-else style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--cp-dim); font-size: 13px">
                    {{ canRead ? 'Select a message to read' : '' }}
                </div>
            </div>
        </div>

        <!-- compose modal -->
        <div v-if="composeOpen" @click.self="composeOpen = false" style="position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 50; padding: 20px">
            <form @submit.prevent="sendMail" style="background: var(--cp-bg); border: 1px solid var(--cp-ln); border-radius: 15px; width: 640px; max-width: 100%; max-height: 92vh; display: flex; flex-direction: column; overflow: hidden">
                <div style="padding: 14px 18px; border-bottom: 1px solid var(--cp-ln); display: flex; align-items: center"><span style="font-size: 14px; font-weight: 600; flex: 1">New message</span><button type="button" @click="composeOpen = false" style="background: transparent; border: 0; color: var(--cp-dim); cursor: pointer"><i class="ti ti-x" style="font-size: 18px" aria-hidden="true"></i></button></div>
                <div style="padding: 14px 18px; overflow-y: auto; flex: 1">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px">
                        <input v-model="compose.to" type="text" required placeholder="To" :style="field" />
                        <button type="button" @click="showCc = !showCc" style="font-size: 12px; color: var(--cp-dim); background: transparent; border: 0; cursor: pointer; white-space: nowrap; font-family: inherit">Cc/Bcc</button>
                    </div>
                    <input v-if="showCc" v-model="compose.cc" placeholder="Cc" :style="field + ';margin-bottom:8px'" />
                    <input v-if="showCc" v-model="compose.bcc" placeholder="Bcc" :style="field + ';margin-bottom:8px'" />
                    <input v-model="compose.subject" placeholder="Subject" :style="field + ';margin-bottom:8px'" />
                    <div style="display: flex; gap: 4px; margin-bottom: 6px">
                        <button type="button" @click="exec('bold')" style="width: 30px; height: 28px; border: 1px solid var(--cp-ln); background: var(--cp-card2); border-radius: 7px; color: var(--cp-mut); cursor: pointer; font-weight: 700; font-family: inherit">B</button>
                        <button type="button" @click="exec('italic')" style="width: 30px; height: 28px; border: 1px solid var(--cp-ln); background: var(--cp-card2); border-radius: 7px; color: var(--cp-mut); cursor: pointer; font-style: italic; font-family: inherit">I</button>
                        <button type="button" @click="exec('underline')" style="width: 30px; height: 28px; border: 1px solid var(--cp-ln); background: var(--cp-card2); border-radius: 7px; color: var(--cp-mut); cursor: pointer; text-decoration: underline; font-family: inherit">U</button>
                        <button type="button" @click="exec('insertUnorderedList')" style="width: 30px; height: 28px; border: 1px solid var(--cp-ln); background: var(--cp-card2); border-radius: 7px; color: var(--cp-mut); cursor: pointer; font-family: inherit"><i class="ti ti-list" style="font-size: 14px"></i></button>
                        <button type="button" @click="makeLink" style="width: 30px; height: 28px; border: 1px solid var(--cp-ln); background: var(--cp-card2); border-radius: 7px; color: var(--cp-mut); cursor: pointer; font-family: inherit"><i class="ti ti-link" style="font-size: 14px"></i></button>
                    </div>
                    <div ref="bodyEl" contenteditable="true" style="min-height: 180px; max-height: 40vh; overflow-y: auto; background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 9px; color: var(--cp-ink); padding: 11px; font-size: 13px; line-height: 1.6; outline: none"></div>
                    <div v-if="attachNames.length" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px">
                        <span v-for="n in attachNames" :key="n" style="display: inline-flex; align-items: center; gap: 5px; background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 7px; padding: 4px 9px; font-size: 11.5px; color: var(--cp-mut)"><i class="ti ti-paperclip" style="font-size: 12px" aria-hidden="true"></i>{{ n }}</span>
                    </div>
                </div>
                <div style="padding: 12px 18px; border-top: 1px solid var(--cp-ln); display: flex; align-items: center; gap: 10px">
                    <button type="submit" :disabled="compose.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 20px; font-weight: 500; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 6px"><i class="ti ti-send" style="font-size: 15px" aria-hidden="true"></i>{{ compose.processing ? 'Sending…' : 'Send' }}</button>
                    <label style="font-size: 12px; color: var(--cp-mut); cursor: pointer; display: inline-flex; align-items: center; gap: 5px"><i class="ti ti-paperclip" style="font-size: 16px" aria-hidden="true"></i>Attach<input type="file" multiple @change="onFiles" style="display: none" /></label>
                    <span v-if="compose.errors.to" style="font-size: 11.5px; color: var(--cp-red)">{{ compose.errors.to }}</span>
                </div>
            </form>
        </div>

        <!-- new mailbox modal -->
        <div v-if="newboxOpen" @click.self="newboxOpen = false" style="position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 50">
            <form @submit.prevent="createBox" style="background: var(--cp-bg); border: 1px solid var(--cp-ln); border-radius: 15px; width: 420px; max-width: 92%; padding: 20px">
                <div style="font-size: 15px; font-weight: 600; margin-bottom: 14px">New mailbox</div>
                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px">
                    <input v-model="newbox.local" required placeholder="name" :style="field" />
                    <span style="color: var(--cp-dim)">@</span>
                    <input v-model="newbox.domain" required placeholder="domain" :style="field" />
                </div>
                <input v-model="newbox.password" type="password" required placeholder="Mailbox password" :style="field + ';margin-bottom:14px'" />
                <div style="display: flex; gap: 8px; justify-content: flex-end">
                    <button type="button" @click="newboxOpen = false" style="font-size: 13px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 9px; padding: 9px 16px; cursor: pointer; font-family: inherit">Cancel</button>
                    <button type="submit" :disabled="newbox.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 16px; cursor: pointer; font-family: inherit">Create</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
