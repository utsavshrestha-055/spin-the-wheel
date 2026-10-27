<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import RealHoNiLogo from '../Components/RealHoNiLogo.vue';

const page = usePage();
const props = defineProps({ fullscreen: Boolean });
const userName = computed(() => page.props.auth?.user?.name || 'Administrator');
const sidebarCollapsed = ref(false);
const links = [
    { href: '/dashboard', label: 'Overview', icon: '◈' },
    { href: '/participants', label: 'Participants', icon: '◎' },
    { href: '/games/create', label: 'New game', icon: '↗' },
    { href: '/history', label: 'Game history', icon: '◷' },
];

onMounted(() => {
    sidebarCollapsed.value = window.matchMedia('(max-width: 800px)').matches;
});
</script>

<template>
    <div class="shell" :class="{ fullscreen, 'sidebar-collapsed': sidebarCollapsed, 'sidebar-expanded': !sidebarCollapsed }">
        <aside v-if="!fullscreen" class="sidebar">
            <div class="brand-head">
                <Link href="/dashboard" class="brand"><RealHoNiLogo /></Link>
                <button class="sidebar-toggle" type="button" :aria-expanded="!sidebarCollapsed" :aria-label="sidebarCollapsed ? 'Expand navigation' : 'Collapse navigation'" @click="sidebarCollapsed = !sidebarCollapsed">
                    <span aria-hidden="true">{{ sidebarCollapsed ? '☰' : '‹' }}</span>
                </button>
            </div>
            <div class="nav-label">Workspace</div>
            <Link v-for="link in links" :key="link.href" :href="link.href" class="nav-link" :class="{ active: $page.url.startsWith(link.href) }">
                <span class="nav-icon">{{ link.icon }}</span><span class="nav-text">{{ link.label }}</span>
            </Link>
            <div class="side-bottom">
                <div class="user-row"><span class="avatar">{{ userName.slice(0, 1).toUpperCase() }}</span><span class="user-name">{{ userName }}</span></div>
                <Link as="button" method="post" href="/logout" class="nav-link sign-out"><span class="nav-icon">↪</span><span class="nav-text">Sign out</span></Link>
            </div>
        </aside>
        <button v-if="!fullscreen && sidebarCollapsed" class="mobile-nav-open" type="button" aria-label="Open navigation" @click="sidebarCollapsed = false">☰</button>
        <button v-if="!fullscreen && !sidebarCollapsed" class="sidebar-scrim" type="button" aria-label="Close navigation" @click="sidebarCollapsed = true"></button>
        <main class="main"><div class="content"><slot /></div></main>
        <div v-if="$page.props.flash?.success" class="toast success-toast">{{ $page.props.flash.success }}</div>
    </div>
</template>
