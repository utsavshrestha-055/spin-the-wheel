<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name || 'Administrator');
const lightTheme = ref(false);
onMounted(() => {
    lightTheme.value = localStorage.getItem('spinroom-theme') === 'light';
    document.documentElement.dataset.theme = lightTheme.value ? 'light' : 'dark';
});
function toggleTheme() {
    lightTheme.value = !lightTheme.value;
    document.documentElement.dataset.theme = lightTheme.value ? 'light' : 'dark';
    localStorage.setItem('spinroom-theme', lightTheme.value ? 'light' : 'dark');
}
const links = [
    { href: '/dashboard', label: 'Overview', icon: '◈' },
    { href: '/participants', label: 'Participants', icon: '◎' },
    { href: '/games/create', label: 'New game', icon: '↗' },
    { href: '/history', label: 'Game history', icon: '◷' },
];
</script>

<template>
    <div class="shell">
        <aside class="sidebar">
            <Link href="/dashboard" class="brand"><span class="brand-mark">✳</span><span class="brand-name">spinroom</span></Link>
            <div class="nav-label">Workspace</div>
            <Link v-for="link in links" :key="link.href" :href="link.href" class="nav-link" :class="{ active: $page.url.startsWith(link.href) }">
                <span class="nav-icon">{{ link.icon }}</span><span class="nav-text">{{ link.label }}</span>
            </Link>
            <button class="nav-link theme-toggle" type="button" aria-label="Toggle light and dark theme" @click="toggleTheme"><span class="nav-icon">◐</span><span class="nav-text">{{ lightTheme ? 'Dark theme' : 'Light theme' }}</span></button>
            <div class="side-bottom">
                <div class="user-row"><span class="avatar">{{ userName.slice(0, 1).toUpperCase() }}</span><span class="user-name">{{ userName }}</span></div>
                <Link as="button" method="post" href="/logout" class="nav-link" style="margin-top: 12px"><span class="nav-icon">↪</span><span class="nav-text">Sign out</span></Link>
            </div>
        </aside>
        <main class="main"><div class="content"><slot /></div></main>
        <div v-if="$page.props.flash?.success" class="toast">{{ $page.props.flash.success }}</div>
    </div>
</template>
