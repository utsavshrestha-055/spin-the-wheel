<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ game: Object, state: Object });
const state = ref(props.state);
const spinning = ref(false);
const result = ref(null);
const pendingWinner = ref(null);
const rollingName = ref('');
const wheel = ref(null);
const toast = ref('');
const toastType = ref('');
const round = computed(() => state.value.round);
const visibleWinners = computed(() => {
    const winners = round.value?.winners || [];

    return spinning.value && pendingWinner.value ? winners.slice(0, -1) : winners;
});
const completed = computed(() => state.value.game.status === 'completed');
const wheelStyle = computed(() => ({ background: `conic-gradient(${(state.value.round?.segments || []).map((segment, i, all) => `${i % 2 ? '#252f29' : '#425139'} ${i * 360 / all.length}deg ${(i + 1) * 360 / all.length}deg`).join(',')})` }));
const spinDuration = computed(() => state.value.game.spinDurationMs || 6000);
let revealTimer;
let unlockTimer;
let nameTimer;
let toastTimer;

function animateSpin(data) {
    const segments = data.state.round.segments;
    state.value = data.state;
    pendingWinner.value = data.spin.winner;
    const names = segments.map((segment) => segment.username);
    let nameIndex = 0;
    rollingName.value = names[0] || data.spin.winner.username;
    clearInterval(nameTimer);
    nameTimer = setInterval(() => {
        nameIndex = (nameIndex + 1) % Math.max(names.length, 1);
        rollingName.value = names[nameIndex] || data.spin.winner.username;
    }, 85);
    const index = Math.max(0, segments.findIndex((segment) => segment.winner));
    const center = (index + .5) * 360 / Math.max(segments.length, 1);
    const currentRotation = Number(wheel.value.dataset.rotation || 0);
    const alignment = (360 - center - currentRotation % 360 + 360) % 360;
    const target = currentRotation + alignment + 360 * (7 + Math.floor(Math.random() * 3));
    wheel.value.dataset.rotation = String(target);
    wheel.value.style.transform = `rotate(${target}deg)`;
    clearTimeout(revealTimer);
    clearTimeout(unlockTimer);
    revealTimer = setTimeout(() => {
        result.value = pendingWinner.value;
        rollingName.value = pendingWinner.value.username;
        toast.value = `🎉 @${pendingWinner.value.username} is selected!`;
        toastType.value = 'winner';
        pendingWinner.value = null;
        clearInterval(nameTimer);
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toast.value = '';
            toastType.value = '';
        }, 4000);
    }, spinDuration.value);
    unlockTimer = setTimeout(() => {
        spinning.value = false;
    }, spinDuration.value + 750);
}

function spin() {
    if (spinning.value) { return; }
    spinning.value = true;
    result.value = null;
    pendingWinner.value = null;
    rollingName.value = '';
    toast.value = '';
    toastType.value = '';
    clearTimeout(toastTimer);
    router.post(`/games/${props.game.public_id}/spin`, {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            const data = page.props.flash.spin;
            if (data) { animateSpin(data); }
            else { spinning.value = false; }
        },
        onError: (errors) => { spinning.value = false; toastType.value = ''; toast.value = errors.spin || 'Unable to spin right now.'; },
        onCancel: () => { spinning.value = false; },
        onFinish: () => { if (!pendingWinner.value && !result.value) { spinning.value = false; } },
    });
}

function startNextRound() {
    router.post(`/games/${props.game.public_id}/rounds`, { round_number: round.value.number + 1 }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const nextState = page.props.flash.state;
            if (nextState) {
                state.value = nextState;
            }
        },
        onError: (errors) => { toastType.value = ''; toast.value = errors.round || 'Unable to start the next round.'; },
    });
}

onBeforeUnmount(() => { clearTimeout(revealTimer); clearTimeout(unlockTimer); clearTimeout(toastTimer); clearInterval(nameTimer); });
</script>

<template>
    <Head :title="game.name" />
    <AppLayout>
        <div class="topline">
            <div>
                <div class="eyebrow">Game control · {{ state.game.status.replace('_', ' ') }}</div>
                <h1>{{ game.name }}</h1>
                <div class="muted">{{ game.participant_count.toLocaleString() }} players in the snapshot · started {{ new Date(game.started_at).toLocaleString() }}</div>
            </div>
            <div class="toolbar">
                <button v-if="!completed" class="button danger" @click="confirm('Cancel this game?') && router.post(`/games/${game.public_id}/cancel`)">Cancel game</button>
            </div>
        </div>
        <div v-if="toast" class="toast" :class="{ 'winner-toast': toastType === 'winner' }">{{ toast }}</div>
        <div class="panel hero-card">
            <div>
                <div class="eyebrow">Round {{ round?.number || 1 }} of {{ game.rounds_config.length }}</div>
                <h2 style="font-size:25px; margin:10px 0">{{ round?.status === 'completed' ? 'Round complete' : 'Ready for the next spin?' }}</h2>
                <div class="muted" style="font-size:12px">{{ round?.spinCount || 0 }} of {{ round?.winnersCount || game.rounds_config[0] }} winners selected</div>
                <div class="round-track"><span v-for="(_, i) in game.rounds_config" :key="i" class="round-step" :class="{ done: i + 1 < (round?.number || 1) || (i + 1 === round?.number && round?.status === 'completed') }"></span></div>
            </div>
            <div class="hero-count">{{ round?.spinCount || 0 }} / {{ round?.winnersCount || game.rounds_config[0] }}</div>
        </div>
        <div class="wheel-layout">
            <section>
                <div class="wheel-frame">
                    <div class="wheel-pointer"></div>
                    <div ref="wheel" class="wheel" :style="[wheelStyle, { transitionDuration: `${spinDuration}ms` }]">
                        <span v-for="(segment, i) in (round?.segments || [])" :key="`${segment.username}-${i}`" class="wheel-label" :style="{ transform: `rotate(${(i + .5) * 360 / round.segments.length}deg) translateY(-160px)`, color: segment.winner ? '#d7ff8f' : '#aab4a9' }">@{{ segment.username }}</span>
                        <div class="wheel-center"></div>
                    </div>
                </div>
                <div class="ticker"><span class="eyebrow">{{ spinning ? 'Names in motion' : 'Wheel names' }}</span><span v-if="rollingName" :key="rollingName" class="ticker-name rolling-name">@{{ rollingName }}</span><span v-else v-for="(person, i) in (round?.segments || []).slice(0, 5)" :key="`${person.username}-${i}`" class="ticker-name">@{{ person.username }}</span></div>
            </section>
            <section>
                <div class="panel winner-box">
                    <div class="eyebrow">{{ result ? 'Winner selected' : 'Selected this round' }}</div>
                    <template v-if="result">
                        <div class="winner-name">{{ result.name }}</div>
                        <div class="muted">@{{ result.username }}</div>
                        <div class="pill green" style="width:max-content;margin-top:12px">✳ Added to winners</div>
                    </template>
                    <template v-else>
                        <div class="winner-name">{{ round?.spinCount || 0 }}<span style="color:#67736a"> / {{ round?.winnersCount || game.rounds_config[0] }}</span></div>
                        <div class="muted">{{ round?.status === 'completed' ? 'All winners are in.' : 'The next name is waiting.' }}</div>
                    </template>
                </div>
                <div class="panel" style="margin-top:14px">
                    <div class="section-row">
                        <div><div class="eyebrow">Round winners</div><h2 style="margin-top:6px">{{ round?.winners.length || 0 }} selected</h2></div>
                        <span class="pill">{{ Math.max(0, round?.poolCount - (round?.spinCount || 0)) }} remaining pool</span>
                    </div>
                    <div v-if="visibleWinners.length" style="display:grid;gap:9px">
                        <div v-for="winner in visibleWinners" :key="winner.spin" class="user-row">
                            <span class="avatar" style="width:29px;height:29px;border-radius:9px">{{ String(winner.spin).padStart(2, '0') }}</span>
                            <span style="color:#eef2eb;font-size:12px">{{ winner.name }}</span>
                            <span class="muted" style="font:10px 'DM Mono',monospace;margin-left:auto">@{{ winner.username }}</span>
                        </div>
                    </div>
                    <div v-else class="muted" style="font-size:12px">Winners appear here after each spin.</div>
                </div>
                <button v-if="!completed && round?.status === 'active'" class="button primary" style="width:100%;margin-top:15px;padding:15px" :disabled="spinning" @click="spin">{{ spinning ? 'The wheel is spinning…' : '✳  Spin the wheel' }}</button>
                <button v-else-if="!completed" class="button primary" style="width:100%;margin-top:15px;padding:15px" @click="startNextRound">Start round {{ round.number + 1 }} ↗</button>
                <Link v-else :href="`/history/${game.public_id}`" class="button primary" style="width:100%;margin-top:15px">View final results ↗</Link>
            </section>
        </div>
    </AppLayout>
</template>
