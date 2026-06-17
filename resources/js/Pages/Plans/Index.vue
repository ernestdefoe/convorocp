<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ plans: Array });

const showForm = ref(false);
const form = useForm({ name: '', price: 12, sites_limit: 1, db_limit: 1, email_limit: 5, disk_mb: 5120, is_public: true });
function create() {
    form.transform((d) => ({ ...d, price_cents: Math.round(d.price * 100) }))
        .post('/plans', { onSuccess: () => { showForm.value = false; form.reset(); } });
}
const destroy = (p) => { if (confirm(`Delete plan "${p.name}"? Subscribers keep their current limits.`)) router.delete(`/plans/${p.id}`); };
const dollars = (c) => (c === 0 ? 'Free' : '$' + (c / 100).toFixed(0) + '/mo');
const field = 'box-sizing:border-box;width:100%;margin-top:5px;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="plans" title="Plans" :subtitle="`${plans.length} plan${plans.length === 1 ? '' : 's'}`">
        <template #actions>
            <button type="button" @click="showForm = !showForm"
                style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit">
                <i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New plan
            </button>
        </template>

        <form v-if="showForm" @submit.prevent="create" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: grid; grid-template-columns: 1.4fr repeat(4, 0.8fr) auto; gap: 10px; align-items: end">
            <label style="font-size: 11.5px; color: var(--cp-mut)">Name<input v-model="form.name" :style="field" placeholder="Pro" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Price $<input v-model.number="form.price" type="number" min="0" :style="field" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Sites<input v-model.number="form.sites_limit" type="number" min="1" :style="field" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">DBs<input v-model.number="form.db_limit" type="number" min="0" :style="field" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Disk MB<input v-model.number="form.disk_mb" type="number" min="128" :style="field" /></label>
            <button type="submit" :disabled="form.processing" style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 14px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Create</button>
        </form>

        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div style="display: grid; grid-template-columns: 1.4fr 90px 1fr 90px 32px; gap: 10px; padding: 9px 15px; border-bottom: 1px solid var(--cp-ln); font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: var(--cp-dim)">
                <span>Plan</span><span>Price</span><span>Limits</span><span>Subscribers</span><span></span>
            </div>
            <div v-for="(p, i) in plans" :key="p.id"
                :style="`display:grid;grid-template-columns:1.4fr 90px 1fr 90px 32px;gap:10px;align-items:center;padding:11px 15px;font-size:12.5px;${i < plans.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <div style="display: flex; align-items: center; gap: 8px">
                    <span style="font-weight: 500">{{ p.name }}</span>
                    <span v-if="!p.is_public" style="font-size: 10px; padding: 1px 7px; border-radius: 999px; background: var(--cp-soft); color: var(--cp-dim)">hidden</span>
                </div>
                <span>{{ dollars(p.price_cents) }}</span>
                <span style="color: var(--cp-mut)">{{ p.sites_limit }} sites · {{ p.db_limit }} db · {{ Math.round(p.disk_mb / 1024) }} GB</span>
                <span style="color: var(--cp-mut)">{{ p.subscribers }}</span>
                <button type="button" @click="destroy(p)" aria-label="Delete plan" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-trash" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
        </div>
    </AppLayout>
</template>
