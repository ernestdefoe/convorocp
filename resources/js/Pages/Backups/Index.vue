<script setup>
import { ref, computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ backups: Array, sites: Array, databases: Array, schedules: Array, offsite: Object });

const isOperator = computed(() => usePage().props.auth?.user?.role === 'operator');

const showForm = ref(false);
const form = useForm({ kind: 'site', target: '' });
const targets = computed(() => form.kind === 'site' ? props.sites : props.databases.map((d) => d.name));
function create() {
    if (!form.target) form.target = targets.value[0] ?? '';
    form.post('/backups', { onSuccess: () => { showForm.value = false; form.reset(); } });
}
const destroy = (b) => { if (confirm('Delete this backup?')) router.delete(`/backups/${b.id}`); };
const restore = (b) => { if (confirm(`Restore ${b.kind} "${b.target}" from this backup? This overwrites the current ${b.kind === 'site' ? 'files' : 'database'}.`)) router.post(`/backups/${b.id}/restore`); };
const human = (n) => (n == null ? '—' : n < 1024 ? n + ' B' : n < 1048576 ? (n / 1024).toFixed(1) + ' KB' : (n / 1048576).toFixed(1) + ' MB');

// schedules
const showSched = ref(false);
const sched = useForm({ kind: 'site', target: '*', frequency: 'daily', retention: 7 });
const schedTargets = computed(() => sched.kind === 'site' ? props.sites : props.databases.map((d) => d.name));
const createSched = () => sched.post('/backups/schedules', { preserveScroll: true, onSuccess: () => { showSched.value = false; sched.reset(); } });
const toggleSched = (s) => router.patch(`/backups/schedules/${s.id}/toggle`, {}, { preserveScroll: true });
const delSched = (s) => { if (confirm('Delete this schedule?')) router.delete(`/backups/schedules/${s.id}`, { preserveScroll: true }); };

// offsite
const showOffsite = ref(false);
const off = useForm({ key: props.offsite?.key || '', secret: '', region: props.offsite?.region || '', bucket: props.offsite?.bucket || '', endpoint: props.offsite?.endpoint || '' });
const saveOffsite = () => off.post('/backups/offsite', { preserveScroll: true, onSuccess: () => { off.secret = ''; showOffsite.value = false; } });

const field = 'box-sizing:border-box;background:var(--cp-card2);border:1px solid var(--cp-ln);border-radius:9px;color:var(--cp-ink);padding:9px 11px;font-size:13px;font-family:inherit';
</script>

<template>
    <AppLayout active="backups" title="Backups" :subtitle="`${backups.length} backup${backups.length === 1 ? '' : 's'}`">
        <template #actions>
            <button type="button" @click="showForm = !showForm"
                style="font-size: 12px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; font-family: inherit">
                <i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New backup
            </button>
        </template>

        <form v-if="showForm" @submit.prevent="create" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: grid; grid-template-columns: 130px 1fr auto; gap: 10px; align-items: end">
            <label style="font-size: 11.5px; color: var(--cp-mut)">Type<select v-model="form.kind" @change="form.target = ''" :style="field + ';margin-top:5px;display:block;width:100%'"><option value="site">Site</option><option value="database">Database</option></select></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Target<select v-model="form.target" :style="field + ';margin-top:5px;display:block;width:100%'"><option v-for="t in targets" :key="t" :value="t">{{ t }}</option></select></label>
            <button type="submit" :disabled="form.processing" style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Back up now</button>
        </form>

        <div v-if="backups.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden">
            <div v-for="(b, i) in backups" :key="b.id"
                :style="`display:flex;align-items:center;gap:12px;padding:12px 15px;font-size:13px;${i < backups.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <i class="ti" :class="b.kind === 'site' ? 'ti-world' : 'ti-database'" :style="`font-size:16px;color:${b.kind === 'site' ? 'var(--cp-vio)' : 'var(--cp-cy)'}`" aria-hidden="true"></i>
                <div style="flex: 1; min-width: 0">
                    <div style="font-weight: 500">{{ b.target }}</div>
                    <div style="font-size: 11px; color: var(--cp-dim)">{{ b.kind }}<span v-if="b.engine"> · {{ b.engine }}</span> · {{ b.created }}</div>
                </div>
                <i v-if="b.offsite" class="ti ti-cloud-check" title="Stored offsite" style="font-size: 15px; color: var(--cp-grn)" aria-hidden="true"></i>
                <span style="font-size: 11.5px; color: var(--cp-dim); width: 70px; text-align: right">{{ human(b.size) }}</span>
                <span v-if="b.status !== 'done'" :style="`font-size:10.5px;font-weight:500;padding:2px 9px;border-radius:999px;${b.status === 'failed' ? 'background:rgba(248,113,113,.16);color:var(--cp-red)' : 'background:rgba(251,191,36,.16);color:var(--cp-amb)'}`">{{ b.status === 'pending' ? 'running…' : b.status }}</span>
                <template v-else>
                    <button type="button" @click="restore(b)" aria-label="Restore" title="Restore" style="border: 0; background: transparent; color: var(--cp-mut); cursor: pointer; padding: 0"><i class="ti ti-history" style="font-size: 16px" aria-hidden="true"></i></button>
                    <a :href="`/backups/${b.id}/download`" aria-label="Download" style="color: var(--cp-mut); text-decoration: none"><i class="ti ti-download" style="font-size: 16px" aria-hidden="true"></i></a>
                </template>
                <button type="button" @click="destroy(b)" aria-label="Delete" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-trash" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
        </div>
        <div v-else style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 40px; text-align: center; color: var(--cp-dim); font-size: 13px">
            No backups yet.
        </div>

        <!-- schedules -->
        <div style="display: flex; align-items: center; gap: 10px; margin: 24px 0 14px">
            <div style="flex: 1; font-size: 13px; font-weight: 600">Schedules</div>
            <button type="button" @click="showSched = !showSched" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-family: inherit"><i class="ti ti-plus" style="font-size: 14px" aria-hidden="true"></i>New schedule</button>
        </div>

        <form v-if="showSched" @submit.prevent="createSched" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 14px 15px; margin-bottom: 14px; display: grid; grid-template-columns: 110px 1fr 110px 90px auto; gap: 10px; align-items: end">
            <label style="font-size: 11.5px; color: var(--cp-mut)">Type<select v-model="sched.kind" @change="sched.target = '*'" :style="field + ';margin-top:5px;display:block;width:100%'"><option value="site">Site</option><option value="database">Database</option></select></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Target<select v-model="sched.target" :style="field + ';margin-top:5px;display:block;width:100%'"><option value="*">All {{ sched.kind === 'site' ? 'sites' : 'databases' }}</option><option v-for="t in schedTargets" :key="t" :value="t">{{ t }}</option></select></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Frequency<select v-model="sched.frequency" :style="field + ';margin-top:5px;display:block;width:100%'"><option value="daily">Daily</option><option value="weekly">Weekly</option></select></label>
            <label style="font-size: 11.5px; color: var(--cp-mut)">Keep<input v-model.number="sched.retention" type="number" min="1" max="90" :style="field + ';margin-top:5px;width:100%'" /></label>
            <button type="submit" :disabled="sched.processing" style="background: var(--cp-ind); color: #fff; border: 0; border-radius: 9px; padding: 9px 14px; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit">Add</button>
        </form>

        <div v-if="schedules.length" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; overflow: hidden; margin-bottom: 8px">
            <div v-for="(s, i) in schedules" :key="s.id" :style="`display:flex;align-items:center;gap:12px;padding:11px 15px;font-size:13px;${i < schedules.length - 1 ? 'border-bottom:1px solid var(--cp-ln)' : ''}`">
                <i class="ti ti-calendar-repeat" :style="`font-size:16px;color:${s.enabled ? 'var(--cp-vio)' : 'var(--cp-dim)'}`" aria-hidden="true"></i>
                <div style="flex: 1; min-width: 0">
                    <div style="font-weight: 500">{{ s.target === '*' ? `All ${s.kind}s` : s.target }}</div>
                    <div style="font-size: 11px; color: var(--cp-dim)">{{ s.frequency }} · keep {{ s.retention }}<span v-if="s.lastRun"> · last {{ s.lastRun }}</span></div>
                </div>
                <button type="button" @click="toggleSched(s)" :style="`font-size:11px;font-weight:500;padding:3px 10px;border-radius:999px;border:0;cursor:pointer;font-family:inherit;${s.enabled ? 'background:rgba(52,211,153,.16);color:var(--cp-grn)' : 'background:var(--cp-ln2);color:var(--cp-dim)'}`">{{ s.enabled ? 'On' : 'Off' }}</button>
                <button type="button" @click="delSched(s)" aria-label="Delete" style="border: 0; background: transparent; color: var(--cp-dim); cursor: pointer; padding: 0"><i class="ti ti-trash" style="font-size: 15px" aria-hidden="true"></i></button>
            </div>
        </div>
        <div v-else style="font-size: 12px; color: var(--cp-dim); margin-bottom: 8px">No schedules — backups run only when you trigger them.</div>

        <!-- offsite (operator) -->
        <template v-if="isOperator">
            <div style="display: flex; align-items: center; gap: 10px; margin: 24px 0 14px">
                <div style="flex: 1; font-size: 13px; font-weight: 600">Offsite storage</div>
                <span v-if="offsite.configured" style="font-size: 11px; font-weight: 600; color: var(--cp-grn); background: rgba(52,211,153,.16); padding: 3px 10px; border-radius: 999px">{{ offsite.bucket }}</span>
                <button type="button" @click="showOffsite = !showOffsite" style="font-size: 12px; color: var(--cp-mut); background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 8px; padding: 7px 12px; cursor: pointer; font-family: inherit">{{ offsite.configured ? 'Edit' : 'Configure' }}</button>
            </div>
            <div v-if="!offsite.configured && !showOffsite" style="font-size: 12px; color: var(--cp-dim)">Add an S3-compatible bucket (AWS, Backblaze, Wasabi, MinIO…) to mirror every backup offsite automatically.</div>
            <form v-if="showOffsite" @submit.prevent="saveOffsite" style="background: var(--cp-card); border: 1px solid var(--cp-ln); border-radius: 13px; padding: 16px 18px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px">
                <label style="font-size: 11.5px; color: var(--cp-mut)">Bucket<input v-model="off.bucket" :style="field + ';margin-top:5px;width:100%'" placeholder="my-backups" /></label>
                <label style="font-size: 11.5px; color: var(--cp-mut)">Region<input v-model="off.region" :style="field + ';margin-top:5px;width:100%'" placeholder="us-east-1" /></label>
                <label style="font-size: 11.5px; color: var(--cp-mut)">Access key ID<input v-model="off.key" :style="field + ';margin-top:5px;width:100%'" placeholder="AKIA…" /></label>
                <label style="font-size: 11.5px; color: var(--cp-mut)">Secret <span v-if="offsite.hasSecret" style="color: var(--cp-grn)">· set</span><input v-model="off.secret" type="password" :style="field + ';margin-top:5px;width:100%'" :placeholder="offsite.hasSecret ? '•••••••• (keep)' : 'secret'" /></label>
                <label style="font-size: 11.5px; color: var(--cp-mut); grid-column: 1 / -1">Endpoint <span style="color: var(--cp-dim)">(optional — for non-AWS S3)</span><input v-model="off.endpoint" :style="field + ';margin-top:5px;width:100%'" placeholder="https://s3.us-west-002.backblazeb2.com" /></label>
                <div style="grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 8px">
                    <button type="button" @click="showOffsite = false" style="font-size: 13px; color: var(--cp-mut); background: transparent; border: 1px solid var(--cp-ln); border-radius: 9px; padding: 8px 14px; cursor: pointer; font-family: inherit">Cancel</button>
                    <button type="submit" :disabled="off.processing" style="font-size: 13px; color: #fff; background: var(--cp-ind); border: 0; border-radius: 9px; padding: 8px 18px; cursor: pointer; font-family: inherit">Save</button>
                </div>
            </form>
        </template>
    </AppLayout>
</template>
