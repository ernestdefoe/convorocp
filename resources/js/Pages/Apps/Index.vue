<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ catalog: Array, sites: Array, installs: Array });

const picked = ref(null);
const form = useForm({ app: '', domain: '' });

function choose(app) {
    if (!props.sites.length) return;
    picked.value = app;
    form.app = app.key;
    form.domain = props.sites[0];
}
function install() {
    form.post('/apps/install', { onSuccess: () => { picked.value = null; form.reset(); } });
}
const remove = (i) => { if (confirm('Remove this install record? (Files on the site are left in place.)')) router.delete(`/apps/${i.id}`, { preserveScroll: true }); };

const statusTone = { done: 'var(--cp-grn)', failed: 'var(--cp-red)', pending: 'var(--cp-amb)' };
const field = 'box-sizing:border-box;width:100%;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="apps" title="App Installer" subtitle="One-click applications">
        <div v-if="!sites.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 16px; margin-bottom: 16px; font-size: 12.5px; color: var(--cp-dim)">
            Create a site first — apps install onto one of your sites.
        </div>

        <!-- catalog -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; margin-bottom: 22px">
            <div v-for="a in catalog" :key="a.key"
                :style="`position:relative;background:var(--cp-card);border:1px solid ${a.featured ? 'var(--cp-ind)' : 'var(--cp-ln)'};border-radius:13px;padding:16px`">
                <span v-if="a.featured" style="position: absolute; top: 12px; right: 12px; font-size: 9.5px; font-weight: 700; letter-spacing: 0.04em; color: #fff; background: var(--cp-ind); padding: 2px 8px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px"><i class="ti ti-star-filled" style="font-size: 10px" aria-hidden="true"></i>FEATURED</span>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 9px">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(91,91,214,.14); display: flex; align-items: center; justify-content: center"><i class="ti" :class="a.icon" style="font-size: 20px; color: var(--cp-vio)" aria-hidden="true"></i></div>
                    <div style="font-size: 14px; font-weight: 600">{{ a.name }}</div>
                </div>
                <div style="font-size: 11.5px; color: var(--cp-dim); line-height: 1.45; min-height: 34px">{{ a.desc }}</div>
                <button type="button" :disabled="!sites.length" @click="choose(a)"
                    :style="`margin-top:12px;width:100%;font-size:12.5px;color:#fff;background:var(--cp-ind);border:0;border-radius:9px;padding:8px;cursor:pointer;font-family:inherit;${!sites.length ? 'opacity:.5;cursor:not-allowed' : ''}`">Install</button>
            </div>
        </div>

        <!-- recent installs -->
        <div v-if="installs.length" style="font-size: 13px; font-weight: 600; margin-bottom: 12px">Recent installs</div>
        <div v-if="installs.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div v-for="(i, idx) in installs" :key="i.id" :style="`display:flex;align-items:center;gap:12px;padding:11px 15px;font-size:13px;${idx < installs.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <span :style="`width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${statusTone[i.status]}`"></span>
                <div style="flex: 1; min-width: 0">
                    <div style="font-weight: 500">{{ i.app }} <span style="color: var(--cp-dim); font-weight: 400">on {{ i.domain }}</span></div>
                    <div style="font-size: 11px; color: var(--cp-dim)">{{ i.info || (i.status === 'pending' ? 'installing…' : i.status) }} · {{ i.created }}</div>
                </div>
                <span :style="`font-size:10.5px;font-weight:600;text-transform:capitalize;color:${statusTone[i.status]}`">{{ i.status === 'pending' ? 'installing' : i.status }}</span>
                <button type="button" @click="remove(i)" aria-label="Remove" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-x" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
        </div>

        <!-- install modal -->
        <div v-if="picked" @click.self="picked = null" style="position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 50">
            <form @submit.prevent="install" style="background: var(--cp-side); border: 1px solid var(--cp-ln); border-radius: 14px; padding: 20px; width: 420px; max-width: 92vw">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px">
                    <i class="ti" :class="picked.icon" style="font-size: 22px; color: var(--cp-vio)" aria-hidden="true"></i>
                    <div style="font-size: 15px; font-weight: 600">Install {{ picked.name }}</div>
                </div>
                <label style="font-size: 11.5px; color: var(--cp-mut)">Install onto site
                    <select v-model="form.domain" :style="field + ';margin-top:5px;display:block'"><option v-for="s in sites" :key="s" :value="s">{{ s }}</option></select>
                </label>
                <p v-if="picked.db" style="font-size: 11px; color: var(--cp-dim); margin: 10px 0 0">A dedicated database will be created automatically.</p>
                <p style="font-size: 11px; color: var(--cp-amb); margin: 8px 0 0">This installs into the site root and overwrites the placeholder page.</p>
                <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px">
                    <button type="button" @click="picked = null" style="font-size: 13px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 9px; padding: 8px 14px; cursor: pointer; font-family: inherit">Cancel</button>
                    <button type="submit" :disabled="form.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 8px 18px; cursor: pointer; font-family: inherit">{{ form.processing ? 'Starting…' : 'Install' }}</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
