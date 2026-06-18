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
// Per-app accent so each card reads at a glance.
const accents = { wordpress: '#3858e9', convoro: '#5b5bd6', flarum: '#e8590c', phpmyadmin: '#f59e0b', static: '#0891b2' };
const accent = (k) => accents[k] || '#5b5bd6';
const field = 'box-sizing:border-box;width:100%;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="apps" title="App Installer" subtitle="One-click applications">
        <div v-if="!sites.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 16px; margin-bottom: 16px; font-size: 12.5px; color: var(--cp-dim)">
            Create a site first — apps install onto one of your sites.
        </div>

        <!-- catalog -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(248px, 1fr)); gap: 14px; margin-bottom: 26px">
            <div v-for="a in catalog" :key="a.key" class="app-card" :class="{ featured: a.featured }" :style="`--acc:${accent(a.key)}`">
                <span v-if="a.featured" class="app-badge"><i class="ti ti-star-filled" style="font-size: 10px" aria-hidden="true"></i>FEATURED</span>
                <div class="app-icon"><i class="ti" :class="a.icon" aria-hidden="true"></i></div>
                <div class="app-name">{{ a.name }}</div>
                <div class="app-desc">{{ a.desc }}</div>
                <button type="button" class="app-btn" :disabled="!sites.length" @click="choose(a)">
                    <i class="ti ti-download" style="font-size: 14px" aria-hidden="true"></i>Install
                </button>
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
                <div style="display: flex; align-items: center; gap: 11px; margin-bottom: 14px">
                    <div :style="`width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:${accent(picked.key)}1f`"><i class="ti" :class="picked.icon" :style="`font-size:22px;color:${accent(picked.key)}`" aria-hidden="true"></i></div>
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

<style scoped>
.app-card {
    position: relative;
    display: flex;
    flex-direction: column;
    background: var(--cp-card);
    border: 1px solid var(--cp-ln);
    border-radius: 15px;
    padding: 18px;
    transition: transform .14s ease, border-color .14s ease, box-shadow .14s ease;
}
.app-card:hover {
    transform: translateY(-3px);
    border-color: var(--acc);
    box-shadow: 0 10px 28px rgba(0, 0, 0, .28);
}
.app-card.featured {
    border-color: color-mix(in srgb, var(--acc) 55%, var(--cp-ln));
    background:
        radial-gradient(120% 80% at 100% 0%, color-mix(in srgb, var(--acc) 14%, transparent), transparent 60%),
        var(--cp-card);
}
.app-icon {
    width: 46px;
    height: 46px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--acc) 16%, transparent);
    margin-bottom: 13px;
}
.app-icon .ti {
    font-size: 24px;
    color: var(--acc);
}
.app-name {
    font-size: 15px;
    font-weight: 600;
    letter-spacing: -0.01em;
}
.app-desc {
    font-size: 12px;
    color: var(--cp-dim);
    line-height: 1.5;
    margin-top: 4px;
    flex: 1;
}
.app-btn {
    margin-top: 16px;
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    color: var(--acc);
    background: color-mix(in srgb, var(--acc) 14%, transparent);
    border: 1px solid color-mix(in srgb, var(--acc) 35%, transparent);
    border-radius: 10px;
    padding: 9px;
    cursor: pointer;
    transition: background .14s ease, color .14s ease;
}
.app-btn:hover:not(:disabled) {
    background: var(--acc);
    color: #fff;
}
.app-btn:disabled {
    opacity: .45;
    cursor: not-allowed;
}
.app-badge {
    position: absolute;
    top: 14px;
    right: 14px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #fff;
    background: var(--acc);
    padding: 3px 8px;
    border-radius: 999px;
}
</style>
