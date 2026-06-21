<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    current: String, repo: String, hasToken: Boolean,
    latest: Object, checkedAt: String, error: String, updating: Boolean,
    system: { type: Object, default: () => ({}) },
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

// ---- Server (OS / kernel) updates ----
const sys = computed(() => props.system || {});
const sum = computed(() => sys.value.summary || null);
const sysBusy = computed(() => !!(sys.value.checking || sys.value.upgrading || sys.value.rebooting));
const sysCheckForm = useForm({});
const sysUpgradeForm = useForm({ mode: 'all' });
const sysRebootForm = useForm({});

const sysCheck = () => sysCheckForm.post('/updates/system/check', { preserveScroll: true });
const sysUpgrade = (mode) => {
    const label = mode === 'security' ? 'security updates' : 'all available updates (including kernel)';
    if (confirm(`Install ${label} now? This runs apt on the server and may take a few minutes.`)) {
        sysUpgradeForm.transform(() => ({ mode })).post('/updates/system/upgrade', { preserveScroll: true });
    }
};
const sysReboot = () => { if (confirm('Reboot the server now? Every hosted site will be offline for ~1 minute while it restarts.')) sysRebootForm.post('/updates/system/reboot', { preserveScroll: true }); };

// Poll while any panel/system operation is in flight so the page reflects results.
let poll = null;
const anyBusy = () => props.updating || sysBusy.value;
onMounted(() => { poll = setInterval(() => { if (anyBusy()) router.reload({ only: ['system', 'updating', 'current'] }); }, 5000); });
onBeforeUnmount(() => poll && clearInterval(poll));
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

        <!-- ===== Server (OS / kernel) updates ===== -->
        <div style="margin-top: 26px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px">
            <i class="ti ti-server-cog" style="font-size: 18px; color: var(--cp-vio)" aria-hidden="true"></i>
            <h2 style="font-size: 14px; font-weight: 600; margin: 0; flex: 1">Server updates</h2>
            <button type="button" @click="sysReboot" :disabled="sysRebootForm.processing || sys.rebooting" title="Restart the whole server" style="font-size: 12px; color: var(--cp-red); background: var(--cp-card); border: 1px solid var(--cp-red); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-family: inherit"><i class="ti ti-rotate-clockwise-2" style="font-size: 14px" aria-hidden="true"></i>{{ sys.rebooting ? 'Rebooting…' : 'Reboot server' }}</button>
            <button type="button" @click="sysCheck" :disabled="sysCheckForm.processing || sys.checking" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-family: inherit"><i class="ti ti-refresh" style="font-size: 14px" aria-hidden="true"></i>{{ sys.checking ? 'Checking…' : 'Check now' }}</button>
        </div>

        <div v-if="sys.error" style="background: rgba(248,113,113,.1); border: 1px solid rgba(248,113,113,.3); border-radius: 11px; padding: 12px 15px; margin-bottom: 14px; font-size: 12.5px; color: var(--cp-red)">{{ sys.error }}</div>

        <div v-if="sys.checking || sys.upgrading" style="background: rgba(251,191,36,.1); border: 1px solid rgba(251,191,36,.3); border-radius: 11px; padding: 12px 15px; margin-bottom: 14px; font-size: 12.5px; color: var(--cp-amb); display: flex; align-items: center; gap: 8px"><i class="ti ti-loader" style="font-size: 15px" aria-hidden="true"></i>{{ sys.upgrading ? 'Installing system updates… this can take a few minutes.' : 'Checking for OS package updates…' }}</div>

        <!-- reboot required (kernel) -->
        <div v-if="sum && sum.reboot_required" style="background: rgba(248,113,113,.08); border: 1px solid rgba(248,113,113,.35); border-radius: 13px; padding: 16px 18px; margin-bottom: 14px; display: flex; align-items: center; gap: 14px">
            <i class="ti ti-rotate-clockwise-2" style="font-size: 22px; color: var(--cp-red)" aria-hidden="true"></i>
            <div style="flex: 1">
                <div style="font-size: 13.5px; font-weight: 600">Reboot required</div>
                <div style="font-size: 11.5px; color: var(--cp-dim)">A kernel or core library was updated<span v-if="sum.reboot_pkgs && sum.reboot_pkgs.length"> ({{ sum.reboot_pkgs.join(', ') }})</span>. Reboot to finish applying it.</div>
            </div>
            <button type="button" @click="sysReboot" :disabled="sysRebootForm.processing || sys.rebooting" style="font-size: 12.5px; color: #fff; background: var(--cp-red); border: 0; border-radius: 9px; padding: 9px 16px; font-weight: 500; cursor: pointer; font-family: inherit">{{ sys.rebooting ? 'Rebooting…' : 'Reboot now' }}</button>
        </div>

        <!-- packages summary -->
        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 18px 20px">
            <div style="display: flex; align-items: center; gap: 14px">
                <div style="width: 42px; height: 42px; border-radius: 11px; background: rgba(91,91,214,.16); display: flex; align-items: center; justify-content: center"><i class="ti ti-package" style="font-size: 22px; color: var(--cp-vio)" aria-hidden="true"></i></div>
                <div style="flex: 1">
                    <div style="font-size: 12px; color: var(--cp-dim)">OS packages</div>
                    <div v-if="sum" style="font-size: 18px; font-weight: 600; letter-spacing: -0.02em">
                        <span v-if="sum.count">{{ sum.count }} update{{ sum.count === 1 ? '' : 's' }} available</span>
                        <span v-else style="color: var(--cp-grn)">Up to date</span>
                    </div>
                    <div v-else style="font-size: 14px; color: var(--cp-mut)">Not checked yet</div>
                    <div v-if="sum && sum.security" style="font-size: 11.5px; color: var(--cp-amb); margin-top: 2px"><i class="ti ti-shield-exclamation" style="font-size: 13px; vertical-align: -1px" aria-hidden="true"></i> {{ sum.security }} security update{{ sum.security === 1 ? '' : 's' }}</div>
                </div>
                <div v-if="sum && sum.count" style="display: flex; gap: 8px">
                    <button v-if="sum.security" type="button" @click="sysUpgrade('security')" :disabled="sys.upgrading" style="font-size: 12.5px; color: var(--cp-amb); background: transparent; border: 1px solid var(--cp-amb); border-radius: 9px; padding: 9px 14px; font-weight: 500; cursor: pointer; font-family: inherit">Security only</button>
                    <button type="button" @click="sysUpgrade('all')" :disabled="sys.upgrading" style="font-size: 12.5px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 16px; font-weight: 500; cursor: pointer; font-family: inherit">{{ sys.upgrading ? 'Installing…' : 'Install all updates' }}</button>
                </div>
            </div>

            <!-- package list -->
            <div v-if="sum && sum.packages && sum.packages.length" style="margin-top: 14px; border-top: 1px solid var(--cp-ln); padding-top: 12px; max-height: 260px; overflow-y: auto">
                <div v-for="p in sum.packages" :key="p.name" style="display: flex; align-items: center; gap: 8px; font-size: 12px; padding: 4px 0">
                    <span :style="`width:6px;height:6px;border-radius:999px;flex:none;${p.security ? 'background:var(--cp-amb)' : 'background:var(--cp-ln)'}`"></span>
                    <span style="font-family: ui-monospace, monospace; color: var(--cp-ink)">{{ p.name }}</span>
                    <span style="color: var(--cp-dim)">{{ p.from }} → {{ p.to }}</span>
                    <span v-if="p.security" style="margin-left: auto; font-size: 10px; color: var(--cp-amb); text-transform: uppercase; letter-spacing: .04em">security</span>
                </div>
            </div>

            <div style="font-size: 11px; color: var(--cp-dim); margin-top: 12px">
                <span v-if="sum && sum.kernel">Running kernel <code style="color: var(--cp-mut)">{{ sum.kernel }}</code>. </span>
                <span v-if="sys.checkedAt">Last checked {{ new Date(sys.checkedAt).toLocaleString() }}.</span>
                <span v-else>Run a check to see available OS and kernel updates.</span>
            </div>
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
