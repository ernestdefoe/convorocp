<script setup>
import { ref, onMounted } from 'vue';

const light = ref(false);
onMounted(() => {
    light.value = document.documentElement.classList.contains('light');
});
function toggle() {
    light.value = !light.value;
    document.documentElement.classList.toggle('light', light.value);
    try {
        localStorage.setItem('cp-theme', light.value ? 'light' : 'dark');
    } catch (e) {}
}
</script>

<template>
    <button type="button" @click="toggle" :aria-label="light ? 'Switch to dark mode' : 'Switch to light mode'"
        style="display: flex; align-items: center; gap: 9px; width: 100%; padding: 8px 10px; border: 1px solid var(--cp-ln); border-radius: 10px; background: var(--cp-card); color: var(--cp-mut); font-size: 12.5px; cursor: pointer; font-family: inherit">
        <i class="ti" :class="light ? 'ti-moon' : 'ti-sun'" style="font-size: 16px" aria-hidden="true"></i>
        {{ light ? 'Dark mode' : 'Light mode' }}
    </button>
</template>
