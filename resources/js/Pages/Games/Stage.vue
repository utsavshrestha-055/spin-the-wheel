<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import RealHoNiLogo from '../../Components/RealHoNiLogo.vue';

const props = defineProps({ gameId: String, initialState: Object, canManage: Boolean });
const state = ref(props.initialState);
const wheel = ref(null);
const busy = ref(false);
const winner = ref(null);
const rollingName = ref('');
const toast = ref('');
const requestingRound = ref(false);
const confetti = ref(false);
const round = computed(() => state.value?.round);
const game = computed(() => state.value?.game);
const selectedPlayers = computed(() => round.value?.selectedPlayers || []);
const finalRound = computed(() => round.value?.number === game.value?.roundsConfig?.length);
const finalCelebration = computed(() => game.value?.status === 'completed' && !busy.value);
const roundComplete = computed(() => round.value?.status === 'completed' && !finalRound.value && !busy.value);
const colors = ['var(--brand-orange)', 'var(--brand-white)', 'var(--brand-orange-soft)'];
const wheelStyle = computed(() => {
    const segments = round.value?.segments || [];

    return { background: `conic-gradient(${segments.map((segment, index, all) => `${segment.winner ? 'var(--brand-orange-dark)' : colors[index % colors.length]} ${index * 360 / all.length}deg ${(index + 1) * 360 / all.length}deg`).join(',')})` };
});

let stageChannel;
let pollTimer;
let nameTimer;
let revealTimer;
let toastTimer;
let pendingWinner = null;
let spinCount = round.value?.spinCount || 0;
let activeRound = round.value?.number || null;
let wheelRotation = 0;

winner.value = selectedPlayers.value.at(-1) || null;

function applyState(next) {
    const incomingRound = next.round?.number || null;
    const incomingCount = next.round?.spinCount || 0;
    const roundChanged = incomingRound !== activeRound;

    state.value = next;

    if (roundChanged) {
        activeRound = incomingRound;
        spinCount = incomingCount;
        winner.value = null;
        busy.value = false;
        pendingWinner = null;
        rollingName.value = '';
        clearTimeout(revealTimer);
        clearInterval(nameTimer);

        return;
    }

    if (incomingCount <= spinCount) {
        return;
    }

    spinCount = incomingCount;
    pendingWinner = next.round.selectedPlayers?.at(-1) || next.round.winners?.at(-1) || null;
    if (!pendingWinner) {
        return;
    }

    const segments = next.round.segments || [];
    const names = segments.map((segment) => segment.username);
    let nameIndex = 0;
    rollingName.value = names[0] || pendingWinner.username;
    clearInterval(nameTimer);
    nameTimer = setInterval(() => {
        nameIndex = (nameIndex + 1) % Math.max(names.length, 1);
        rollingName.value = names[nameIndex] || pendingWinner.username;
    }, 90);

    const winnerIndex = Math.max(0, segments.findIndex((segment) => segment.winner));
    const segmentCount = Math.max(segments.length, 1);
    const center = (winnerIndex + .5) * 360 / segmentCount;
    const alignment = (360 - center - wheelRotation % 360 + 360) % 360;
    wheelRotation += alignment + 360 * 8;
    busy.value = true;
    confetti.value = false;
    requestAnimationFrame(() => {
        if (wheel.value) {
            wheel.value.style.transform = `rotate(${wheelRotation}deg)`;
        }
    });

    clearTimeout(revealTimer);
    revealTimer = setTimeout(() => {
        winner.value = pendingWinner;
        rollingName.value = pendingWinner.username;
        busy.value = false;
        confetti.value = true;
        toast.value = `🎉 @${pendingWinner.username} is selected!`;
        clearInterval(nameTimer);
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toast.value = '';
            confetti.value = false;
        }, 3800);
    }, next.game.spinDurationMs || 6000);
}

async function refreshState() {
    try {
        const response = await fetch(`/games/${props.gameId}/state`, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        if (response.ok) {
            applyState(await response.json());
        }
    } catch (_) {
        // Continue polling when the shared host is reachable again.
    }
}

async function startNextRound() {
    if (requestingRound.value) {
        return;
    }

    requestingRound.value = true;
    toast.value = '';

    try {
        const response = await fetch(`/games/${props.gameId}/rounds`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ round_number: round.value.number + 1 }),
        });
        const data = await response.json();
        if (!response.ok) {
            toast.value = data.message || 'Unable to start the next round.';
            return;
        }

        applyState(data);
        stageChannel?.postMessage({ type: 'state', state: data });
    } catch (_) {
        toast.value = 'Unable to reach the game server. Try again.';
    } finally {
        requestingRound.value = false;
    }
}

onMounted(() => {
    pollTimer = setInterval(refreshState, 2000);
    if ('BroadcastChannel' in window) {
        stageChannel = new BroadcastChannel(`real-ho-ni-game-${props.gameId}`);
        stageChannel.onmessage = (event) => {
            if (event.data?.state) {
                applyState(event.data.state);
            }
        };
    }
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
    clearInterval(nameTimer);
    clearTimeout(revealTimer);
    clearTimeout(toastTimer);
    stageChannel?.close();
});
</script>

<template>
    <Head :title="`${game?.name || 'Real Ho Ni'} · Live Stage`" />
    <div class="stage-page">
        <header class="stage-head">
            <a href="/dashboard" aria-label="Real Ho Ni home"><RealHoNiLogo light /></a>
            <span class="pill stage-live"><i class="dot"></i> LIVE STAGE</span>
        </header>

        <main v-if="finalCelebration" class="stage-transition">
            <section class="transition-card">
                <div class="eyebrow">The final round is complete</div>
                <h1 class="transition-title">A moment to celebrate!</h1>
                <p class="transition-subtitle">{{ selectedPlayers.length }} winner{{ selectedPlayers.length === 1 ? '' : 's' }} made it all the way.</p>
                <div class="podium-grid">
                    <article v-for="player in selectedPlayers" :key="player.spin" class="podium-card">
                        <span class="stage-slot-rank">WINNER {{ player.spin }}</span>
                        <span class="stage-slot-name">{{ player.name }}</span>
                        <span class="stage-priority">@{{ player.username }}</span>
                    </article>
                </div>
                <a :href="`/history/${gameId}`" class="button primary">View game results</a>
            </section>
        </main>

        <main v-else-if="roundComplete" class="stage-transition">
            <section class="transition-card">
                <div class="eyebrow">Round {{ round.number }} complete</div>
                <h1 class="transition-title">These players move forward.</h1>
                <div class="podium-grid">
                    <article v-for="player in selectedPlayers" :key="player.spin" class="podium-card">
                        <span class="stage-slot-rank">SELECTED {{ player.spin }}</span>
                        <span class="stage-slot-name">{{ player.name }}</span>
                        <span class="stage-priority">@{{ player.username }}<template v-if="player.priority"> · Priority</template></span>
                    </article>
                </div>
                <div class="next-round-card">
                    <div class="eyebrow">Next up</div>
                    <h2 class="transition-next-title">Round {{ round.number + 1 }} starts</h2>
                    <div class="transition-details">
                        <span class="pill">{{ game.roundsConfig[round.number] }} to be selected</span>
                        <span class="pill green">{{ selectedPlayers.length }} players in the pool</span>
                    </div>
                    <button v-if="canManage" class="button primary stage-button" :disabled="requestingRound" @click="startNextRound">
                        {{ requestingRound ? 'Starting round…' : `Start Round ${round.number + 1}` }}
                    </button>
                    <p v-else class="muted">The next round will be started by the game host.</p>
                </div>
            </section>
        </main>

        <main v-else class="stage-main">
            <div class="stage-kicker">
                <span>Round {{ round?.number || 1 }} · {{ busy ? 'Selection in progress' : 'Game control · in progress' }}</span>
                <span>{{ round?.spinCount || 0 }} / {{ round?.winnersCount || '—' }} selected</span>
            </div>
            <h1 class="stage-title">{{ busy ? 'Who will move forward?' : winner ? 'Selected player' : 'Ready for the next player?' }}</h1>
            <div class="stage-wheel-layout">
                <div class="wheel-frame stage-wheel">
                    <div class="wheel-pointer"></div>
                    <div ref="wheel" class="wheel" :style="[wheelStyle, { transitionDuration: `${game?.spinDurationMs || 6000}ms` }]">
                        <span v-for="(segment, index) in (round?.segments || [])" :key="`${segment.username}-${index}`" class="wheel-label" :class="{ 'winner-segment-label': segment.winner, 'light-segment': index % colors.length === 0 }" :style="{ transform: `rotate(${(index + .5) * 360 / round.segments.length}deg) translateY(-165px)` }">@{{ segment.username }}</span>
                        <div class="wheel-center"></div>
                    </div>
                </div>
                <section class="stage-card">
                    <h2 class="stage-card-title">{{ busy ? 'Names in motion' : 'Selected Players' }}</h2>
                    <div v-if="busy" class="stage-current rolling-name">@{{ rollingName }}</div>
                    <div v-else-if="winner" class="stage-current">{{ winner.name }} <span class="muted">@{{ winner.username }}</span></div>
                    <div v-else class="stage-current">Choose the next player</div>
                    <div class="stage-selected-grid">
                        <article v-for="index in (round?.winnersCount || 0)" :key="index" class="stage-slot" :class="{ filled: selectedPlayers[index - 1] }">
                            <template v-if="selectedPlayers[index - 1]">
                                <span class="stage-slot-rank">PLAYER {{ String(index).padStart(2, '0') }}</span>
                                <span class="stage-slot-name">{{ selectedPlayers[index - 1].username }}</span>
                                <span v-if="selectedPlayers[index - 1].priority" class="stage-priority">✳ PRIORITY</span>
                            </template>
                            <template v-else>
                                <span class="stage-slot-rank">PLAYER {{ String(index).padStart(2, '0') }}</span>
                                <span class="muted">Waiting for selection</span>
                            </template>
                        </article>
                    </div>
                    <div class="stage-footer-count">{{ round?.remainingPoolCount ?? 0 }} still in the pool · {{ round?.poolCount ?? 0 }} eligible players</div>
                </section>
            </div>
        </main>

        <div v-if="toast" class="toast winner-toast">{{ toast }}</div>
        <div v-if="confetti" class="confetti" aria-hidden="true">
            <i v-for="index in 48" :key="index" class="confetti-piece" :style="{ left: `${(index * 71) % 100}%`, animationDelay: `${(index % 9) * .07}s` }"></i>
        </div>
        <footer class="stage-foot"><span>REAL HO NI · LIVE DRAW</span><span>{{ game?.participantCount?.toLocaleString() }} PLAYERS IN THIS GAME</span></footer>
    </div>
</template>
