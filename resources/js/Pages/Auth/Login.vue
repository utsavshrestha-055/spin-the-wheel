<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import RealHoNiLogo from '../../Components/RealHoNiLogo.vue';

const form = useForm({ email: '', password: '', remember: true });
</script>

<template>
    <Head title="Sign in" />
    <div class="login-page">
        <form class="panel login-card login-brand-card" @submit.prevent="form.post('/login')">
            <div class="login-logo"><RealHoNiLogo light /></div>
            <div class="eyebrow">Admin workspace</div>
            <h1 style="font-size: 32px">Welcome back</h1>
            <p class="muted" style="font-size: 13px; line-height: 1.7">Sign in to manage participants and run your next draw.</p>
            <div class="field"><label for="email">Email address</label><input id="email" v-model="form.email" class="input" type="email" autocomplete="username" required autofocus><div v-if="form.errors.email" class="error">{{ form.errors.email }}</div></div>
            <div class="field"><label for="password">Password</label><input id="password" v-model="form.password" class="input" type="password" autocomplete="current-password" required><div v-if="form.errors.password" class="error">{{ form.errors.password }}</div></div>
            <label class="muted" style="display:flex; gap:9px; align-items:center; font-size:12px; margin: 17px 0 20px"><input v-model="form.remember" type="checkbox"> Keep me signed in</label>
            <button class="button primary" style="width:100%; padding:14px" :disabled="form.processing">{{ form.processing ? 'Signing in…' : 'Sign in' }} <span>↗</span></button>
        </form>
    </div>
</template>
