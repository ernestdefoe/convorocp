<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ node: Object });

const bar = (pct, tone) => `height:6px;border-radius:999px;background:var(--cp-ln2);overflow:hidden`;
const tone = (pct) => pct >= 90 ? 'var(--cp-red)' : pct >= 70 ? 'var(--cp-amb)' : 'var(--cp-grn)';
const counts = [
    { key: 'sites', label: 'Sites', icon: 'ti-world' },
    { key: 'databases', label: 'Databases', icon: 'ti-database' },
    { key: 'containers', label: 'Containers', icon: 'ti-brand-docker' },
    { key: 'mailboxes', label: 'Mailboxes', icon: 'ti-mail' },
    { key: 'customers', label: 'Customers', icon: 'ti-users' },
];
const statusColor = (s) => s === 'active' ? 'var(--cp-grn)' : s === 'inactive' ? 'var(--cp-dim)' : 'var(--cp-amb)';
const card = 'background:var(--cp-card);border:1px solid var(--cp-ln);border-radius:13px;padding:18px 20px';
</script>

<template>
    <AppLayout active="nodes" title="Nodes" subtitle="Infrastructure">
        <!-- node header -->
        <div :style="card + ';display:flex;align-items:center;gap:14px;margin-bottom:14px'">
            <div style="width: 42px; height: 42px; border-radius: 11px; background: rgba(91,91,214,.16); display: flex; align-items: center; justify-content: center"><i class="ti ti-server-2" style="font-size: 22px; color: var(--cp-vio)" aria-hidden="true"></i></div>
            <div style="flex: 1; min-width: 0">
                <div style="display: flex; align-items: center; gap: 9px">
                    <span style="font-size: 17px; font-weight: 600; letter-spacing: -0.02em">{{ node.name }}</span>
                    <span style="font-size: 10.5px; font-weight: 600; color: var(--cp-grn); background: rgba(52,211,153,.16); padding: 2px 9px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px"><span style="width: 6px; height: 6px; border-radius: 50%; background: var(--cp-grn)"></span>Online</span>
                </div>
                <div style="font-size: 11.5px; color: var(--cp-dim)">{{ node.os }} · kernel {{ node.kernel }} · PHP {{ node.php }} · up {{ node.metrics.uptime }}</div>
            </div>
        </div>

        <!-- resource meters -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 14px">
            <div :style="card">
                <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 9px"><span style="color: var(--cp-mut)">CPU</span><span style="font-weight: 600">{{ node.metrics.cpu.pct }}%</span></div>
                <div :style="bar()"><div :style="`height:100%;width:${node.metrics.cpu.pct}%;background:${tone(node.metrics.cpu.pct)}`"></div></div>
                <div style="font-size: 11px; color: var(--cp-dim); margin-top: 8px">load {{ node.metrics.cpu.load }} · {{ node.metrics.cpu.cores }} cores</div>
            </div>
            <div :style="card">
                <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 9px"><span style="color: var(--cp-mut)">Memory</span><span style="font-weight: 600">{{ node.metrics.memory.pct }}%</span></div>
                <div :style="bar()"><div :style="`height:100%;width:${node.metrics.memory.pct}%;background:${tone(node.metrics.memory.pct)}`"></div></div>
                <div style="font-size: 11px; color: var(--cp-dim); margin-top: 8px">{{ node.metrics.memory.used_mb }} / {{ node.metrics.memory.total_mb }} MB</div>
            </div>
            <div :style="card">
                <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 9px"><span style="color: var(--cp-mut)">Disk</span><span style="font-weight: 600">{{ node.metrics.disk.pct }}%</span></div>
                <div :style="bar()"><div :style="`height:100%;width:${node.metrics.disk.pct}%;background:${tone(node.metrics.disk.pct)}`"></div></div>
                <div style="font-size: 11px; color: var(--cp-dim); margin-top: 8px">{{ node.metrics.disk.used_gb }} / {{ node.metrics.disk.total_gb }} GB</div>
            </div>
        </div>

        <!-- counts -->
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 14px">
            <div v-for="c in counts" :key="c.key" :style="card + ';text-align:center'">
                <i class="ti" :class="c.icon" style="font-size: 18px; color: var(--cp-vio)" aria-hidden="true"></i>
                <div style="font-size: 22px; font-weight: 600; margin-top: 6px">{{ node.counts[c.key] }}</div>
                <div style="font-size: 11px; color: var(--cp-dim)">{{ c.label }}</div>
            </div>
        </div>

        <!-- services -->
        <div :style="card">
            <div style="font-size: 13px; font-weight: 600; margin-bottom: 14px">Services</div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px 24px">
                <div v-for="s in node.services" :key="s.name" style="display: flex; align-items: center; gap: 9px; font-size: 12.5px; padding: 4px 0">
                    <span :style="`width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${statusColor(s.status)}`"></span>
                    <span style="flex: 1">{{ s.label }}</span>
                    <span :style="`font-size:11px;text-transform:capitalize;color:${statusColor(s.status)}`">{{ s.status }}</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
