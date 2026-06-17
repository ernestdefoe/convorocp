<script setup>
import { useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ site: Object, phpVersions: Array, disableableFunctions: { type: Array, default: () => [] } });

const php = useForm({ php_version: props.site.php_version });
function changePhp() {
    php.patch(`/sites/${props.site.id}/php`, { preserveScroll: true });
}

const s = props.site.php_settings || {};
const ini = useForm({
    memory_limit: s.memory_limit ?? '256M',
    upload_max_filesize: s.upload_max_filesize ?? '64M',
    post_max_size: s.post_max_size ?? '64M',
    max_execution_time: s.max_execution_time ?? 30,
    display_errors: !!s.display_errors,
    disable_functions: Array.isArray(s.disable_functions) ? [...s.disable_functions] : [],
});
function saveIni() {
    ini.patch(`/sites/${props.site.id}/php-settings`, { preserveScroll: true });
}
function toggleFn(fn) {
    const i = ini.disable_functions.indexOf(fn);
    if (i === -1) ini.disable_functions.push(fn); else ini.disable_functions.splice(i, 1);
}
function destroy() {
    if (confirm(`Delete ${props.site.domain}? This cannot be undone.`)) {
        router.delete(`/sites/${props.site.id}`);
    }
}
const field = 'box-sizing:border-box;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="sites" :title="site.domain" subtitle="Site">
        <template #actions>
            <a :href="`https://${site.domain}`" target="_blank" rel="noopener"
                style="font-size: 12px; color: #fff; background: var(--cp-ind); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; text-decoration: none">
                <i class="ti ti-external-link" style="font-size: 14px" aria-hidden="true"></i>Visit
            </a>
        </template>

        <Link href="/sites" style="display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; color: var(--cp-mut); text-decoration: none; margin-bottom: 14px">
            <i class="ti ti-chevron-left" style="font-size: 15px" aria-hidden="true"></i>All sites
        </Link>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px">
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 12px">Runtime</div>
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 9px"><span style="color: var(--cp-mut)">Type</span><span style="text-transform: capitalize">{{ site.runtime }}</span></div>
                <div v-if="site.runtime === 'php'">
                    <label style="font-size: 12px; color: var(--cp-mut)">PHP version</label>
                    <select v-model="php.php_version" @change="changePhp" :style="field + ';width:100%;margin-top:5px;display:block'">
                        <option v-for="v in phpVersions" :key="v" :value="v">PHP {{ v }}</option>
                    </select>
                    <p style="font-size: 11px; color: var(--cp-dim); margin: 7px 0 0">Switches this site's FPM pool — applied by the agent.</p>
                </div>
            </div>
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px">
                    <i class="ti ti-certificate" style="color: var(--cp-grn); font-size: 17px" aria-hidden="true"></i>
                    <span style="font-size: 13px; font-weight: 600; flex: 1">SSL</span>
                    <span :style="`font-size:10.5px;font-weight:500;padding:2px 9px;border-radius:999px;${site.ssl_status === 'active' ? 'background:rgba(52,211,153,.16);color:var(--cp-grn)' : 'background:rgba(251,191,36,.16);color:var(--cp-amb)'}`">{{ site.ssl_status }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 9px"><span style="color: var(--cp-mut)">Status</span><span style="text-transform: capitalize">{{ site.status }}</span></div>
                <div style="display: flex; justify-content: space-between; font-size: 12.5px"><span style="color: var(--cp-mut)">Auto-deploy</span><span>{{ site.auto_deploy ? 'On' : 'Off' }}</span></div>
            </div>
        </div>

        <div v-if="site.runtime === 'php'" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 16px; margin-bottom: 14px">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px">
                <i class="ti ti-adjustments" style="color: var(--cp-vio); font-size: 17px" aria-hidden="true"></i>
                <span style="font-size: 13px; font-weight: 600; flex: 1">PHP settings</span>
                <button type="button" @click="saveIni" :disabled="ini.processing"
                    style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 6px 14px; font-weight: 500; cursor: pointer; font-family: inherit">{{ ini.processing ? 'Saving…' : 'Save' }}</button>
            </div>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px">
                <label style="font-size: 11.5px; color: var(--cp-mut)">Memory limit<input v-model="ini.memory_limit" :style="field + ';width:100%;margin-top:5px'" /></label>
                <label style="font-size: 11.5px; color: var(--cp-mut)">Upload max<input v-model="ini.upload_max_filesize" :style="field + ';width:100%;margin-top:5px'" /></label>
                <label style="font-size: 11.5px; color: var(--cp-mut)">POST max<input v-model="ini.post_max_size" :style="field + ';width:100%;margin-top:5px'" /></label>
                <label style="font-size: 11.5px; color: var(--cp-mut)">Max exec (s)<input v-model.number="ini.max_execution_time" type="number" :style="field + ';width:100%;margin-top:5px'" /></label>
            </div>
            <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--cp-mut); margin-top: 14px">
                <input type="checkbox" v-model="ini.display_errors" /> Display errors (development)
            </label>
            <div style="font-size: 11.5px; color: var(--cp-mut); margin: 16px 0 8px">Disabled functions <span style="color: var(--cp-dim)">— check to block</span></div>
            <div style="display: flex; flex-wrap: wrap; gap: 8px">
                <button v-for="fn in disableableFunctions" :key="fn" type="button" @click="toggleFn(fn)"
                    :style="`font-size:11.5px;font-family:ui-monospace,monospace;padding:5px 11px;border-radius:8px;cursor:pointer;border:1px solid var(--cp-ln);${ini.disable_functions.includes(fn) ? 'background:rgba(248,113,113,.16);color:var(--cp-red);border-color:transparent' : 'background:var(--cp-card2);color:var(--cp-mut)'}`">
                    <i class="ti" :class="ini.disable_functions.includes(fn) ? 'ti-ban' : 'ti-circle'" style="font-size: 12px; vertical-align: -1px; margin-right: 4px" aria-hidden="true"></i>{{ fn }}
                </button>
            </div>
        </div>

        <div style="background: var(--cp-card); border: 1px solid var(--cp-red); border-radius: 13px; padding: 14px 15px; display: flex; align-items: center; gap: 12px">
            <div style="flex: 1">
                <div style="font-size: 13px; font-weight: 600">Delete site</div>
                <div style="font-size: 11.5px; color: var(--cp-dim)">Removes the vhost, pool and files. Cannot be undone.</div>
            </div>
            <button type="button" @click="destroy"
                style="font-size: 12.5px; color: var(--cp-red); background: transparent; border: 1px solid var(--cp-red); border-radius: 8px; padding: 7px 13px; cursor: pointer; font-family: inherit">Delete</button>
        </div>
    </AppLayout>
</template>
