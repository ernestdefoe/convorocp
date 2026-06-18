<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ isOperator: Boolean, twoFactor: Object, rules: Array, enabled: Boolean, fail2ban: Object });

const showForm = ref(false);
const form = useForm({ port: 8080, proto: 'tcp', action: 'allow', note: '' });
function add() { form.post('/security/rules', { onSuccess: () => { showForm.value = false; form.reset(); } }); }
const remove = (r) => { if (confirm(`Remove rule ${r.action} ${r.port}/${r.proto}?`)) router.delete(`/security/rules/${r.id}`); };
const toggle = () => router.post('/security/toggle', {}, { preserveScroll: true });
const installF2b = () => router.post('/security/fail2ban/install', {}, { preserveScroll: true });
const unban = (jail, ip) => { if (confirm(`Unban ${ip} from ${jail}?`)) router.post('/security/fail2ban/unban', { jail, ip }, { preserveScroll: true }); };

// two-factor (personal)
const enableForm = useForm({});
const confirmForm = useForm({ code: '' });
const disableForm = useForm({ password: '' });
const enable2fa = () => enableForm.post('/security/2fa/enable', { preserveScroll: true });
const confirm2fa = () => confirmForm.post('/security/2fa/confirm', { preserveScroll: true });
const disable2fa = () => { if (confirm('Disable two-factor authentication?')) disableForm.delete('/security/2fa', { preserveScroll: true }); };

const field = 'box-sizing:border-box;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="security" title="Security" :subtitle="isOperator ? 'Two-factor, firewall & fail2ban' : 'Two-factor authentication'">
        <!-- two-factor (everyone manages their own) -->
        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 18px 20px; margin-bottom: 14px; max-width: 620px">
            <div style="display: flex; align-items: center; gap: 12px">
                <i class="ti ti-lock-access" :style="`font-size:22px;color:${twoFactor.enabled ? 'var(--cp-grn)' : 'var(--cp-dim)'}`" aria-hidden="true"></i>
                <div style="flex: 1">
                    <div style="font-size: 14px; font-weight: 600">Two-factor authentication</div>
                    <div style="font-size: 11.5px; color: var(--cp-dim)">Require a 6-digit code from an authenticator app at login.</div>
                </div>
                <span v-if="twoFactor.enabled" style="font-size: 11px; font-weight: 600; color: var(--cp-grn); background: rgba(52,211,153,.16); padding: 3px 10px; border-radius: 999px">ON</span>
            </div>

            <div v-if="twoFactor.recoveryCodes && twoFactor.recoveryCodes.length" style="margin-top: 16px; background: rgba(251,191,36,.08); border: 1px solid rgba(251,191,36,.3); border-radius: 11px; padding: 14px">
                <div style="font-size: 12.5px; font-weight: 600; color: var(--cp-amb); margin-bottom: 6px">Save your recovery codes</div>
                <div style="font-size: 11.5px; color: var(--cp-mut); margin-bottom: 10px">Each works once if you lose your device. They won't be shown again.</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-family: ui-monospace, monospace; font-size: 12.5px">
                    <span v-for="c in twoFactor.recoveryCodes" :key="c" style="background: var(--cp-card2); padding: 5px 9px; border-radius: 7px">{{ c }}</span>
                </div>
            </div>

            <div v-if="twoFactor.pending" style="margin-top: 18px; border-top: 1px solid var(--cp-ln); padding-top: 18px; display: flex; gap: 18px; align-items: flex-start; flex-wrap: wrap">
                <img v-if="twoFactor.qr" :src="twoFactor.qr" alt="2FA QR" style="width: 150px; height: 150px; background: #fff; border-radius: 10px; padding: 8px" />
                <div style="flex: 1; min-width: 200px">
                    <div style="font-size: 11px; color: var(--cp-dim)">Scan with your authenticator, or enter this key</div>
                    <code style="display: block; font-size: 12px; color: var(--cp-mut); word-break: break-all; margin: 4px 0 14px">{{ twoFactor.secret }}</code>
                    <form @submit.prevent="confirm2fa">
                        <input v-model="confirmForm.code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" :style="field + ';width:100%;box-sizing:border-box'" />
                        <div v-if="confirmForm.errors.code" style="font-size: 11px; color: var(--cp-red); margin-top: 6px">{{ confirmForm.errors.code }}</div>
                        <button type="submit" :disabled="confirmForm.processing" style="margin-top: 10px; font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 18px; cursor: pointer; font-family: inherit">Confirm &amp; enable</button>
                    </form>
                </div>
            </div>
            <div v-else-if="!twoFactor.enabled" style="margin-top: 16px">
                <button type="button" @click="enable2fa" :disabled="enableForm.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 18px; cursor: pointer; font-family: inherit">Enable two-factor</button>
            </div>
            <form v-else @submit.prevent="disable2fa" style="margin-top: 18px; border-top: 1px solid var(--cp-ln); padding-top: 18px">
                <div style="font-size: 12.5px; color: var(--cp-mut); margin-bottom: 10px">Enter your password to turn off two-factor.</div>
                <div style="display: flex; gap: 8px; max-width: 420px">
                    <input v-model="disableForm.password" type="password" placeholder="Current password" :style="field + ';flex:1'" />
                    <button type="submit" :disabled="disableForm.processing" style="white-space: nowrap; font-size: 13px; color: var(--cp-red); background: transparent; border: 1px solid rgba(248,113,113,.4); border-radius: 9px; padding: 9px 16px; cursor: pointer; font-family: inherit">Disable</button>
                </div>
                <div v-if="disableForm.errors.password" style="font-size: 11px; color: var(--cp-red); margin-top: 6px">{{ disableForm.errors.password }}</div>
            </form>
        </div>

        <template v-if="isOperator">
        <div style="display: flex; align-items: center; gap: 10px; margin: 24px 0 14px"><div style="flex: 1; font-size: 13px; font-weight: 600">Firewall</div></div>
        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 16px; margin-bottom: 14px; display: flex; align-items: center; gap: 12px">
            <i class="ti ti-shield-lock" :style="`font-size:22px;color:${enabled ? 'var(--cp-grn)' : 'var(--cp-dim)'}`" aria-hidden="true"></i>
            <div style="flex: 1">
                <div style="font-size: 14px; font-weight: 600">Firewall {{ enabled ? 'enabled' : 'disabled' }}</div>
                <div style="font-size: 11.5px; color: var(--cp-dim)">Enabling always allows 22/80/443 + the panel port (8000) first so you can't lock yourself out.</div>
            </div>
            <button type="button" @click="toggle" :aria-label="enabled ? 'Disable firewall' : 'Enable firewall'"
                :style="`width:46px;height:25px;border:0;border-radius:999px;position:relative;cursor:pointer;flex-shrink:0;background:${enabled ? 'var(--cp-grn)' : 'var(--cp-ln2)'}`">
                <span :style="`position:absolute;top:2px;width:21px;height:21px;border-radius:50%;background:#fff;${enabled ? 'right:2px' : 'left:2px'}`"></span>
            </button>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px">
            <div style="flex: 1; font-size: 13px; font-weight: 600">Rules</div>
            <button type="button" @click="showForm = !showForm" style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit"><i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>Add rule</button>
        </div>

        <form v-if="showForm" @submit.prevent="add" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: grid; grid-template-columns: 90px 90px 90px 1fr auto; gap: 10px; align-items: end">
            <label style="font-size: 11.5px; color: var(--cp-mut)">Port<input v-model.number="form.port" type="number" :style="field + ';width:100%;margin-top:5px'" /></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Proto<select v-model="form.proto" :style="field + ';margin-top:5px;display:block;width:100%'"><option value="tcp">tcp</option><option value="udp">udp</option></select></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Action<select v-model="form.action" :style="field + ';margin-top:5px;display:block;width:100%'"><option value="allow">allow</option><option value="deny">deny</option></select></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Note<input v-model="form.note" :style="field + ';width:100%;margin-top:5px'" placeholder="optional" /></label>
            <button type="submit" :disabled="form.processing" style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 14px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Add</button>
        </form>

        <div style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div v-for="(r, i) in rules" :key="r.id"
                :style="`display:flex;align-items:center;gap:12px;padding:11px 15px;font-size:13px;${i < rules.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <span :style="`font-size:11px;font-weight:600;padding:2px 9px;border-radius:6px;${r.action === 'allow' ? 'background:rgba(52,211,153,.16);color:var(--cp-grn)' : 'background:rgba(248,113,113,.16);color:var(--cp-red)'}`">{{ r.action }}</span>
                <span style="font-family: ui-monospace, monospace; font-weight: 500">{{ r.port }}/{{ r.proto }}</span>
                <span style="flex: 1; font-size: 11.5px; color: var(--cp-dim)">{{ r.note }}</span>
                <button type="button" @click="remove(r)" aria-label="Remove" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-trash" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
        </div>

        <!-- fail2ban -->
        <div style="display: flex; align-items: center; gap: 10px; margin: 24px 0 14px">
            <div style="flex: 1; font-size: 13px; font-weight: 600">Brute-force protection (fail2ban)</div>
            <span v-if="fail2ban && fail2ban.installed" style="font-size: 11px; font-weight: 600; color: var(--cp-grn); background: rgba(52,211,153,.16); padding: 3px 10px; border-radius: 999px">ACTIVE</span>
        </div>

        <div v-if="!fail2ban || !fail2ban.installed" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 24px; text-align: center">
            <div style="font-size: 12.5px; color: var(--cp-dim); margin-bottom: 14px">Automatically ban IPs after repeated failed SSH logins.</div>
            <button type="button" @click="installF2b" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 9px 16px; cursor: pointer; font-family: inherit">Install &amp; enable fail2ban</button>
        </div>

        <div v-else style="display: flex; flex-direction: column; gap: 12px">
            <div v-for="j in fail2ban.jails" :key="j.name" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
                <div style="display: flex; align-items: center; gap: 10px; padding: 11px 15px; border-bottom: 1px solid var(--cp-ln)">
                    <i class="ti ti-shield-x" style="font-size: 16px; color: var(--cp-vio)" aria-hidden="true"></i>
                    <span style="font-family: ui-monospace, monospace; font-weight: 500; font-size: 13px">{{ j.name }}</span>
                    <span style="flex: 1"></span>
                    <span style="font-size: 11.5px; color: var(--cp-dim)">{{ j.total }} banned</span>
                </div>
                <div v-if="!j.banned.length" style="padding: 14px 15px; font-size: 12px; color: var(--cp-dim)">No IPs currently banned.</div>
                <div v-for="(ip, i) in j.banned" :key="ip"
                    :style="`display:flex;align-items:center;gap:12px;padding:9px 15px;font-size:13px;${i < j.banned.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                    <span style="font-family: ui-monospace, monospace; flex: 1">{{ ip }}</span>
                    <button type="button" @click="unban(j.name, ip)" style="font-size: 11.5px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 7px; padding: 4px 10px; cursor: pointer; font-family: inherit">Unban</button>
                </div>
            </div>
        </div>
        </template>
    </AppLayout>
</template>
