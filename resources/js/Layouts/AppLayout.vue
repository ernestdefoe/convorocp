<script setup>
const props = defineProps({
    active: { type: String, default: 'dashboard' },
    server: { type: Object, default: () => ({ name: 'web-01', status: 'healthy', uptime: '41d' }) },
});

const nav = [
    { key: 'dashboard', label: 'Dashboard', icon: 'ti-layout-dashboard', href: '/' },
    { key: 'sites', label: 'Sites', icon: 'ti-world', href: '#' },
    { key: 'email', label: 'Email', icon: 'ti-mail', href: '#' },
    { key: 'databases', label: 'Databases', icon: 'ti-database', href: '#' },
    { key: 'files', label: 'Files', icon: 'ti-folder', href: '#' },
    { key: 'scheduler', label: 'Scheduler', icon: 'ti-calendar-clock', href: '#' },
    { key: 'daemons', label: 'Daemons', icon: 'ti-cpu', href: '#' },
    { key: 'security', label: 'Security', icon: 'ti-shield-lock', href: '#' },
];
</script>

<template>
    <div style="display: flex; min-height: 100vh; background: var(--cp-bg)">
        <aside style="width: 200px; flex-shrink: 0; background: var(--cp-side); border-right: 1px solid var(--cp-ln); padding: 16px 12px; display: flex; flex-direction: column; gap: 3px">
            <div style="display: flex; align-items: center; gap: 9px; padding: 4px 8px 14px">
                <div style="width: 28px; height: 28px; border-radius: 8px; background: var(--cp-ind); display: flex; align-items: center; justify-content: center">
                    <i class="ti ti-sailboat" style="font-size: 17px; color: #fff" aria-hidden="true"></i>
                </div>
                <span style="font-size: 16px; font-weight: 600; letter-spacing: -0.02em">ConvoroCP</span>
            </div>
            <a v-for="item in nav" :key="item.key" :href="item.href"
                :style="`display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:9px;font-size:13px;text-decoration:none;${item.key === active ? 'background:rgba(91,91,214,.16);color:#fff' : 'color:var(--cp-mut)'}`">
                <i class="ti" :class="item.icon" :style="`font-size:17px;${item.key === active ? 'color:var(--cp-vio)' : ''}`" aria-hidden="true"></i>{{ item.label }}
            </a>
            <div style="margin-top: auto; display: flex; align-items: center; gap: 9px; padding: 9px; border: 1px solid var(--cp-ln); border-radius: 10px; background: var(--cp-card)">
                <span style="width: 7px; height: 7px; border-radius: 50%; background: var(--cp-grn)"></span>
                <div style="line-height: 1.3">
                    <div style="font-size: 12px; font-weight: 500">{{ server.name }}</div>
                    <div style="font-size: 10.5px; color: var(--cp-dim)">{{ server.status }} · {{ server.uptime }} up</div>
                </div>
            </div>
        </aside>
        <main style="flex: 1; min-width: 0; padding: 20px 26px">
            <slot />
        </main>
    </div>
</template>
