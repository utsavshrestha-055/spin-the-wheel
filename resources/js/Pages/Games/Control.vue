<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ game: Object, state: Object, participants: Array });
const state = ref(props.state);
const spinning = ref(false);
const result = ref(null);
const pendingWinner = ref(null);
const rollingName = ref('');
const wheel = ref(null);
const toast = ref('');
const toastType = ref('');
const cancelling = ref(false);
const participantCarousel = ref(null);
const participantCarouselPaused = ref(false);
const round = computed(() => state.value.round);
const visibleWinners = computed(() => {
    const winners = round.value?.winners || [];

    return spinning.value && pendingWinner.value ? winners.slice(0, -1) : winners;
});
const completed = computed(() => state.value.game.status === 'completed');
const canCancel = computed(() => ['draft', 'in_progress'].includes(state.value.game.status));
const wheelStyle = computed(() => {
    const colors = ['var(--brand-orange)', 'var(--brand-white)', 'var(--brand-orange-soft)'];
    const segments = state.value.round?.segments || [];

    return { background: `conic-gradient(${segments.map((segment, index, all) => `${segment.winner ? 'var(--brand-orange-dark)' : colors[index % colors.length]} ${index * 360 / all.length}deg ${(index + 1) * 360 / all.length}deg`).join(',')})` };
});
const spinDuration = computed(() => state.value.game.spinDurationMs || 6000);
let stageChannel;
let revealTimer;
let unlockTimer;
let nameTimer;
let toastTimer;
let participantScrollTimer;

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
    stageChannel?.postMessage({ type: 'spin-resolved', spin: data.spin, state: data.state });
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
                stageChannel?.postMessage({ type: 'state', state: nextState });
            }
        },
        onError: (errors) => { toastType.value = ''; toast.value = errors.round || 'Unable to start the next round.'; },
    });
}

function cancelGame() {
    if (!window.confirm('Cancel this game?')) {
        return;
    }

    cancelling.value = true;
    router.post(`/games/${props.game.public_id}/cancel`, {}, {
        onError: (errors) => {
            toastType.value = '';
            toast.value = errors.game || 'Unable to cancel this game.';
        },
        onFinish: () => { cancelling.value = false; },
    });
}

function advanceParticipantCarousel() {
    const carousel = participantCarousel.value;
    if (!carousel || carousel.scrollWidth <= carousel.clientWidth) {
        return;
    }

    const maxScroll = carousel.scrollWidth - carousel.clientWidth;
    if (carousel.scrollLeft >= maxScroll - 4) {
        carousel.scrollTo({ left: 0, behavior: 'smooth' });
        return;
    }

    const cardWidth = carousel.querySelector('.participant-carousel-card')?.getBoundingClientRect().width || 190;
    carousel.scrollBy({ left: cardWidth + 10, behavior: 'smooth' });
}

onMounted(() => {
    if ('BroadcastChannel' in window) {
        stageChannel = new BroadcastChannel(`real-ho-ni-game-${props.game.public_id}`);
    }
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        participantScrollTimer = setInterval(() => {
            if (!participantCarouselPaused.value) {
                advanceParticipantCarousel();
            }
        }, 1400);
    }
});
onBeforeUnmount(() => { clearTimeout(revealTimer); clearTimeout(unlockTimer); clearTimeout(toastTimer); clearInterval(nameTimer); clearInterval(participantScrollTimer); stageChannel?.close(); });
</script>

<template>
    <Head :title="game.name" />
    <AppLayout fullscreen>
        <div class="game-control">
        <div class="topline game-topline">
            <div>
                <div class="eyebrow">Game control · {{ state.game.status.replace('_', ' ') }}</div>
                <h1>{{ game.name }}</h1>
                <div class="muted">{{ game.participant_count.toLocaleString() }} players in the snapshot · started {{ new Date(game.started_at).toLocaleString() }}</div>
            </div>
            <div class="toolbar">
                <button v-if="canCancel" class="button danger" :disabled="cancelling" @click="cancelGame">{{ cancelling ? 'Cancelling…' : 'Cancel game' }}</button>
                <a :href="`/games/${game.public_id}/stage`" target="_blank" rel="noopener" class="button">Open live stage ↗</a>
            </div>
        </div>
        <div v-if="toast" class="toast" :class="{ 'winner-toast': toastType === 'winner' }" role="status" aria-live="polite">{{ toast }}</div>
        <div class="panel hero-card">
            <div>
                <div class="eyebrow">Round {{ round?.number || 1 }} of {{ game.rounds_config.length }}</div>
                <h2 style="font-size:25px; margin:10px 0">{{ round?.status === 'completed' ? 'Round complete' : 'Ready for the next spin?' }}</h2>
                <div class="muted" style="font-size:12px">{{ round?.spinCount || 0 }} of {{ round?.winnersCount || game.rounds_config[0] }} winners selected</div>
                <div class="round-track"><span v-for="(_, i) in game.rounds_config" :key="i" class="round-step" :class="{ done: i + 1 < (round?.number || 1) || (i + 1 === round?.number && round?.status === 'completed') }"></span></div>
            </div>
            <div class="hero-count">{{ round?.spinCount || 0 }} / {{ round?.winnersCount || game.rounds_config[0] }}</div>
        </div>
        <section class="panel participant-carousel-panel" aria-label="All game participants">
            <div class="section-row participant-carousel-heading">
                <div><div class="eyebrow">Full game snapshot</div><h2 style="margin-top:6px">All participants <span class="muted">({{ participants.length }})</span></h2></div>
                <div class="toolbar">
                    <button class="button participant-carousel-toggle" type="button" @click="participantCarouselPaused = !participantCarouselPaused">{{ participantCarouselPaused ? 'Resume' : 'Pause' }}</button>
                </div>
            </div>
            <div ref="participantCarousel" class="participant-carousel">
                <article v-for="person in participants" :key="person.username" class="participant-carousel-card">
                    <span class="participant-carousel-name">{{ person.name }}</span>
                    <span class="participant-carousel-username">@{{ person.username }}</span>
                </article>
            </div>
        </section>
        <div class="wheel-layout game-wheel-layout">
            <section>
                <div class="wheel-frame game-wheel-frame">
                    <div class="wheel-pointer"></div>
                    <div ref="wheel" class="wheel" :style="[wheelStyle, { transitionDuration: `${spinDuration}ms` }]">
                        <div class="wheel-center"></div>
                    </div>
                    <div class="game-wheel-name-panel" aria-live="polite">
                        <span class="game-wheel-name-heading">{{ spinning ? 'Names in motion' : 'Wheel names' }}</span>
                        <span v-if="rollingName" :key="rollingName" class="ticker-name rolling-name">@{{ rollingName }}</span>
                        <div v-else class="game-wheel-name-list">
                            <span v-for="(person, i) in (round?.segments || []).slice(0, 4)" :key="`${person.username}-${i}`" class="ticker-name">@{{ person.username }}</span>
                        </div>
                    </div>
                </div>
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
                        <div class="winner-name">{{ round?.spinCount || 0 }}<span class="count-total"> / {{ round?.winnersCount || game.rounds_config[0] }}</span></div>
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
                            <span class="selected-name">{{ winner.name }}</span>
                            <span class="muted" style="font:10px 'DM Mono',monospace;margin-left:auto">@{{ winner.username }}</span>
                        </div>
                    </div>
                    <div v-else class="muted" style="font-size:12px">Winners appear here after each spin.</div>
                </div>
                <button v-if="!completed && round?.status === 'active'" class="button primary game-action-button" :disabled="spinning" @click="spin">{{ spinning ? 'The wheel is spinning…' : '✳  Spin the wheel' }}</button>
                <button v-else-if="!completed" class="button primary game-action-button" @click="startNextRound">Start round {{ round.number + 1 }} ↗</button>
                <Link v-else :href="`/history/${game.public_id}`" class="button primary game-action-button">View final results ↗</Link>
            </section>
        </div>
        </div>
    </AppLayout>
</template>
