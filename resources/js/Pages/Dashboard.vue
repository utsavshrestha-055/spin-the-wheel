<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

defineProps({ stats: Object, activeGame: Object, recentGames: Array, user: Object });
</script>

<template>
    <Head title="Overview" />
    <AppLayout>
        <div class="topline"><div><div class="eyebrow">{{ new Date().toLocaleDateString('en', { weekday: 'long', month: 'long', day: 'numeric' }) }}</div><h1>Good to see you, {{ user.name.split(' ')[0] }}.</h1><div class="muted">Your next big moment starts here.</div></div><Link href="/games/create" class="button primary">✳ <span>Start a game</span></Link></div>
        <div class="grid stats-grid">
            <div class="panel stat-card"><div class="stat-label">Active participants</div><div class="stat-value">{{ stats.participants.toLocaleString() }}</div><div class="muted" style="font-size:11px">Ready for the next draw</div></div>
            <div class="panel stat-card"><div class="stat-label">Priority players</div><div class="stat-value">{{ stats.priority.toLocaleString() }}</div><div class="muted" style="font-size:11px">Guaranteed into round one</div></div>
            <div class="panel stat-card"><div class="stat-label">Completed games</div><div class="stat-value">{{ String(stats.completedGames).padStart(2, '0') }}</div><div class="muted" style="font-size:11px">Saved in your history</div></div>
        </div>
        <div v-if="activeGame" class="panel hero-card">
            <div><div class="eyebrow">Live game · in progress</div><h2 style="font-size:24px; margin:10px 0">{{ activeGame.name }}</h2><div class="muted" style="font-size:12px">{{ activeGame.participant_count.toLocaleString() }} players in the original draw</div><div class="round-track"><span v-for="(_, index) in activeGame.rounds_config" :key="index" class="round-step" :class="{ done: index === 0 }"></span></div></div>
            <Link :href="`/games/${activeGame.public_id}`" class="button primary">Open control panel ↗</Link>
        </div>
        <div class="panel">
            <div class="section-row"><div><div class="eyebrow">Recent activity</div><h2 style="margin-top:7px">Latest games</h2></div><Link href="/history" class="button">View history ↗</Link></div>
            <div v-if="recentGames.length" class="table-wrap"><table><thead><tr><th>Game</th><th>Started by</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody><tr v-for="game in recentGames" :key="game.id"><td class="table-primary-text">{{ game.name }}</td><td>{{ game.starter?.name || '—' }}</td><td>{{ new Date(game.created_at).toLocaleDateString() }}</td><td><span class="pill" :class="game.status === 'completed' ? 'green' : 'amber'"><i class="dot"></i>{{ game.status.replace('_', ' ') }}</span></td><td><Link :href="`/history/${game.public_id}`">↗</Link></td></tr></tbody></table></div>
            <div v-else class="muted" style="padding:25px 10px; font-size:13px">No games yet. Start a game to make your first winner moment.</div>
        </div>
    </AppLayout>
</template>
