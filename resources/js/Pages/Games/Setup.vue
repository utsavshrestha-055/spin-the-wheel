<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ rounds: Array, participantCount: Number });
const form = useForm({ name: 'Lucky draw' });
</script>

<template>
    <Head title="New game" />
    <AppLayout>
        <div class="topline"><div><div class="eyebrow">New draw</div><h1>Set up your game</h1><div class="muted">Review the format and start a fresh participant snapshot.</div></div></div>
        <div class="panel" style="max-width:740px"><div class="eyebrow">Game details</div><h2 style="margin:8px 0 22px">The rules are ready</h2>
            <form @submit.prevent="form.post('/games')"><div class="field"><label for="name">Game name</label><input id="name" v-model="form.name" class="input" required maxlength="120"><div v-if="form.errors.name" class="error">{{ form.errors.name }}</div></div>
                <div style="height:20px"></div><div class="field"><label>Elimination format</label><div class="toolbar" style="margin-top:5px"><span v-for="(count, index) in rounds" :key="index" class="pill green">Round {{ index + 1 }} · {{ count }} winners</span></div></div>
                <div class="panel snapshot-check"><div class="eyebrow">Snapshot check</div><div class="snapshot-count"><strong>{{ participantCount.toLocaleString() }}</strong> active participants will be captured when the game starts.</div><div class="muted snapshot-note">Priority players are guaranteed round one selection, with their spins placed at random positions.</div></div>
                <div v-if="form.errors.game" class="error" style="margin-bottom:14px">{{ form.errors.game }}</div><div class="toolbar"><button class="button primary" :disabled="form.processing || participantCount < rounds[0]">Start game <span>↗</span></button><Link href="/dashboard" class="button">Cancel</Link></div>
            </form>
        </div>
    </AppLayout>
</template>
