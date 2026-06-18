<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ twoFactorEnabled: Boolean });

const page = usePage();
const user = computed(() => page.props.auth?.user ?? { name: '', email: '' });
const status = computed(() => page.props.flash?.status);

const profile = useForm({ name: user.value.name, email: user.value.email });
function saveProfile() {
    profile.patch('/account/profile', { preserveScroll: true });
}

const pw = useForm({ current_password: '', password: '', password_confirmation: '' });
function savePassword() {
    pw.patch('/account/password', { preserveScroll: true, onSuccess: () => pw.reset() });
}

const card = 'background:var(--cp-card);border:1px solid var(--cp-ln);border-radius:13px;padding:16px 18px;margin-bottom:14px';
const field = 'box-sizing:border-box;width:100%;margin-top:6px;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
const label = 'display:block;font-size:11.5px;color:var(--cp-mut);font-weight:500';
const btn = 'font-size:12.5px;color:#fff;background:var(--cp-ind);border:0;border-radius:9px;padding:9px 16px;font-weight:500;cursor:pointer;font-family:inherit';
</script>

<template>
    <AppLayout active="account" title="Account" subtitle="Your profile, password and security">
        <div v-if="status" style="background: rgba(52,211,153,.14); border: 1px solid var(--cp-grn); color: var(--cp-grn); border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px; display: flex; align-items: center; gap: 8px">
            <i class="ti ti-circle-check" style="font-size: 16px" aria-hidden="true"></i>{{ status }}
        </div>

        <!-- Profile -->
        <div :style="card">
            <div style="font-size: 14px; font-weight: 600; margin-bottom: 14px">Profile</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px">
                <label :style="label">Name
                    <input v-model="profile.name" :style="field" />
                    <span v-if="profile.errors.name" style="color: var(--cp-red); font-size: 11.5px">{{ profile.errors.name }}</span>
                </label>
                <label :style="label">Email address
                    <input v-model="profile.email" type="email" autocomplete="email" :style="field" />
                    <span v-if="profile.errors.email" style="color: var(--cp-red); font-size: 11.5px">{{ profile.errors.email }}</span>
                </label>
            </div>
            <div style="margin-top: 16px"><button type="button" @click="saveProfile" :disabled="profile.processing" :style="btn">{{ profile.processing ? 'Saving…' : 'Save profile' }}</button></div>
        </div>

        <!-- Password -->
        <div :style="card">
            <div style="font-size: 14px; font-weight: 600; margin-bottom: 14px">Password</div>
            <label :style="label">Current password
                <input v-model="pw.current_password" type="password" autocomplete="current-password" :style="field + ';max-width:340px'" />
                <span v-if="pw.errors.current_password" style="color: var(--cp-red); font-size: 11.5px">{{ pw.errors.current_password }}</span>
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 14px; max-width: 520px">
                <label :style="label">New password
                    <input v-model="pw.password" type="password" autocomplete="new-password" :style="field" />
                    <span v-if="pw.errors.password" style="color: var(--cp-red); font-size: 11.5px">{{ pw.errors.password }}</span>
                </label>
                <label :style="label">Confirm new password
                    <input v-model="pw.password_confirmation" type="password" autocomplete="new-password" :style="field" />
                </label>
            </div>
            <div style="margin-top: 16px"><button type="button" @click="savePassword" :disabled="pw.processing" :style="btn">{{ pw.processing ? 'Saving…' : 'Change password' }}</button></div>
        </div>

        <!-- 2FA pointer -->
        <div :style="card + ';display:flex;align-items:center;gap:12px;margin-bottom:0'">
            <i class="ti ti-shield-lock" :style="`font-size:20px;color:${twoFactorEnabled ? 'var(--cp-grn)' : 'var(--cp-mut)'}`" aria-hidden="true"></i>
            <div style="flex: 1">
                <div style="font-size: 13px; font-weight: 600">Two-factor authentication</div>
                <div style="font-size: 11.5px; color: var(--cp-dim)">{{ twoFactorEnabled ? 'Enabled — manage it on the Security page.' : 'Not enabled. Add an authenticator app on the Security page.' }}</div>
            </div>
            <a href="/security" style="font-size: 12.5px; color: var(--cp-mut); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 13px; text-decoration: none">Security</a>
        </div>
    </AppLayout>
</template>
