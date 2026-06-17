<script setup>
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    server: Object,
    metrics: Array,
    sites: Array,
});

const accent = { CPU: 'var(--cp-ind)', Memory: 'var(--cp-vio)', Disk: 'var(--cp-amb)', Bandwidth: 'var(--cp-cy)' };
const statusColor = (s) => (s === 'healthy' ? 'var(--cp-grn)' : s === 'deploying' ? 'var(--cp-amb)' : 'var(--cp-dim)');

const services = [
    { name: 'nginx', state: 'active', color: 'var(--cp-grn)' },
    { name: 'php8.5-fpm', state: 'active', color: 'var(--cp-grn)' },
    { name: 'mariadb', state: 'active', color: 'var(--cp-grn)' },
    { name: 'redis', state: 'active', color: 'var(--cp-grn)' },
    { name: 'horizon', state: '3 workers', color: 'var(--cp-vio)' },
];
const activity = [
    { icon: 'ti-rocket', color: 'var(--cp-grn)', text: 'Deployed convoro.co · 2af70ab', when: '3 min ago' },
    { icon: 'ti-certificate', color: 'var(--cp-cy)', text: 'Renewed SSL for shop.convoro.co', when: '1 hr ago' },
    { icon: 'ti-database-export', color: 'var(--cp-vio)', text: 'Backup completed · 4.2 GB', when: '6 hr ago' },
];
</script>

<template>
    <AppLayout active="dashboard" :server="server">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 18px">
            <div style="flex: 1">
                <div style="font-size: 11px; color: var(--cp-dim)">Production workspace</div>
                <div style="font-size: 20px; font-weight: 600; letter-spacing: -0.02em">Overview</div>
            </div>
            <div style="display: flex; align-items: center; gap: 6px; background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 9px; padding: 6px 10px; font-size: 12px; color: var(--cp-mut)">
                <i class="ti ti-search" style="font-size: 14px" aria-hidden="true"></i>Search
                <span style="margin-left: 16px; border: 1px solid var(--cp-ln2); border-radius: 5px; padding: 0 5px; font-size: 10.5px; color: var(--cp-dim)">⌘K</span>
            </div>
            <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--cp-vio); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #1a1430">ED</div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 14px">
            <div v-for="m in metrics" :key="m.label" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 14px; padding: 13px 14px">
                <div style="display: flex; justify-content: space-between; align-items: center">
                    <span style="font-size: 12px; color: var(--cp-mut)">{{ m.label }}</span>
                    <span style="font-size: 11px; font-weight: 500; padding: 2px 8px; border-radius: 999px; background: var(--cp-soft); color: var(--cp-mut)">{{ m.delta }}</span>
                </div>
                <div style="font-size: 23px; font-weight: 600; margin: 4px 0 8px">{{ m.value }}<span style="font-size: 13px; color: var(--cp-dim); font-weight: 400">{{ m.unit }}</span></div>
                <div style="height: 4px; border-radius: 4px; background: var(--cp-soft); overflow: hidden">
                    <div :style="`width:46%;height:100%;background:${accent[m.label] || 'var(--cp-ind)'}`"></div>
                </div>
            </div>
        </div>

        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 14px; overflow: hidden; margin-bottom: 14px">
            <div style="display: flex; align-items: center; padding: 12px 15px; border-bottom: 1px solid var(--cp-ln)">
                <span style="font-size: 13px; font-weight: 600; flex: 1">Sites</span>
                <span style="display: flex; align-items: center; gap: 6px; background: var(--cp-ind); color: #fff; font-size: 12px; font-weight: 500; padding: 6px 12px; border-radius: 8px">
                    <i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New site
                </span>
            </div>
            <div v-for="(s, i) in sites" :key="s.name"
                :style="`display:flex;align-items:center;gap:10px;padding:11px 15px;font-size:13px;${i < sites.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <span :style="`width:7px;height:7px;border-radius:50%;background:${statusColor(s.status)}`"></span>
                <span style="flex: 1; font-weight: 500">{{ s.name }}</span>
                <span style="font-size: 11px; padding: 2px 8px; border-radius: 999px; background: var(--cp-soft); color: var(--cp-mut)">{{ s.runtime }}</span>
                <span style="color: var(--cp-dim); width: 72px; text-align: right">{{ s.visits }}</span>
                <i class="ti ti-dots" style="color: var(--cp-dim)" aria-hidden="true"></i>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px">
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 14px; padding: 13px 15px">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 11px">Services</div>
                <div v-for="svc in services" :key="svc.name" style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; margin-bottom: 9px">
                    <span :style="`width:7px;height:7px;border-radius:50%;background:${svc.color}`"></span>
                    <span style="flex: 1">{{ svc.name }}</span>
                    <span style="color: var(--cp-dim)">{{ svc.state }}</span>
                </div>
            </div>
            <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 14px; padding: 13px 15px">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 11px">Activity</div>
                <div v-for="(a, i) in activity" :key="i" style="display: flex; gap: 9px; font-size: 12px; margin-bottom: 11px">
                    <i class="ti" :class="a.icon" :style="`color:${a.color};font-size:15px`" aria-hidden="true"></i>
                    <div style="line-height: 1.4">
                        <div>{{ a.text }}</div>
                        <div style="color: var(--cp-dim); font-size: 11px">{{ a.when }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
