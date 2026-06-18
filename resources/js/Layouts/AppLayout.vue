<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ThemeToggle from '../Components/ThemeToggle.vue';

const mobileOpen = ref(false);

const props = defineProps({
    variant: { type: String, default: '' },
    active: { type: String, default: '' },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});

const navSets = {
    operator: [
        { key: 'overview', label: 'Overview', icon: 'ti-chart-line', href: '/' },
        { key: 'customers', label: 'Customers', icon: 'ti-users', href: '/customers' },
        { key: 'sites', label: 'Sites', icon: 'ti-world', href: '/sites' },
        { key: 'apps', label: 'App Installer', icon: 'ti-apps', href: '/apps' },
        { key: 'databases', label: 'Databases', icon: 'ti-database', href: '/databases' },
        { key: 'dns', label: 'DNS', icon: 'ti-route', href: '/dns' },
        { key: 'mail', label: 'Webmail', icon: 'ti-mail', href: '/mail' },
        { key: 'php', label: 'PHP', icon: 'ti-brand-php', href: '/php' },
        { key: 'containers', label: 'Docker', icon: 'ti-brand-docker', href: '/containers' },
        { key: 'scheduler', label: 'Scheduler', icon: 'ti-calendar-clock', href: '/scheduler' },
        { key: 'daemons', label: 'Daemons', icon: 'ti-cpu', href: '/daemons' },
        { key: 'backups', label: 'Backups', icon: 'ti-archive', href: '/backups' },
        { key: 'services', label: 'Services', icon: 'ti-server-cog', href: '/services' },
        { key: 'terminal', label: 'Terminal', icon: 'ti-terminal-2', href: '/terminal' },
        { key: 'security', label: 'Security', icon: 'ti-shield-lock', href: '/security' },
        { key: 'updates', label: 'Updates', icon: 'ti-refresh-alert', href: '/updates' },
        { key: 'branding', label: 'Branding', icon: 'ti-palette', href: '/branding' },
        { key: 'plans', label: 'Plans', icon: 'ti-tag', href: '/plans' },
        { key: 'billing', label: 'Billing', icon: 'ti-credit-card', href: '/billing' },
        { key: 'tickets', label: 'Tickets', icon: 'ti-lifebuoy', href: '/tickets' },
        { key: 'account', label: 'Account', icon: 'ti-user-cog', href: '/account' },
    ],
    client: [
        { key: 'home', label: 'My hosting', icon: 'ti-home', href: '/' },
        { key: 'sites', label: 'Websites', icon: 'ti-world', href: '/sites' },
        { key: 'apps', label: 'App Installer', icon: 'ti-apps', href: '/apps' },
        { key: 'mail', label: 'Email', icon: 'ti-mail', href: '/mail' },
        { key: 'databases', label: 'Databases', icon: 'ti-database', href: '/databases' },
        { key: 'dns', label: 'DNS', icon: 'ti-route', href: '/dns' },
        { key: 'files', label: 'Files', icon: 'ti-folder', href: '#' },
        { key: 'containers', label: 'Docker', icon: 'ti-brand-docker', href: '/containers' },
        { key: 'billing', label: 'Billing', icon: 'ti-credit-card', href: '/billing' },
        { key: 'backups', label: 'Backups', icon: 'ti-archive', href: '/backups' },
        { key: 'tickets', label: 'Support', icon: 'ti-lifebuoy', href: '/tickets' },
        { key: 'security', label: 'Security', icon: 'ti-shield-lock', href: '/security' },
        { key: 'account', label: 'Account', icon: 'ti-user-cog', href: '/account' },
    ],
    server: [
        { key: 'dashboard', label: 'Dashboard', icon: 'ti-layout-dashboard', href: '/' },
        { key: 'sites', label: 'Sites', icon: 'ti-world', href: '/sites' },
        { key: 'databases', label: 'Databases', icon: 'ti-database', href: '/databases' },
        { key: 'dns', label: 'DNS', icon: 'ti-route', href: '/dns' },
        { key: 'scheduler', label: 'Scheduler', icon: 'ti-calendar-clock', href: '/scheduler' },
        { key: 'daemons', label: 'Daemons', icon: 'ti-cpu', href: '/daemons' },
    ],
};

const role = computed(() => usePage().props.auth?.user?.role);
const effVariant = computed(() => props.variant || (role.value === 'operator' ? 'operator' : role.value === 'client' ? 'client' : 'server'));
const nav = computed(() => navSets[effVariant.value] ?? navSets.server);
const activeKey = computed(() => props.active || nav.value[0].key);
const tag = computed(() => (effVariant.value === 'operator' ? 'OPERATOR' : effVariant.value === 'client' ? 'CLIENT' : ''));
const user = computed(() => usePage().props.auth?.user ?? { name: 'User', initials: 'U' });
const brand = computed(() => usePage().props.brand ?? { name: 'ConvoroCP', logo: null });

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div style="min-height: 100vh; background: var(--cp-bg)">
        <!-- Mobile top bar (hidden on desktop via .cp-topbar) -->
        <div class="cp-topbar">
            <button type="button" @click="mobileOpen = true" aria-label="Open menu"
                style="border: 0; background: transparent; color: var(--cp-ink); cursor: pointer; padding: 4px; display: inline-flex; align-items: center">
                <i class="ti ti-menu-2" style="font-size: 22px" aria-hidden="true"></i>
            </button>
            <img v-if="brand.logo" :src="brand.logo" :alt="brand.name" style="width: 24px; height: 24px; border-radius: 7px; object-fit: contain" />
            <div v-else style="width: 24px; height: 24px; border-radius: 7px; background: var(--cp-ind); display: flex; align-items: center; justify-content: center">
                <i class="ti ti-sailboat" style="font-size: 15px; color: #fff" aria-hidden="true"></i>
            </div>
            <span style="font-size: 15px; font-weight: 600; letter-spacing: -0.02em">{{ brand.name }}</span>
        </div>

        <div style="display: flex; min-height: 100vh">
            <div v-if="mobileOpen" class="cp-backdrop" @click="mobileOpen = false"></div>
            <aside class="cp-sidebar" :class="{ 'cp-open': mobileOpen }" style="background: var(--cp-side); border-right: 1px solid var(--cp-ln); padding: 16px 12px; display: flex; flex-direction: column; gap: 3px">
            <div style="display: flex; align-items: center; gap: 9px; padding: 4px 8px 14px">
                <img v-if="brand.logo" :src="brand.logo" :alt="brand.name" style="width: 28px; height: 28px; border-radius: 8px; object-fit: contain" />
                <div v-else style="width: 28px; height: 28px; border-radius: 8px; background: var(--cp-ind); display: flex; align-items: center; justify-content: center">
                    <i class="ti ti-sailboat" style="font-size: 17px; color: #fff" aria-hidden="true"></i>
                </div>
                <div style="line-height: 1.1">
                    <div style="font-size: 15px; font-weight: 600; letter-spacing: -0.02em">{{ brand.name }}</div>
                    <div v-if="tag" style="font-size: 9.5px; font-weight: 500; color: var(--cp-vio)">{{ tag }}</div>
                </div>
            </div>
            <a v-for="item in nav" :key="item.key" :href="item.href" @click="mobileOpen = false"
                :style="`display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:9px;font-size:13px;text-decoration:none;${item.key === activeKey ? 'background:rgba(91,91,214,.16);color:var(--cp-ink)' : 'color:var(--cp-mut)'}`">
                <i class="ti" :class="item.icon" :style="`font-size:17px;${item.key === activeKey ? 'color:var(--cp-vio)' : ''}`" aria-hidden="true"></i>{{ item.label }}
            </a>
            <div style="margin-top: auto; display: flex; flex-direction: column; gap: 9px">
                <ThemeToggle />
                <div style="display: flex; align-items: center; gap: 9px; padding: 8px 9px; border: 1px solid var(--cp-ln); border-radius: 10px; background: var(--cp-card)">
                    <div style="width: 26px; height: 26px; border-radius: 50%; background: var(--cp-vio); display: flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 600; color: #1a1430">{{ user.initials }}</div>
                    <a href="/account" style="line-height: 1.25; flex: 1; min-width: 0; text-decoration: none; color: inherit" title="Account settings">
                        <div style="font-size: 12px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ user.name }}</div>
                    </a>
                    <button type="button" @click="logout" aria-label="Log out" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0">
                        <i class="ti ti-logout" style="font-size: 16px" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </aside>
            <main class="cp-main" style="flex: 1; min-width: 0; padding: 20px 26px">
                <div v-if="title" style="display: flex; align-items: center; gap: 12px; margin-bottom: 18px; flex-wrap: wrap">
                    <div style="flex: 1; min-width: 0">
                        <div v-if="subtitle" style="font-size: 11px; color: var(--cp-dim)">{{ subtitle }}</div>
                        <div style="font-size: 20px; font-weight: 600; letter-spacing: -0.02em">{{ title }}</div>
                    </div>
                    <slot name="actions" />
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
