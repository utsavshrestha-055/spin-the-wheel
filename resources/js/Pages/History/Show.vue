<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
defineProps({ game: Object, starter: Object, rounds: Array });
</script>

<template>
    <Head :title="game.name" />
    <AppLayout>
        <div class="topline"><div><div class="eyebrow">Game archive · {{ game.status.replace('_', ' ') }}</div><h1>{{ game.name }}</h1><div class="muted">Started by {{ starter?.name || 'an administrator' }} · {{ new Date(game.started_at || game.created_at).toLocaleString() }}</div></div><div class="toolbar"><a :href="`/history/${game.public_id}/export`" class="button">↓ Export CSV</a><Link href="/history" class="button">Back to history</Link></div></div>
        <div v-for="round in rounds" :key="round.round_number" class="panel" style="margin-bottom:16px"><div class="section-row"><div><div class="eyebrow">Elimination round {{ round.round_number }}</div><h2 style="margin-top:7px">{{ round.spins.length }} winners</h2></div><span class="pill" :class="round.status === 'completed' ? 'green' : 'amber'">{{ round.status }}</span></div><div class="table-wrap"><table><thead><tr><th>Spin</th><th>Winner</th><th>Username</th><th>Priority</th><th>Selected at</th></tr></thead><tbody><tr v-for="spin in round.spins" :key="spin.id"><td>{{ String(spin.spin_number).padStart(2, '0') }}</td><td class="table-primary-text">{{ spin.winner_name }}</td><td>@{{ spin.winner_username }}</td><td><span v-if="spin.was_priority" class="pill green">Priority</span><span v-else class="muted">—</span></td><td>{{ new Date(spin.spun_at).toLocaleString() }}</td></tr></tbody></table></div></div>
    </AppLayout>
</template>
