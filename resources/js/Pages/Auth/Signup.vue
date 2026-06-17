<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ plans: Array });
const form = useForm({ name: '', email: '', password: '', password_confirmation: '', plan_id: props.plans[0]?.id ?? null });
function submit() {
    form.post('/signup', { onFinish: () => form.reset('password', 'password_confirmation') });
}
const field = 'box-sizing:border-box;width:100%;margin-top:6px;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:10px 12px;font-size:14px;font-family:inherit';
</script>

<template>
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: var(--cp-bg)">
        <div style="width: 560px; max-width: 100%">
            <div style="display: flex; align-items: center; gap: 11px; justify-content: center; margin-bottom: 22px">
                <div style="width: 34px; height: 34px; border-radius: 10px; background: var(--cp-ind); display: flex; align-items: center; justify-content: center">
                    <i class="ti ti-sailboat" style="font-size: 20px; color: #fff" aria-hidden="true"></i>
                </div>
                <span style="font-size: 20px; font-weight: 600; letter-spacing: -0.02em">ConvoroCP</span>
            </div>
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 16px; padding: 24px">
                <div style="font-size: 16px; font-weight: 600; margin-bottom: 16px">Create your hosting account</div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 18px">
                    <button v-for="p in plans" :key="p.id" type="button" @click="form.plan_id = p.id"
                        :style="`text-align:left;padding:13px;border-radius:12px;cursor:pointer;font-family:inherit;background:var(--cp-card2);${form.plan_id === p.id ? 'border:2px solid var(--cp-ind)' : 'border:1px solid var(--cp-ln)'}`">
                        <div style="font-size: 13px; font-weight: 600; color: var(--cp-ink)">{{ p.name }}</div>
                        <div style="font-size: 18px; font-weight: 600; color: var(--cp-ink); margin: 2px 0">{{ p.price }}</div>
                        <div style="font-size: 11px; color: var(--cp-dim); line-height: 1.5">{{ p.sites }} site{{ p.sites === 1 ? '' : 's' }}<br>{{ p.databases }} databases<br>{{ p.disk }} disk</div>
                    </button>
                </div>

                <form @submit.prevent="submit">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px">
                        <label style="font-size: 12.5px; color: var(--cp-mut); font-weight: 500">Name<input v-model="form.name" :style="field" /></label>
                        <label style="font-size: 12.5px; color: var(--cp-mut); font-weight: 500">Email<input v-model="form.email" type="email" :style="field" /></label>
                    </div>
                    <p v-if="form.errors.email" style="color: var(--cp-red); font-size: 12px; margin: 6px 0 0">{{ form.errors.email }}</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px">
                        <label style="font-size: 12.5px; color: var(--cp-mut); font-weight: 500">Password<input v-model="form.password" type="password" :style="field" /></label>
                        <label style="font-size: 12.5px; color: var(--cp-mut); font-weight: 500">Confirm<input v-model="form.password_confirmation" type="password" :style="field" /></label>
                    </div>
                    <p v-if="form.errors.password" style="color: var(--cp-red); font-size: 12px; margin: 6px 0 0">{{ form.errors.password }}</p>
                    <button type="submit" :disabled="form.processing"
                        style="width: 100%; margin-top: 18px; background: var(--cp-ind); color: #fff; border: 0; border-radius: 10px; padding: 11px; font-size: 14px; font-weight: 500; cursor: pointer; font-family: inherit">
                        {{ form.processing ? 'Creating…' : 'Create account' }}
                    </button>
                </form>
            </div>
            <p style="text-align: center; font-size: 12px; color: var(--cp-mut); margin-top: 16px">
                Already have an account? <a href="/login" style="color: var(--cp-vio); text-decoration: none; font-weight: 500">Sign in</a>
            </p>
        </div>
    </div>
</template>
