<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ games: Object, filters: Object });
</script>

<template>
    <Head title="Game history" />
    <AppLayout>
        <div class="topline"><div><div class="eyebrow">Every draw, remembered</div><h1>Game history</h1><div class="muted">Review your finished and active games, round by round.</div></div><Link href="/games/create" class="button primary">＋ New game</Link></div>
        <div class="panel"><div class="section-row"><div><div class="eyebrow">Archive</div><h2 style="margin-top:7px">{{ games.total.toLocaleString() }} games</h2></div><form method="get" action="/history"><select name="status" class="input" style="width:160px" @change="$event.target.form.submit()"><option value="">All statuses</option><option value="completed" :selected="filters.status === 'completed'">Completed</option><option value="in_progress" :selected="filters.status === 'in_progress'">In progress</option><option value="cancelled" :selected="filters.status === 'cancelled'">Cancelled</option></select></form></div>
            <div class="table-wrap"><table><thead><tr><th>Game name</th><th>Players</th><th>Rounds</th><th>Started by</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody><tr v-for="game in games.data" :key="game.id"><td class="table-primary-text">{{ game.name }}</td><td>{{ game.participant_count.toLocaleString() }}</td><td>{{ game.rounds_config.length }}</td><td>{{ game.starter?.name || '—' }}</td><td>{{ new Date(game.created_at).toLocaleDateString() }}</td><td><span class="pill" :class="game.status === 'completed' ? 'green' : 'amber'"><i class="dot"></i>{{ game.status.replace('_', ' ') }}</span></td><td><Link :href="`/history/${game.public_id}`" class="button">Details ↗</Link></td></tr></tbody></table></div>
            <div v-if="!games.data.length" class="muted" style="padding:35px;text-align:center">No games match this filter.</div><div class="pagination"><Link v-for="link in games.links" :key="link.label" :href="link.url || '# '" class="button" :class="{ primary: link.active }" v-html="link.label" /></div>
        </div>
    </AppLayout>
</template>
