<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ participants: Object, filters: Object, totals: Object, importFailures: Array, importFailureCount: Number });
const showForm = ref(false);
const editing = ref(null);
const search = ref(props.filters.search || '');
const form = useForm({ name: '', username: '', social_media: '', priority: 0 });
const importForm = useForm({ file: null });
const editForm = useForm({ name: '', username: '', social_media: '', priority: 0, is_active: true });

function submit() {
    form.post('/participants', { onSuccess: () => { form.reset(); showForm.value = false; } });
}

function runSearch() {
    router.get('/participants', { search: search.value || undefined }, { preserveState: true, replace: true });
}

function importCsv() {
    importForm.post('/participants/import', { forceFormData: true, onSuccess: () => importForm.reset() });
}

function editPerson(person) {
    editing.value = person;
    editForm.name = person.name;
    editForm.username = person.username;
    editForm.social_media = person.social_media || '';
    editForm.priority = person.priority;
    editForm.is_active = person.is_active;
}

function updatePerson() {
    editForm.put(`/participants/${editing.value.public_id}`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });
}
</script>

<template>
    <Head title="Participants" />
    <AppLayout>
        <div class="topline">
            <div>
                <div class="eyebrow">People in the draw</div>
                <h1>Participants</h1>
                <div class="muted">Manage your player pool and set priority for round one.</div>
            </div>
            <div class="toolbar">
                <label class="button" for="csv">↑ Import CSV</label>
                <input id="csv" type="file" accept=".csv,text/csv" hidden @change="importForm.file = $event.target.files[0]; importCsv()">
                <button class="button primary" @click="showForm = !showForm">＋ Add participant</button>
            </div>
        </div>

        <div class="muted" style="font-size:11px;margin-top:-20px;margin-bottom:18px">Add the participant's social platform, such as Instagram, TikTok, or another network. CSV imports can use a <code>social_media</code> column.</div>
        <div v-if="importForm.errors.file" class="error" style="margin-bottom:16px">{{ importForm.errors.file }}</div>
        <section v-if="importFailures.length" class="panel import-failures" aria-labelledby="import-failures-title">
            <div class="section-row">
                <div><div class="eyebrow">CSV import</div><h2 id="import-failures-title" style="margin-top:6px">Rows that were not imported ({{ importFailureCount }})</h2></div>
            </div>
            <p v-if="importFailureCount > importFailures.length" class="muted">Showing the first {{ importFailures.length }} failed rows.</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Row</th><th>Username</th><th>Reason</th></tr></thead>
                    <tbody>
                        <tr v-for="failure in importFailures" :key="failure.row">
                            <td>{{ failure.row }}</td>
                            <td>{{ failure.username || '(blank)' }}</td>
                            <td>{{ failure.reason }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="editing" class="panel" style="margin-bottom:17px">
            <div class="section-row">
                <div><div class="eyebrow">Edit player</div><h2 style="margin-top:7px">{{ editing.username }}</h2></div>
                <button class="button" @click="editing = null">Close</button>
            </div>
            <form class="form-row" @submit.prevent="updatePerson">
                <div class="field"><label>Name</label><input v-model="editForm.name" class="input" required></div>
                <div class="field"><label>Username</label><input v-model="editForm.username" class="input" required></div>
                <div class="field"><label>Social media</label><input v-model="editForm.social_media" class="input" maxlength="80" placeholder="Instagram, TikTok, YouTube…"></div>
                <div class="field" style="max-width:140px"><label>Priority</label><input v-model="editForm.priority" class="input" type="number" min="0"></div>
                <label class="pill"><input v-model="editForm.is_active" type="checkbox"> Active</label>
                <button class="button primary" :disabled="editForm.processing">Save changes</button>
            </form>
            <div v-for="error in editForm.errors" :key="error" class="error">{{ error }}</div>
        </div>

        <div v-if="showForm" class="panel" style="margin-bottom:17px">
            <div class="section-row">
                <div><div class="eyebrow">New player</div><h2 style="margin-top:7px">Add participant</h2></div>
                <button class="button" @click="showForm = false">Close</button>
            </div>
            <form class="form-row" @submit.prevent="submit">
                <div class="field"><label>Name</label><input v-model="form.name" class="input" required></div>
                <div class="field"><label>Username</label><input v-model="form.username" class="input" required></div>
                <div class="field"><label>Social media</label><input v-model="form.social_media" class="input" maxlength="80" placeholder="Instagram, TikTok, YouTube…"></div>
                <div class="field" style="max-width:140px"><label>Priority</label><input v-model="form.priority" class="input" type="number" min="0"></div>
                <button class="button primary" :disabled="form.processing">Save player</button>
            </form>
            <div v-for="error in form.errors" :key="error" class="error">{{ error }}</div>
        </div>

        <div class="grid stats-grid" style="margin-bottom:17px">
            <div class="panel stat-card"><div class="stat-label">Total participants</div><div class="stat-value">{{ totals.all.toLocaleString() }}</div></div>
            <div class="panel stat-card"><div class="stat-label">Active in draws</div><div class="stat-value">{{ totals.active.toLocaleString() }}</div></div>
            <div class="panel stat-card"><div class="stat-label">Priority players</div><div class="stat-value">{{ totals.priority.toLocaleString() }}</div></div>
        </div>

        <div class="panel">
            <div class="section-row">
                <div><div class="eyebrow">Player directory</div><h2 style="margin-top:7px">{{ participants.total.toLocaleString() }} people</h2></div>
                <form class="toolbar" @submit.prevent="runSearch">
                    <input v-model="search" class="input" placeholder="Find a player…" style="width:210px">
                    <button class="button">Search</button>
                </form>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Name</th><th>Username</th><th>Social media</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="person in participants.data" :key="person.id">
                            <td class="table-primary-text">{{ person.name }}</td>
                            <td>@{{ person.username }}</td>
                            <td><span v-if="person.social_media" class="pill social-media-pill">◉ &nbsp;{{ person.social_media }}</span><span v-else class="muted">—</span></td>
                            <td><span class="pill" :class="person.is_active ? 'green' : ''">{{ person.is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="toolbar">
                                    <button class="button" @click="editPerson(person)">Edit</button>
                                    <button class="button" @click="router.put(`/participants/${person.public_id}`, { name: person.name, username: person.username, social_media: person.social_media, priority: person.priority, is_active: !person.is_active }, { preserveScroll: true })">{{ person.is_active ? 'Deactivate' : 'Activate' }}</button>
                                    <button class="button danger" title="Delete" @click="router.delete(`/participants/${person.public_id}`, { preserveScroll: true })">×</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination"><Link v-for="link in participants.links" :key="link.label" :href="link.url || '# '" class="button" :class="{ primary: link.active }" :style="{ opacity: link.url ? 1 : .4 }" v-html="link.label" /></div>
        </div>
    </AppLayout>
</template>
