<script setup>
import { ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ runtimes: Array, inis: { type: Array, default: () => [] }, systemVersion: String });

const install = (r) => router.post(`/php/${r.id}/install`, {}, { preserveScroll: true });
const uninstall = (r) => { if (confirm(`Remove PHP ${r.version}? Sites using it must be moved first.`)) router.post(`/php/${r.id}/uninstall`, {}, { preserveScroll: true }); };

const flash = () => usePage().props.flash?.status;
const editing = ref(null);
const iniForm = useForm({ version: '', content: '', confirm: false });
function edit(ini) {
    if (editing.value === ini.version) { editing.value = null; return; }
    editing.value = ini.version;
    iniForm.version = ini.version;
    iniForm.content = ini.content;
    iniForm.confirm = false;
}
function saveIni() {
    iniForm.post('/php/save-ini', { preserveScroll: true });
}

const badge = {
    installed: 'background:rgba(52,211,153,.16);color:var(--cp-grn)',
    available: 'background:var(--cp-soft);color:var(--cp-mut)',
    installing: 'background:rgba(251,191,36,.16);color:var(--cp-amb)',
    removing: 'background:rgba(248,113,113,.16);color:var(--cp-red)',
};
</script>

<template>
    <AppLayout active="php" title="PHP versions" subtitle="Install or remove PHP runtimes offered on this node">
        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div v-for="(r, i) in runtimes" :key="r.id"
                :style="`display:flex;align-items:center;gap:14px;padding:14px 16px;${i < runtimes.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <i class="ti ti-brand-php" style="font-size: 22px; color: var(--cp-vio)" aria-hidden="true"></i>
                <div style="flex: 1">
                    <div style="font-size: 14px; font-weight: 600">PHP {{ r.version }}</div>
                    <div style="font-size: 11.5px; color: var(--cp-dim)">php{{ r.version }}-fpm</div>
                </div>
                <span :style="`font-size:11px;font-weight:500;padding:3px 10px;border-radius:999px;text-transform:capitalize;${badge[r.status]}`">
                    {{ r.status === 'installing' ? 'Installing…' : r.status === 'removing' ? 'Removing…' : r.status }}
                </span>
                <button v-if="r.status === 'available'" type="button" @click="install(r)"
                    style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 14px; font-weight: 500; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px">
                    <i class="ti ti-download" style="font-size: 14px" aria-hidden="true"></i>Install
                </button>
                <button v-else-if="r.status === 'installed'" type="button" @click="uninstall(r)"
                    style="font-size: 12px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 14px; cursor: pointer; font-family: inherit">
                    Remove
                </button>
                <span v-else style="font-size: 12px; color: var(--cp-dim); width: 78px; text-align: center">working…</span>
            </div>
        </div>
        <p style="font-size: 12px; color: var(--cp-dim); margin-top: 14px; display: flex; align-items: center; gap: 7px">
            <i class="ti ti-info-circle" style="font-size: 15px" aria-hidden="true"></i>
            Installs run on the node in the background (apt) — refresh in a minute to see the status update.
        </p>

        <!-- php.ini editors -->
        <div v-if="inis.length" style="margin-top: 26px">
            <div style="font-size: 14px; font-weight: 600; margin-bottom: 4px">php.ini</div>
            <div style="font-size: 12px; color: var(--cp-dim); margin-bottom: 12px">Edit the live FPM php.ini directly. The agent reloads FPM and auto-reverts if it fails to start.</div>
            <div v-if="flash()" style="background: rgba(52,211,153,.14); border: 1px solid var(--cp-grn); color: var(--cp-grn); border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px">{{ flash() }}</div>

            <div v-for="ini in inis" :key="ini.version" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 12px 15px; margin-bottom: 12px">
                <div style="display: flex; align-items: center; gap: 10px">
                    <i class="ti ti-file-settings" style="font-size: 17px; color: var(--cp-vio)" aria-hidden="true"></i>
                    <div style="flex: 1">
                        <div style="font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 7px">PHP {{ ini.version }}<span v-if="ini.protected" style="font-size: 10px; font-weight: 500; color: var(--cp-amb); background: rgba(251,191,36,.16); padding: 2px 8px; border-radius: 999px; text-transform: uppercase; letter-spacing: .04em">System</span></div>
                        <div style="font-size: 11px; color: var(--cp-dim); font-family: ui-monospace, monospace">{{ ini.path }}</div>
                    </div>
                    <button type="button" @click="edit(ini)"
                        style="font-size: 12px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 8px; padding: 6px 13px; cursor: pointer; font-family: inherit">{{ editing === ini.version ? 'Close' : 'Edit' }}</button>
                </div>
                <div v-if="editing === ini.version" style="margin-top: 12px">
                    <div v-if="ini.protected" style="background: rgba(251,191,36,.1); border: 1px solid rgba(251,191,36,.35); border-radius: 10px; padding: 10px 13px; margin-bottom: 10px; font-size: 12px; color: var(--cp-amb); display: flex; gap: 8px">
                        <i class="ti ti-alert-triangle" style="font-size: 15px; flex: none; margin-top: 1px" aria-hidden="true"></i>
                        <span>This is the <strong>system PHP</strong>. Its php.ini is shared by every site on PHP {{ ini.version }} — including the panel and any live site on this box. A change here is global. To change PHP for one site only, use that site's <strong>PHP settings</strong> instead.</span>
                    </div>
                    <textarea v-model="iniForm.content" spellcheck="false" rows="20"
                        style="box-sizing: border-box; width: 100%; background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 9px; color: var(--cp-ink); padding: 11px 12px; font-size: 12px; font-family: ui-monospace, monospace; line-height: 1.5; resize: vertical"></textarea>
                    <p v-if="iniForm.errors.content" style="color: var(--cp-red); font-size: 12px; margin: 8px 0 0">{{ iniForm.errors.content }}</p>
                    <div style="margin-top: 10px; display: flex; align-items: center; justify-content: flex-end; gap: 14px">
                        <label v-if="ini.protected" style="font-size: 12px; color: var(--cp-amb); display: flex; align-items: center; gap: 7px; cursor: pointer; margin-right: auto"><input type="checkbox" v-model="iniForm.confirm" />I understand this changes PHP {{ ini.version }} for every site on this server</label>
                        <button type="button" @click="saveIni" :disabled="iniForm.processing || (ini.protected && !iniForm.confirm)"
                            :style="`font-size:12.5px;color:#fff;background:var(--cp-ind);border:0;border-radius:9px;padding:9px 18px;font-weight:500;font-family:inherit;${iniForm.processing || (ini.protected && !iniForm.confirm) ? 'opacity:.5;cursor:not-allowed' : 'cursor:pointer'}`">{{ iniForm.processing ? 'Saving…' : 'Save & reload FPM' }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
