<script setup>
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ site: Object, path: String, parent: { type: String, default: null }, entries: { type: Array, default: null }, file: { type: Object, default: null } });

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
</script>

<template>
    <AppLayout active="sites" :title="site.domain" subtitle="Files">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px">
            <Link :href="`/sites/${site.id}`" style="font-size: 12.5px; color: var(--cp-mut); text-decoration: none; display: inline-flex; align-items: center; gap: 4px"><i class="ti ti-chevron-left" style="font-size: 15px" aria-hidden="true"></i>Site</Link>
            <div style="flex: 1; font-family: ui-monospace, monospace; font-size: 12.5px; color: var(--cp-mut)">/{{ path }}</div>
            <template v-if="entries">
                <label style="font-size: 12px; color: var(--cp-mut); cursor: pointer; display: inline-flex; align-items: center; gap: 5px"><i class="ti ti-upload" style="font-size: 15px" aria-hidden="true"></i>Upload<input type="file" @change="doUpload" style="display: none"></label>
                <button type="button" @click="newFolder" style="font-size: 12px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 8px; padding: 6px 11px; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px"><i class="ti ti-folder-plus" style="font-size: 15px" aria-hidden="true"></i>New folder</button>
            </template>
        </div>

        <div v-if="entries" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <Link v-if="parent !== null" :href="url(parent)" style="display: flex; align-items: center; gap: 10px; padding: 10px 15px; border-bottom: 1px solid var(--cp-ln); text-decoration: none; color: var(--cp-mut); font-size: 13px">
                <i class="ti ti-arrow-up" style="font-size: 16px" aria-hidden="true"></i>..
            </Link>
            <div v-for="(e, i) in entries" :key="e.name" :style="`display:flex;align-items:center;gap:10px;padding:10px 15px;font-size:13px;${i < entries.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <i class="ti" :class="e.type === 'dir' ? 'ti-folder' : 'ti-file'" :style="`font-size:16px;color:${e.type === 'dir' ? 'var(--cp-amb)' : 'var(--cp-dim)'}`" aria-hidden="true"></i>
                <Link :href="url(e.path)" style="flex: 1; text-decoration: none; color: var(--cp-ink); font-weight: 500">{{ e.name }}</Link>
                <span style="font-size: 11px; color: var(--cp-dim); width: 70px; text-align: right">{{ human(e.size) }}</span>
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
    </AppLayout>
</template>
