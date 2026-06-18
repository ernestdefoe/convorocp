<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    current: String, repo: String, hasToken: Boolean,
    latest: Object, checkedAt: String, error: String, updating: Boolean,
});

const settingsOpen = ref(false);
const checkForm = useForm({});
const applyForm = useForm({});
const settings = useForm({ repo: props.repo, token: '', clear_token: false });

const check = () => checkForm.post('/updates/check', { preserveScroll: true });
const apply = () => { if (confirm('Update ConvoroCP now? The panel will briefly reload while the new version is applied.')) applyForm.post('/updates/apply', { preserveScroll: true }); };
const saveSettings = () => settings.post('/updates/settings', { preserveScroll: true, onSuccess: () => { settingsOpen.value = false; settings.token = ''; } });

const upToDate = () => props.latest && !props.latest.newer;
const field = 'box-sizing:border-box;width:100%;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="updates" title="Updates" subtitle="ConvoroCP version">
        <template #actions>
            <button type="button" @click="settingsOpen = !settingsOpen" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-family: inherit; margin-right: 8px"><i class="ti ti-settings" style="font-size: 14px" aria-hidden="true"></i>Source</button>
            <button type="button" @click="check" :disabled="checkForm.processing" style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit"><i class="ti ti-refresh" style="font-size: 14px" aria-hidden="true"></i>{{ checkForm.processing ? 'Checking…' : 'Check for updates' }}</button>
        </template>

        <!-- version card -->
        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 18px 20px; margin-bottom: 14px; display: flex; align-items: center; gap: 14px">
            <div style="width: 42px; height: 42px; border-radius: 11px; background: rgba(91,91,214,.16); display: flex; align-items: center; justify-content: center"><i class="ti ti-sailboat" style="font-size: 22px; color: var(--cp-vio)" aria-hidden="true"></i></div>
            <div style="flex: 1">
                <div style="font-size: 12px; color: var(--cp-dim)">Installed version</div>
                <div style="font-size: 20px; font-weight: 600; letter-spacing: -0.02em">ConvoroCP {{ current }}</div>
            </div>
            <span v-if="upToDate()" style="font-size: 12px; font-weight: 500; color: var(--cp-grn); display: inline-flex; align-items: center; gap: 5px"><i class="ti ti-circle-check" style="font-size: 16px" aria-hidden="true"></i>Up to date</span>
        </div>

        <div v-if="error" style="background: rgba(248,113,113,.1); border: 1px solid rgba(248,113,113,.3); border-radius: 11px; padding: 12px 15px; margin-bottom: 14px; font-size: 12.5px; color: var(--cp-red)">{{ error }}</div>

        <div v-if="updating" style="background: rgba(251,191,36,.1); border: 1px solid rgba(251,191,36,.3); border-radius: 11px; padding: 12px 15px; margin-bottom: 14px; font-size: 12.5px; color: var(--cp-amb); display: flex; align-items: center; gap: 8px"><i class="ti ti-loader" style="font-size: 15px" aria-hidden="true"></i>An update is being applied… this page will reflect the new version shortly.</div>

        <!-- available update -->
        <div v-if="latest && latest.newer" style="background: var(--cp-card); border: 1px solid var(--cp-ind); border-radius: 13px; padding: 18px 20px; margin-bottom: 14px">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px">
                <i class="ti ti-sparkles" style="font-size: 18px; color: var(--cp-vio)" aria-hidden="true"></i>
                <div style="flex: 1">
                    <div style="font-size: 14px; font-weight: 600">{{ latest.name }} available</div>
                    <div style="font-size: 11px; color: var(--cp-dim)">{{ latest.tag }}<span v-if="latest.date"> · {{ new Date(latest.date).toLocaleDateString() }}</span></div>
                </div>
                <button type="button" @click="apply" :disabled="applyForm.processing || updating" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 18px; font-weight: 500; cursor: pointer; font-family: inherit">{{ applyForm.processing ? 'Starting…' : 'Update now' }}</button>
            </div>
            <pre v-if="latest.notes" style="white-space: pre-wrap; font-family: inherit; font-size: 12px; color: var(--cp-mut); margin: 8px 0 0; max-height: 220px; overflow-y: auto; line-height: 1.5">{{ latest.notes }}</pre>
            <a v-if="latest.url" :href="latest.url" target="_blank" rel="noopener" style="font-size: 11.5px; color: var(--cp-vio); text-decoration: none; display: inline-block; margin-top: 8px">View release on GitHub →</a>
        </div>

        <div style="font-size: 11px; color: var(--cp-dim)">
            <span v-if="checkedAt">Last checked {{ new Date(checkedAt).toLocaleString() }}.</span>
            <span>Update source: <code style="color: var(--cp-mut)">{{ repo }}</code>{{ hasToken ? ' (token set)' : '' }}.</span>
        </div>

        <!-- source settings -->
        <form v-if="settingsOpen" @submit.prevent="saveSettings" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 16px 18px; margin-top: 14px">
            <div style="font-size: 13px; font-weight: 600; margin-bottom: 12px">Update source</div>
            <label style="font-size: 11.5px; color: var(--cp-mut); display: block; margin-bottom: 10px">Repository (owner/name)<input v-model="settings.repo" :style="field + ';margin-top:5px'" placeholder="ernestdefoe/convorocp" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut); display: block">GitHub token <span style="color: var(--cp-dim)">(only for private repos; leave blank to keep current)</span><input v-model="settings.token" type="password" :style="field + ';margin-top:5px'" :placeholder="hasToken ? '•••••••• (set)' : 'ghp_…'" /></label>
            <label v-if="hasToken" style="font-size: 11.5px; color: var(--cp-dim); display: flex; align-items: center; gap: 6px; margin-top: 10px"><input type="checkbox" v-model="settings.clear_token" />Remove stored token</label>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px">
                <button type="button" @click="settingsOpen = false" style="font-size: 13px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 9px; padding: 8px 14px; cursor: pointer; font-family: inherit">Cancel</button>
                <button type="submit" :disabled="settings.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 8px 18px; cursor: pointer; font-family: inherit">Save</button>
            </div>
        </form>
    </AppLayout>
</template>
