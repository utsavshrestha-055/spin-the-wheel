<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportParticipantsRequest;
use App\Http\Requests\StoreParticipantRequest;
use App\Http\Requests\UpdateParticipantRequest;
use App\Models\Participant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ParticipantController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Participant::class);
        $query = Participant::query()->when($request->filled('search'), function ($query) use ($request): void {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('username', 'like', $term));
        })->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))->orderByDesc('priority')->orderBy('name');

        return Inertia::render('Participants/Index', ['participants' => $query->paginate(15)->withQueryString(), 'filters' => $request->only(['search', 'active']), 'totals' => ['all' => Participant::count(), 'active' => Participant::where('is_active', true)->count(), 'priority' => Participant::where('is_active', true)->where('priority', '>', 0)->count()], 'importFailures' => $request->session()->get('import_failures', []), 'importFailureCount' => $request->session()->get('import_failure_count', 0)]);
    }

    public function store(StoreParticipantRequest $request): RedirectResponse
    {
        $this->authorize('create', Participant::class);
        $attributes = $request->validated();
        Participant::query()->create([...$attributes, 'is_active' => true]);

        return back()->with('success', 'Participant added.');
    }

    public function update(UpdateParticipantRequest $request, Participant $participant): RedirectResponse
    {
        $this->authorize('update', $participant);
        $attributes = $request->validated();
        $participant->update($attributes);

        return back()->with('success', 'Participant updated.');
    }

    public function destroy(Participant $participant): RedirectResponse
    {
        $this->authorize('delete', $participant);
        if (DB::table('game_participants')->where('participant_id', $participant->id)->exists()) {
            return back()->withErrors(['participant' => 'This participant belongs to a game snapshot and cannot be deleted. Deactivate them instead.']);
        }
        $participant->delete();

        return back()->with('success', 'Participant deleted.');
    }

    public function import(ImportParticipantsRequest $request): RedirectResponse
    {
        $this->authorize('create', Participant::class);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return back()->withErrors(['file' => 'Could not read this CSV file.']);
        }
        $headers = fgetcsv($handle);
        if (is_array($headers) && isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }
        if (! $headers || ! in_array('username', $headers, true)) {
            fclose($handle);

            return back()->withErrors(['file' => 'CSV must include a username column; name, social_media, and priority are optional.']);
        }
        $indices = ['username' => array_search('username', $headers, true), 'name' => array_search('name', $headers, true), 'social_media' => array_search('social_media', $headers, true), 'priority' => array_search('priority', $headers, true)];
        $valueAt = static fn (array $row, string $column, string $fallback = ''): string => $indices[$column] === false ? $fallback : trim((string) ($row[$indices[$column]] ?? $fallback));
        $updateColumns = ['name', 'priority', 'is_active', 'updated_at'];
        if ($indices['social_media'] !== false) {
            $updateColumns[] = 'social_media';
        }
        $batch = [];
        $processed = 0;
        $skipped = 0;
        $failures = [];
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $username = $valueAt($row, 'username');
            if ($username === '') {
                $skipped++;
                if (count($failures) < 100) {
                    $failures[] = ['row' => $rowNumber, 'username' => '', 'reason' => 'Username is blank.'];
                }

                continue;
            }
            if (Str::length($username) > 225) {
                $skipped++;
                if (count($failures) < 100) {
                    $failures[] = ['row' => $rowNumber, 'username' => $username, 'reason' => 'Username is longer than 225 characters.'];
                }

                continue;
            }
            $batch[] = ['public_id' => (string) Str::ulid(), 'username' => $username, 'name' => $valueAt($row, 'name', $username) ?: $username, 'social_media' => $valueAt($row, 'social_media') ?: null, 'priority' => min(4294967295, max(0, (int) $valueAt($row, 'priority', '0'))), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()];
            if (count($batch) === 500) {
                $processed += $this->upsertImportBatch($batch, $updateColumns);
                $batch = [];
            }
        }
        fclose($handle);
        if ($batch !== []) {
            $processed += $this->upsertImportBatch($batch, $updateColumns);
        }

        $message = number_format($processed).' participant rows imported or updated.';
        if ($skipped > 0) {
            $message .= ' '.number_format($skipped).' '.($skipped === 1 ? 'row' : 'rows').' skipped. See the failed rows below.';
        }

        return back()->with('success', $message)->with('import_failures', $failures)->with('import_failure_count', $skipped);
    }

    /** @param array<int, array<string, mixed>> $batch */
    private function upsertImportBatch(array $batch, array $updateColumns): int
    {
        $batch = array_values(array_column($batch, null, 'username'));
        Participant::query()->upsert($batch, ['username'], $updateColumns);

        return count($batch);
    }
}
