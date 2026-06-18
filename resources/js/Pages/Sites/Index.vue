<script setup>
import { ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ sites: Array, phpVersions: Array });
const isOperator = usePage().props.auth?.user?.role === 'operator';

const showForm = ref(false);
const form = useForm({ domain: '', runtime: 'php', php_version: props.phpVersions[0] });
function create() {
    form.post('/sites', { onSuccess: () => { showForm.value = false; form.reset(); } });
}

const showAdopt = ref(false);
const adoptForm = useForm({ domain: '', path: '' });
function adopt() {
    adoptForm.post('/sites/adopt', { onSuccess: () => { showAdopt.value = false; adoptForm.reset(); } });
}

const statusColor = (s) => (s === 'active' ? 'var(--cp-grn)' : s === 'deploying' ? 'var(--cp-amb)' : 'var(--cp-dim)');
const field = 'box-sizing:border-box;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="sites" title="Sites" :subtitle="`${sites.length} site${sites.length === 1 ? '' : 's'}`">
        <template #actions>
            <button v-if="isOperator" type="button" @click="showAdopt = !showAdopt"
                style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-family: inherit; margin-right: 8px">
                <i class="ti ti-link" style="font-size: 14px" aria-hidden="true"></i>Adopt existing
            </button>
            <button type="button" @click="showForm = !showForm"
                style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit">
                <i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New site
            </button>
        </template>

        <form v-if="showAdopt" @submit.prevent="adopt" style="background: var(--cp-card); border: 1px solid var(--cp-ind); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap">
            <div style="flex-basis: 100%; font-size: 11.5px; color: var(--cp-dim); margin-bottom: 2px">Adopt an app ConvoroCP didn't create — it's symlinked + managed for safe ops only (no provisioning, no destructive actions).</div>
            <label style="flex: 1; min-width: 180px; font-size: 12px; color: var(--cp-mut)">Domain
                <input v-model="adoptForm.domain" :style="field + ';width:100%;margin-top:5px'" placeholder="convoro.co" />
            </label>
            <label style="flex: 1; min-width: 200px; font-size: 12px; color: var(--cp-mut)">Existing path
                <input v-model="adoptForm.path" :style="field + ';width:100%;margin-top:5px'" placeholder="/var/www/convoro" />
            </label>
            <button type="submit" :disabled="adoptForm.processing"
                style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Adopt</button>
        </form>
        <p v-if="adoptForm.errors.domain || adoptForm.errors.path" style="color: var(--cp-red); font-size: 12px; margin: -8px 0 12px">{{ adoptForm.errors.domain || adoptForm.errors.path }}</p>

        <form v-if="showForm" @submit.prevent="create" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap">
            <label style="flex: 1; min-width: 200px; font-size: 12px; color: var(--cp-mut)">Domain
                <input v-model="form.domain" :style="field + ';width:100%;margin-top:5px'" placeholder="example.com" />
            </label>
            <label style="font-size: 12px; color: var(--cp-mut)">Runtime
                <select v-model="form.runtime" :style="field + ';margin-top:5px;display:block'">
                    <option value="php">PHP</option>
                    <option value="node">Node</option>
                    <option value="static">Static</option>
                </select>
            </label>
            <label v-if="form.runtime === 'php'" style="font-size: 12px; color: var(--cp-mut)">PHP
                <select v-model="form.php_version" :style="field + ';margin-top:5px;display:block'">
                    <option v-for="v in phpVersions" :key="v" :value="v">{{ v }}</option>
                </select>
            </label>
            <button type="submit" :disabled="form.processing"
                style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Create</button>
        </form>
        <p v-if="form.errors.domain" style="color: var(--cp-red); font-size: 12px; margin: -8px 0 12px">{{ form.errors.domain }}</p>

        <div v-if="sites.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <Link v-for="(s, i) in sites" :key="s.id" :href="`/sites/${s.id}`"
                :style="`display:flex;align-items:center;gap:11px;padding:12px 15px;font-size:13px;text-decoration:none;color:var(--cp-ink);${i < sites.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <span :style="`width:8px;height:8px;border-radius:50%;background:${statusColor(s.status)}`"></span>
                <span style="font-weight: 500">{{ s.domain }}</span>
                <span v-if="s.adopted" style="font-size: 10px; font-weight: 600; letter-spacing: .03em; color: var(--cp-vio); background: rgba(91,91,214,.16); padding: 1px 7px; border-radius: 999px">ADOPTED</span>
                <span style="flex: 1"></span>
                <span v-if="isOperator && s.owner" style="font-size: 11.5px; color: var(--cp-dim)">{{ s.owner }}</span>
                <span style="font-size: 11px; padding: 2px 8px; border-radius: 999px; background: var(--cp-soft); color: var(--cp-mut)">{{ s.runtime === 'php' ? 'PHP ' + s.php_version : s.runtime }}</span>
                <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; width: 54px" :style="`color:${s.ssl_status === 'active' ? 'var(--cp-grn)' : 'var(--cp-amb)'}`">
                    <i class="ti ti-lock" style="font-size: 13px" aria-hidden="true"></i>{{ s.ssl_status === 'active' ? 'SSL' : 'pending' }}
                </span>
                <i class="ti ti-chevron-right" style="color: var(--cp-dim)" aria-hidden="true"></i>
            </Link>
        </div>
        <div v-else style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 40px; text-align: center; color: var(--cp-dim); font-size: 13px">
            No sites yet. Create your first one.
        </div>
    </AppLayout>
</template>
