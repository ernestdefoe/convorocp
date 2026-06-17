<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

defineProps({ plan: Object, usage: Array, sites: Array, invoice: Object });

const firstName = computed(() => (usePage().props.auth?.user?.name || 'there').split(' ')[0]);
const tone = (t) => ({ ind: 'var(--cp-ind)', cy: 'var(--cp-cy)', vio: 'var(--cp-vio)' }[t] || 'var(--cp-ind)');
</script>

<template>
    <AppLayout variant="client" active="home" :title="`Welcome back, ${firstName}`" subtitle="Everything's running smoothly.">
        <template #actions>
            <span style="font-size: 11px; font-weight: 500; padding: 4px 11px; border-radius: 999px; background: rgba(91,91,214,.14); color: var(--cp-vio)">{{ plan.name }}</span>
        </template>

        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 14px; padding: 15px 16px; margin-bottom: 14px">
            <div style="display: flex; align-items: center; margin-bottom: 13px">
                <div style="flex: 1">
                    <div style="font-size: 14px; font-weight: 600">{{ plan.name }}</div>
                    <div style="font-size: 11.5px; color: var(--cp-dim)">Renews {{ plan.renews }} · {{ plan.price }}</div>
                </div>
                <span style="font-size: 11.5px; color: var(--cp-vio); border: 1px solid var(--cp-ln2); border-radius: 8px; padding: 6px 12px; font-weight: 500">Upgrade</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px">
                <div v-for="u in usage" :key="u.label">
                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; margin-bottom: 5px">
                        <span style="color: var(--cp-mut)">{{ u.label }}</span><span style="color: var(--cp-dim)">{{ u.used }} / {{ u.total }}</span>
                    </div>
                    <div style="height: 6px; border-radius: 4px; background: var(--cp-soft)">
                        <div :style="`width:${u.pct}%;height:100%;border-radius:4px;background:${tone(u.tone)}`"></div>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 14px; margin-bottom: 14px">
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 14px; overflow: hidden">
                <div style="display: flex; align-items: center; padding: 11px 14px; border-bottom: 1px solid var(--cp-ln)">
                    <span style="font-size: 13px; font-weight: 600; flex: 1">My websites</span>
                    <i class="ti ti-plus" style="color: var(--cp-vio); font-size: 16px" aria-hidden="true"></i>
                </div>
                <div v-for="(s, i) in sites" :key="s"
                    :style="`display:flex;align-items:center;gap:9px;padding:10px 14px;font-size:12.5px;${i < sites.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                    <span style="width: 7px; height: 7px; border-radius: 50%; background: var(--cp-grn)"></span>
                    <span style="flex: 1; font-weight: 500">{{ s }}</span>
                    <i class="ti ti-external-link" style="color: var(--cp-dim); font-size: 14px" aria-hidden="true"></i>
                    <i class="ti ti-settings" style="color: var(--cp-dim); font-size: 14px" aria-hidden="true"></i>
                </div>
            </div>
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 14px; padding: 13px 14px">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 11px">Next invoice</div>
                <div style="font-size: 26px; font-weight: 600">{{ invoice.amount }}</div>
                <div style="font-size: 11.5px; color: var(--cp-dim); margin-bottom: 12px">due {{ invoice.due }}</div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--cp-mut); padding-top: 11px; border-top: 1px solid var(--cp-ln)">
                    <i class="ti ti-credit-card" style="font-size: 16px" aria-hidden="true"></i>{{ invoice.card }}
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 11px">
            <div v-for="qa in [
                { icon: 'ti-apps', label: 'Install an app', c: 'var(--cp-ind)' },
                { icon: 'ti-mail-plus', label: 'Add email', c: 'var(--cp-grn)' },
                { icon: 'ti-database-plus', label: 'New database', c: 'var(--cp-cy)' },
                { icon: 'ti-lifebuoy', label: 'Get support', c: 'var(--cp-amb)' },
            ]" :key="qa.label" style="display: flex; flex-direction: column; gap: 8px; padding: 13px; border: 1px solid var(--cp-ln); border-radius: 11px; font-size: 12px; color: var(--cp-mut)">
                <i class="ti" :class="qa.icon" :style="`font-size:19px;color:${qa.c}`" aria-hidden="true"></i>{{ qa.label }}
            </div>
        </div>
    </AppLayout>
</template>
