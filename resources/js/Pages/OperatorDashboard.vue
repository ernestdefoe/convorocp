<script setup>
import AppLayout from '../Layouts/AppLayout.vue';

defineProps({ metrics: Array, customers: Array });

const tone = (t) => ({ grn: 'var(--cp-grn)', amb: 'var(--cp-amb)', cy: 'var(--cp-cy)', mut: 'var(--cp-mut)' }[t] || 'var(--cp-mut)');
const planStyle = (p) => ({
    Business: 'background:rgba(139,139,240,.18);color:var(--cp-vio)',
    Pro: 'background:rgba(52,211,153,.16);color:var(--cp-grn)',
}[p] || 'background:var(--cp-soft);color:var(--cp-mut)');
</script>

<template>
    <AppLayout variant="operator" active="overview" title="Hosting overview" subtitle="Convoro Hosting · 6 nodes">
        <template #actions>
            <span style="font-size: 12px; color: #fff; background: var(--cp-ind); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500">
                <i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New customer
            </span>
        </template>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 11px; margin-bottom: 14px">
            <div v-for="m in metrics" :key="m.label" style="background: var(--cp-card2); border-radius: 12px; padding: 12px 13px">
                <div style="font-size: 11px; color: var(--cp-dim)">{{ m.label }}</div>
                <div style="font-size: 22px; font-weight: 600">{{ m.value }}</div>
                <div :style="`font-size:11px;margin-top:1px;color:${tone(m.tone)}`">{{ m.sub }}</div>
            </div>
        </div>

        <div style="border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden; background: var(--cp-card); margin-bottom: 14px">
            <div style="display: flex; align-items: center; padding: 11px 14px; border-bottom: 1px solid var(--cp-ln)">
                <span style="font-size: 13px; font-weight: 600; flex: 1">Customers</span>
                <span style="font-size: 11.5px; color: var(--cp-dim)">142 total</span>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 92px 50px 70px 60px; gap: 10px; padding: 8px 14px; border-bottom: 1px solid var(--cp-ln); font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: var(--cp-dim)">
                <span>Customer</span><span>Plan</span><span>Sites</span><span>Node</span><span style="text-align: right">MRR</span>
            </div>
            <div v-for="(c, i) in customers" :key="c.name"
                :style="`display:grid;grid-template-columns:1fr 92px 50px 70px 60px;gap:10px;padding:10px 14px;font-size:12.5px;align-items:center;${i < customers.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <div style="display: flex; align-items: center; gap: 9px">
                    <span style="width: 26px; height: 26px; border-radius: 50%; background: rgba(91,91,214,.2); color: var(--cp-vio); display: flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 600">{{ c.initials }}</span>{{ c.name }}
                </div>
                <span :style="`font-size:10.5px;font-weight:500;padding:2px 8px;border-radius:999px;justify-self:start;${planStyle(c.plan)}`">{{ c.plan }}</span>
                <span style="color: var(--cp-mut)">{{ c.sites }}</span>
                <span style="color: var(--cp-mut)">{{ c.node }}</span>
                <span style="text-align: right">{{ c.mrr }}</span>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px">
            <div style="border: 1px solid var(--cp-ln); border-radius: 13px; background: var(--cp-card); padding: 13px 14px">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 11px">Plans</div>
                <div style="display: flex; gap: 8px; font-size: 12.5px; margin-bottom: 9px"><span style="flex: 1">Starter</span><span style="color: var(--cp-dim)">38 subs</span><span style="font-weight: 500">$12/mo</span></div>
                <div style="display: flex; gap: 8px; font-size: 12.5px; margin-bottom: 9px"><span style="flex: 1">Pro</span><span style="color: var(--cp-dim)">74 subs</span><span style="font-weight: 500">$45/mo</span></div>
                <div style="display: flex; gap: 8px; font-size: 12.5px"><span style="flex: 1">Business</span><span style="color: var(--cp-dim)">30 subs</span><span style="font-weight: 500">$120/mo</span></div>
            </div>
            <div style="border: 1px solid var(--cp-ln); border-radius: 13px; background: var(--cp-card); padding: 13px 14px">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 11px">Nodes</div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; margin-bottom: 9px"><span style="width: 7px; height: 7px; border-radius: 50%; background: var(--cp-grn)"></span><span style="flex: 1">web-01</span><span style="color: var(--cp-dim)">62% · 48 sites</span></div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; margin-bottom: 9px"><span style="width: 7px; height: 7px; border-radius: 50%; background: var(--cp-grn)"></span><span style="flex: 1">web-02</span><span style="color: var(--cp-dim)">41% · 33 sites</span></div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px"><span style="width: 7px; height: 7px; border-radius: 50%; background: var(--cp-amb)"></span><span style="flex: 1">web-03</span><span style="color: var(--cp-dim)">83% · 61 sites</span></div>
            </div>
        </div>
    </AppLayout>
</template>
