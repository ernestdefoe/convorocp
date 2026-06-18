<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ name: String, accent: String, logo: String, defaultName: String, defaultAccent: String });

const form = useForm({ name: props.name, accent: props.accent });
const save = () => form.post('/branding', { preserveScroll: true });

const logoInput = ref(null);
const logoForm = useForm({ logo: null });
function pickLogo(e) {
    logoForm.logo = e.target.files[0];
    if (logoForm.logo) logoForm.post('/branding/logo', { preserveScroll: true, forceFormData: true, onSuccess: () => router.reload({ only: ['logo'] }) });
}
const removeLogo = () => router.delete('/branding/logo', { preserveScroll: true });

const swatches = ['#5B5BD6', '#2563eb', '#0891b2', '#059669', '#d97706', '#dc2626', '#db2777', '#7c3aed'];
const field = 'box-sizing:border-box;width:100%;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
const card = 'background:var(--cp-card);border:1px solid var(--cp-ln);border-radius:13px;padding:18px 20px;margin-bottom:14px';
</script>

<template>
    <AppLayout active="branding" title="Branding" subtitle="White-label this panel">
        <div style="max-width: 560px">
            <!-- live preview -->
            <div :style="card + ';display:flex;align-items:center;gap:11px'">
                <img v-if="logo" :src="logo" alt="logo" style="width: 34px; height: 34px; border-radius: 9px; object-fit: contain" />
                <div v-else style="width: 34px; height: 34px; border-radius: 9px; background: var(--cp-ind); display: flex; align-items: center; justify-content: center"><i class="ti ti-sailboat" style="font-size: 19px; color: #fff" aria-hidden="true"></i></div>
                <span style="font-size: 18px; font-weight: 600; letter-spacing: -0.02em">{{ form.name || defaultName }}</span>
                <span style="margin-left: auto; font-size: 11px; color: var(--cp-dim)">live preview</span>
            </div>

            <!-- name + accent -->
            <form @submit.prevent="save" :style="card">
                <label style="display: block; font-size: 11.5px; color: var(--cp-mut); margin-bottom: 12px">Panel name
                    <input v-model="form.name" :style="field + ';margin-top:5px'" :placeholder="defaultName" maxlength="40" />
                </label>
                <div style="font-size: 11.5px; color: var(--cp-mut); margin-bottom: 8px">Accent colour</div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px">
                    <button v-for="s in swatches" :key="s" type="button" @click="form.accent = s"
                        :style="`width:26px;height:26px;border-radius:7px;cursor:pointer;background:${s};border:2px solid ${form.accent.toLowerCase() === s.toLowerCase() ? 'var(--cp-ink)' : 'transparent'}`" :aria-label="s"></button>
                    <input v-model="form.accent" :style="field + ';width:110px;font-family:ui-monospace,monospace;font-size:12px'" />
                    <input type="color" v-model="form.accent" style="width: 34px; height: 34px; border: 0; background: transparent; cursor: pointer" />
                </div>
                <p v-if="form.errors.accent" style="font-size: 11px; color: var(--cp-red); margin: 0 0 10px">{{ form.errors.accent }}</p>
                <div style="display: flex; justify-content: flex-end">
                    <button type="submit" :disabled="form.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 18px; cursor: pointer; font-family: inherit">Save</button>
                </div>
                <p style="font-size: 11px; color: var(--cp-dim); margin: 10px 0 0">Colour applies across the panel after saving (reload to see it everywhere).</p>
            </form>

            <!-- logo -->
            <div :style="card + ';margin-bottom:0'">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 4px">Logo</div>
                <div style="font-size: 11.5px; color: var(--cp-dim); margin-bottom: 12px">PNG, SVG, JPG or WebP up to 200&nbsp;KB. Replaces the mark in the sidebar and on the login screen.</div>
                <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" @change="pickLogo" style="display: none" />
                <div style="display: flex; gap: 8px; align-items: center">
                    <button type="button" @click="logoInput.click()" :disabled="logoForm.processing" style="font-size: 13px; color: var(--cp-ink); background: var(--cp-card2); border: 1px solid var(--cp-ln); border-radius: 9px; padding: 9px 16px; cursor: pointer; font-family: inherit">{{ logoForm.processing ? 'Uploading…' : (logo ? 'Replace logo' : 'Upload logo') }}</button>
                    <button v-if="logo" type="button" @click="removeLogo" style="font-size: 13px; color: var(--cp-red); background: transparent; border: 1px solid rgba(248,113,113,.4); border-radius: 9px; padding: 9px 16px; cursor: pointer; font-family: inherit">Remove</button>
                </div>
                <p v-if="logoForm.errors.logo" style="font-size: 11px; color: var(--cp-red); margin: 10px 0 0">{{ logoForm.errors.logo }}</p>
            </div>
        </div>
    </AppLayout>
</template>
