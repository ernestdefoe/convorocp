<script setup>
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ site: Object, path: String, parent: { type: String, default: null }, entries: { type: Array, default: null }, file: { type: Object, default: null }, provisioned: { type: Boolean, default: true } });

const url = (p) => `/sites/${props.site.id}/files?path=${encodeURIComponent(p)}`;
const human = (n) => (n == null ? '' : n < 1024 ? n + ' B' : n < 1048576 ? (n / 1024).toFixed(1) + ' KB' : (n / 1048576).toFixed(1) + ' MB');

const edit = useForm({ path: props.path, content: props.file?.content ?? '' });
const saveFile = () => edit.post(`/sites/${props.site.id}/files/save`, { preserveScroll: true });

const upload = useForm({ path: props.path, file: null });
function doUpload(e) {
    upload.file = e.target.files[0];
    if (upload.file) upload.post(`/sites/${props.site.id}/files/upload`, { preserveScroll: true, forceFormData: true, onFinish: () => (upload.file = null) });
}
function newFolder() {
    const name = prompt('New folder name:');
    if (name) router.post(`/sites/${props.site.id}/files/mkdir`, { path: props.path, name }, { preserveScroll: true });
}
const del = (p, name) => { if (confirm(`Delete ${name}?`)) router.delete(`/sites/${props.site.id}/files`, { data: { path: p }, preserveScroll: true }); };

const chmodTarget = ref(null);
const chmodForm = useForm({ path: '', mode: '' });
function openChmod(e) { chmodTarget.value = e; chmodForm.path = e.path; chmodForm.mode = e.perms; }
function applyChmod() { chmodForm.post(`/sites/${props.site.id}/files/chmod`, { preserveScroll: true, onSuccess: () => { chmodTarget.value = null; } }); }
const presets = ['644', '755', '600', '700', '775', '666'];
</script>

<template>
    <AppLayout active="sites" :title="site.domain" subtitle="Files">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px">
            <Link :href="`/sites/${site.id}`" style="font-size: 12.5px; color: var(--cp-mut); text-decoration: none; display: inline-flex; align-items: center; gap: 4px"><i class="ti ti-chevron-left" style="font-size: 15px" aria-hidden="true"></i>Site</Link>
            <div style="flex: 1; font-family: ui-monospace, monospace; font-size: 12.5px; color: var(--cp-mut)">/{{ path }}</div>
            <template v-if="entries && provisioned">
                <label style="font-size: 12px; color: var(--cp-mut); cursor: pointer; display: inline-flex; align-items: center; gap: 5px"><i class="ti ti-upload" style="font-size: 15px" aria-hidden="true"></i>Upload<input type="file" @change="doUpload" style="display: none"></label>
                <button type="button" @click="newFolder" style="font-size: 12px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 8px; padding: 6px 11px; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px"><i class="ti ti-folder-plus" style="font-size: 15px" aria-hidden="true"></i>New folder</button>
            </template>
        </div>

        <div v-if="!provisioned" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 40px; text-align: center; color: var(--cp-dim); font-size: 13px">
            <i class="ti ti-folder-off" style="font-size: 26px; display: block; margin-bottom: 10px" aria-hidden="true"></i>
            This site isn't provisioned on this node yet — files appear once it's deployed.
        </div>

        <div v-else-if="entries" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <Link v-if="parent !== null" :href="url(parent)" style="display: flex; align-items: center; gap: 10px; padding: 10px 15px; border-bottom: 1px solid var(--cp-ln); text-decoration: none; color: var(--cp-mut); font-size: 13px">
                <i class="ti ti-arrow-up" style="font-size: 16px" aria-hidden="true"></i>..
            </Link>
            <div v-for="(e, i) in entries" :key="e.name" :style="`display:flex;align-items:center;gap:10px;padding:10px 15px;font-size:13px;${i < entries.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <i class="ti" :class="e.type === 'dir' ? 'ti-folder' : 'ti-file'" :style="`font-size:16px;color:${e.type === 'dir' ? 'var(--cp-amb)' : 'var(--cp-dim)'}`" aria-hidden="true"></i>
                <Link :href="url(e.path)" style="flex: 1; text-decoration: none; color: var(--cp-ink); font-weight: 500">{{ e.name }}</Link>
                <span style="font-size: 11px; color: var(--cp-dim); width: 64px; text-align: right">{{ human(e.size) }}</span>
                <button type="button" @click="openChmod(e)" title="Change permissions" style="font-family: ui-monospace, monospace; font-size: 11px; color: var(--cp-mut); background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 6px; padding: 2px 7px; cursor: pointer">{{ e.perms }}</button>
                <button type="button" @click="del(e.path, e.name)" aria-label="Delete" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-trash" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
            <div v-if="!entries.length" style="padding: 30px; text-align: center; color: var(--cp-dim); font-size: 13px">Empty folder</div>
        </div>

        <div v-else-if="file" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div style="display: flex; align-items: center; gap: 10px; padding: 11px 15px; border-bottom: 1px solid var(--cp-ln)">
                <Link v-if="parent !== null" :href="url(parent)" style="color: var(--cp-mut); text-decoration: none"><i class="ti ti-arrow-up" style="font-size: 16px" aria-hidden="true"></i></Link>
                <i class="ti ti-file-text" style="font-size: 15px; color: var(--cp-vio)" aria-hidden="true"></i>
                <span style="flex: 1; font-family: ui-monospace, monospace; font-size: 12.5px; font-weight: 500">{{ file.name }}</span>
                <button v-if="file.editable" type="button" @click="saveFile" :disabled="edit.processing" style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 6px 14px; font-weight: 500; cursor: pointer; font-family: inherit">{{ edit.processing ? 'Saving…' : 'Save' }}</button>
            </div>
            <textarea v-if="file.editable" v-model="edit.content" spellcheck="false"
                style="width: 100%; box-sizing: border-box; min-height: 440px; border: 0; background: var(--cp-bg); color: var(--cp-ink); padding: 14px 16px; font-family: ui-monospace, Menlo, monospace; font-size: 12.5px; line-height: 1.6; resize: vertical; outline: none"></textarea>
            <div v-else style="padding: 40px; text-align: center; color: var(--cp-dim); font-size: 13px">File too large to edit in the browser ({{ human(file.size) }}).</div>
        </div>

        <div v-if="chmodTarget" @click.self="chmodTarget = null"
            style="position: fixed; inset: 0; z-index: 50; background: rgba(6,8,16,.55); display: flex; align-items: center; justify-content: center; padding: 20px">
            <div style="width: 360px; max-width: 100%; background: var(--cp-card); border: 1px solid var(--cp-ln2); border-radius: 14px; padding: 18px">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px">
                    <i class="ti ti-lock-cog" style="font-size: 17px; color: var(--cp-vio)" aria-hidden="true"></i>
                    <span style="font-size: 14px; font-weight: 600; flex: 1">Permissions</span>
                    <button type="button" @click="chmodTarget = null" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer"><i class="ti ti-x" style="font-size: 16px" aria-hidden="true"></i></button>
                </div>
                <div style="font-size: 12px; color: var(--cp-dim); font-family: ui-monospace, monospace; margin-bottom: 14px; word-break: break-all">{{ chmodTarget.name }}</div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px">
                    <input v-model="chmodForm.mode" maxlength="3" style="width: 88px; box-sizing: border-box; background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 9px; color: var(--cp-ink); padding: 9px 11px; font-size: 16px; font-family: ui-monospace, monospace; text-align: center; letter-spacing: 3px" />
                    <span style="font-size: 11.5px; color: var(--cp-dim)">octal — owner / group / others</span>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 16px">
                    <button v-for="p in presets" :key="p" type="button" @click="chmodForm.mode = p"
                        :style="`font-family:ui-monospace,monospace;font-size:12px;padding:5px 10px;border-radius:8px;cursor:pointer;border:1px solid var(--cp-ln);${chmodForm.mode === p ? 'background:var(--cp-ind);color:#fff;border-color:transparent' : 'background:var(--cp-card2);color:var(--cp-mut)'}`">{{ p }}</button>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 8px">
                    <button type="button" @click="chmodTarget = null" style="font-size: 12.5px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 14px; cursor: pointer; font-family: inherit">Cancel</button>
                    <button type="button" @click="applyChmod" :disabled="chmodForm.processing" style="font-size: 12.5px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 16px; font-weight: 500; cursor: pointer; font-family: inherit">Apply</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
