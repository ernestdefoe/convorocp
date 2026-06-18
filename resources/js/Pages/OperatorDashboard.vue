<script setup>
import AppLayout from '../Layouts/AppLayout.vue';

defineProps({ metrics: Array, customers: Array, node: Object });

const tone = (t) => ({ grn: 'var(--cp-grn)', amb: 'var(--cp-amb)', cy: 'var(--cp-cy)', mut: 'var(--cp-mut)' }[t] || 'var(--cp-mut)');
const planStyle = (p) => ({
    Business: 'background:rgba(139,139,240,.18);color:var(--cp-vio)',
    Pro: 'background:rgba(52,211,153,.16);color:var(--cp-grn)',
}[p] || 'background:var(--cp-soft);color:var(--cp-mut)');
</script>

<template>
    <AppLayout variant="operator" active="overview" title="Hosting overview" :subtitle="`${node.name} · ${node.os}`">
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
                <div style="display: flex; align-items: center; margin-bottom: 12px"><span style="font-size: 13px; font-weight: 600; flex: 1">{{ node.name }} · node health</span><span style="font-size: 11px; color: var(--cp-grn); display: inline-flex; align-items: center; gap: 4px"><span style="width: 6px; height: 6px; border-radius: 50%; background: var(--cp-grn)"></span>up {{ node.metrics.uptime }}</span></div>
                <div v-for="bar in [
                    { label: 'CPU', pct: node.metrics.cpu.pct, detail: node.metrics.cpu.load + ' load · ' + node.metrics.cpu.cores + ' cores', color: 'var(--cp-ind)' },
                    { label: 'Memory', pct: node.metrics.memory.pct, detail: node.metrics.memory.used_mb + ' / ' + node.metrics.memory.total_mb + ' MB', color: 'var(--cp-vio)' },
                    { label: 'Disk', pct: node.metrics.disk.pct, detail: node.metrics.disk.used_gb + ' / ' + node.metrics.disk.total_gb + ' GB', color: 'var(--cp-cy)' },
                ]" :key="bar.label" style="margin-bottom: 10px">
                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; margin-bottom: 4px"><span style="color: var(--cp-mut)">{{ bar.label }} <span style="color: var(--cp-dim)">{{ bar.detail }}</span></span><span style="color: var(--cp-mut)">{{ bar.pct }}%</span></div>
                    <div style="height: 5px; border-radius: 4px; background: var(--cp-soft)"><div :style="`width:${bar.pct}%;height:100%;border-radius:4px;background:${bar.color}`"></div></div>
                </div>
            </div>
        </div>

        <!-- node resources + service health -->
        <div style="border: 1px solid var(--cp-ln); border-radius: 13px; background: var(--cp-card); padding: 13px 14px; margin-top: 14px">
            <div style="display: flex; align-items: center; margin-bottom: 12px"><span style="font-size: 13px; font-weight: 600; flex: 1">Node services</span><span style="font-size: 11px; color: var(--cp-dim)">kernel {{ node.kernel }} · PHP {{ node.php }}</span></div>
            <div style="display: flex; gap: 18px; flex-wrap: wrap; margin-bottom: 14px">
                <div v-for="c in [
                    { n: node.counts.sites, l: 'Sites' }, { n: node.counts.databases, l: 'Databases' },
                    { n: node.counts.containers, l: 'Containers' }, { n: node.counts.mailboxes, l: 'Mailboxes' },
                    { n: node.counts.customers, l: 'Customers' },
                ]" :key="c.l" style="min-width: 70px">
                    <div style="font-size: 19px; font-weight: 600">{{ c.n }}</div>
                    <div style="font-size: 11px; color: var(--cp-dim)">{{ c.l }}</div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 6px 18px; border-top: 1px solid var(--cp-ln); padding-top: 12px">
                <div v-for="s in node.services" :key="s.name" style="display: flex; align-items: center; gap: 8px; font-size: 12px; padding: 3px 0">
                    <span :style="`width:7px;height:7px;border-radius:50%;flex-shrink:0;background:${s.status === 'active' ? 'var(--cp-grn)' : s.status === 'inactive' ? 'var(--cp-dim)' : 'var(--cp-amb)'}`"></span>
                    <span style="flex: 1; color: var(--cp-mut)">{{ s.label }}</span>
                    <span :style="`font-size:10.5px;text-transform:capitalize;color:${s.status === 'active' ? 'var(--cp-grn)' : 'var(--cp-dim)'}`">{{ s.status }}</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
