<script setup>
import AppLayout from '../Layouts/AppLayout.vue';

defineProps({ metrics: Array, customers: Array, node: Object, plans: { type: Array, default: () => [] }, customersTotal: { type: Number, default: 0 }, beszelHistory: { type: Object, default: null } });

const tone = (t) => ({ grn: 'var(--cp-grn)', amb: 'var(--cp-amb)', cy: 'var(--cp-cy)', mut: 'var(--cp-mut)' }[t] || 'var(--cp-mut)');

// Build an SVG area + line path (viewBox 0 0 100 40) from a percentage series.
const spark = (arr, w = 100, h = 40) => {
    if (!arr || arr.length < 2) return { line: '', fill: '' };
    const max = Math.max(5, Math.max(...arr) * 1.15);
    const n = arr.length;
    const pt = (v, i) => `${((i / (n - 1)) * w).toFixed(1)},${(h - Math.min(1, v / max) * h).toFixed(1)}`;
    const line = 'M' + arr.map(pt).join(' L');
    return { line, fill: `${line} L${w},${h} L0,${h} Z` };
};
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
                <span style="font-size: 11.5px; color: var(--cp-dim)">{{ customersTotal }} total</span>
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

        <div :style="`display:grid;grid-template-columns:${plans.length ? '1fr 1fr' : '1fr'};gap:14px`">
            <div v-if="plans.length" style="border: 1px solid var(--cp-ln); border-radius: 13px; background: var(--cp-card); padding: 13px 14px">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 11px">Plans</div>
                <div v-for="(p, i) in plans" :key="p.name" :style="`display:flex;gap:8px;font-size:12.5px;${i < plans.length - 1 ? 'margin-bottom:9px' : ''}`">
                    <span style="flex: 1">{{ p.name }}</span>
                    <span style="color: var(--cp-dim)">{{ p.subs }} {{ p.subs === 1 ? 'sub' : 'subs' }}</span>
                    <span style="font-weight: 500">{{ p.price }}</span>
                </div>
            </div>
            <div style="border: 1px solid var(--cp-ln); border-radius: 13px; background: var(--cp-card); padding: 13px 14px">
                <div style="display: flex; align-items: center; margin-bottom: 12px"><span style="font-size: 13px; font-weight: 600; flex: 1">{{ node.name }} · node health</span><a v-if="node.beszel" href="/monitoring" title="Live via Beszel — open full dashboard" style="font-size: 10.5px; color: var(--cp-ind); text-decoration: none; display: inline-flex; align-items: center; gap: 3px; margin-right: 12px"><i class="ti ti-activity-heartbeat" style="font-size: 12px" aria-hidden="true"></i>Beszel</a><span style="font-size: 11px; color: var(--cp-grn); display: inline-flex; align-items: center; gap: 4px"><span style="width: 6px; height: 6px; border-radius: 50%; background: var(--cp-grn)"></span>up {{ node.metrics.uptime }}</span></div>
                <!-- Beszel history graphs (preferred); falls back to plain bars when the hub is off. -->
                <div v-for="g in [
                    { key: 'cpu', label: 'CPU', series: beszelHistory && beszelHistory.cpu, pct: node.metrics.cpu.pct, detail: node.metrics.cpu.load + ' load · ' + node.metrics.cpu.cores + ' cores', color: 'var(--cp-ind)' },
                    { key: 'memory', label: 'Memory', series: beszelHistory && beszelHistory.memory, pct: node.metrics.memory.pct, detail: node.metrics.memory.used_mb + ' / ' + node.metrics.memory.total_mb + ' MB', color: 'var(--cp-vio)' },
                    { key: 'disk', label: 'Disk', series: beszelHistory && beszelHistory.disk, pct: node.metrics.disk.pct, detail: node.metrics.disk.used_gb + ' / ' + node.metrics.disk.total_gb + ' GB', color: 'var(--cp-cy)' },
                ]" :key="g.key" style="margin-bottom: 12px">
                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; margin-bottom: 4px"><span style="color: var(--cp-mut)">{{ g.label }} <span style="color: var(--cp-dim)">{{ g.detail }}</span></span><span style="color: var(--cp-mut)">{{ g.pct }}%</span></div>
                    <svg v-if="beszelHistory" viewBox="0 0 100 40" preserveAspectRatio="none" style="width: 100%; height: 38px; display: block">
                        <defs><linearGradient :id="`bz-${g.key}`" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" :stop-color="g.color" stop-opacity="0.35" /><stop offset="100%" :stop-color="g.color" stop-opacity="0" /></linearGradient></defs>
                        <path :d="spark(g.series).fill" :fill="`url(#bz-${g.key})`" />
                        <path :d="spark(g.series).line" fill="none" :stroke="g.color" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                    </svg>
                    <div v-else style="height: 5px; border-radius: 4px; background: var(--cp-soft)"><div :style="`width:${g.pct}%;height:100%;border-radius:4px;background:${g.color}`"></div></div>
                </div>
            </div>
        </div>

        <!-- resources -->
        <div style="margin-top: 14px; margin-bottom: 8px; font-size: 12px; font-weight: 600; color: var(--cp-mut)">Resources on this node</div>
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 11px; margin-bottom: 14px">
            <div v-for="c in [
                { n: node.counts.sites, l: 'Sites', icon: 'ti-world' },
                { n: node.counts.databases, l: 'Databases', icon: 'ti-database' },
                { n: node.counts.containers, l: 'Containers', icon: 'ti-brand-docker' },
                { n: node.counts.mailboxes, l: 'Mailboxes', icon: 'ti-mail' },
                { n: node.counts.customers, l: 'Customers', icon: 'ti-users' },
            ]" :key="c.l" style="background: var(--cp-card2); border-radius: 12px; padding: 12px 13px">
                <div style="display: flex; align-items: center; gap: 6px; color: var(--cp-dim)"><i class="ti" :class="c.icon" style="font-size: 14px" aria-hidden="true"></i><span style="font-size: 11px">{{ c.l }}</span></div>
                <div style="font-size: 22px; font-weight: 600; margin-top: 3px">{{ c.n }}</div>
            </div>
        </div>

        <!-- service health -->
        <div style="border: 1px solid var(--cp-ln); border-radius: 13px; background: var(--cp-card); overflow: hidden">
            <div style="display: flex; align-items: center; padding: 11px 14px; border-bottom: 1px solid var(--cp-ln)">
                <span style="font-size: 13px; font-weight: 600; flex: 1">Service health</span>
                <span style="font-size: 11px; color: var(--cp-dim)">kernel {{ node.kernel }} · PHP {{ node.php }}</span>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr">
                <div v-for="(s, i) in node.services" :key="s.name"
                    :style="`display:flex;align-items:center;gap:9px;padding:10px 14px;font-size:12.5px;border-bottom:1px solid var(--cp-ln);${i % 2 === 0 ? 'border-right:1px solid var(--cp-ln)' : ''}`">
                    <span :style="`width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${s.status === 'active' ? 'var(--cp-grn)' : s.status === 'failed' ? 'var(--cp-red)' : 'var(--cp-dim)'}`"></span>
                    <span style="flex: 1; color: var(--cp-ink)">{{ s.label }}</span>
                    <span :style="`font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;padding:2px 8px;border-radius:999px;${s.status === 'active' ? 'background:rgba(52,211,153,.16);color:var(--cp-grn)' : s.status === 'failed' ? 'background:rgba(248,113,113,.16);color:var(--cp-red)' : 'background:var(--cp-soft);color:var(--cp-dim)'}`">{{ s.status === 'active' ? 'Running' : s.status === 'inactive' ? 'Stopped' : s.status }}</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
