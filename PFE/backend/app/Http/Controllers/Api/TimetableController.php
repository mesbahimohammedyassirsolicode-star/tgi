<?php

namespace App\Http\Controllers\Api;

use App\Models\Seance;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Timetable (emploi du temps): weekly seances.
 * Seances must have affectation_id. Stagiaire: filière + groupe(s). Date filter applied after scope.
 */
class TimetableController extends BaseApiController
{
    private function emptyPayload(Carbon $start, Carbon $end): array
    {
        return [
            'week_start' => $start->format('Y-m-d'),
            'week_end' => $end->format('Y-m-d'),
            'seances' => [],
            'by_date' => (object) [],
        ];
    }

    /**
     * GET /timetable or /emploi-du-temps
     * Query: week_start (Y-m-d). Always returns JSON with week_start, week_end, seances, by_date.
     */
    public function index(Request $request)
    {
        try {
            $weekStart = $request->get('week_start');
            $date = $weekStart ? Carbon::parse($weekStart) : Carbon::now()->startOfWeek(Carbon::MONDAY);
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->addDays(6)->endOfDay();
        } catch (\Throwable) {
            $start = Carbon::now()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $end = $start->copy()->addDays(6)->endOfDay();
            return $this->success($this->emptyPayload($start, $end));
        }

        $user = $request->user();
        if (! $user) {
            return $this->success($this->emptyPayload($start, $end));
        }

        // Base: only seances with mandatory affectation_id; load relations
        $query = Seance::query()
            ->with(['affectation.module', 'affectation.groupe', 'affectation.formateur.user', 'filiere', 'groupe'])
            ->whereNotNull('affectation_id')
            ->orderBy('date')
            ->orderBy('start_time');

        if ($this->isStudentRole($user->role)) {
            [$filiereId, $groupeIds] = $this->resolveStudentScope($request);
            if ($filiereId === null || $groupeIds->isEmpty()) {
                return $this->success($this->emptyPayload($start, $end));
            }

            $query->where(function ($q) use ($filiereId, $groupeIds) {
                $q->where('seances.filiere_id', $filiereId);
                if ($groupeIds->isNotEmpty()) {
                    $q->where(function ($q2) use ($groupeIds) {
                        $q2->whereIn('seances.groupe_id', $groupeIds)
                            ->orWhereHas('affectation', fn ($a) => $a->whereIn('groupe_id', $groupeIds));
                    });
                }
            });
        } else {
            if ($request->filled('groupe_id')) {
                $query->where(function ($q) use ($request) {
                    $gid = (int) $request->groupe_id;
                    $q->where('seances.groupe_id', $gid)
                        ->orWhereHas('affectation', fn ($a) => $a->where('groupe_id', $gid));
                });
            }
            if ($request->filled('formateur_id')) {
                $query->whereHas('affectation', fn ($q) => $q->where('formateur_id', $request->formateur_id));
            }
        }

        try {
            $allForScope = (clone $query)->get();
        } catch (\Throwable) {
            return $this->success($this->emptyPayload($start, $end));
        }

        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d');

        $seancesForWeek = $allForScope->filter(function ($s) use ($startStr, $endStr) {
            $d = $s->date;
            if (is_object($d)) {
                $d = $d->format('Y-m-d');
            }
            return $d >= $startStr && $d <= $endStr;
        });

        if ($this->isStudentRole($user->role) && $seancesForWeek->isEmpty() && $allForScope->isNotEmpty()) {
            $firstDate = $allForScope->min('date');
            if ($firstDate) {
                $d = is_object($firstDate) ? Carbon::parse($firstDate) : Carbon::parse($firstDate);
                $start = $d->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
                $end = $start->copy()->addDays(6)->endOfDay();
                $startStr = $start->format('Y-m-d');
                $endStr = $end->format('Y-m-d');
                $seancesForWeek = $allForScope->filter(function ($s) use ($startStr, $endStr) {
                    $d = $s->date;
                    if (is_object($d)) {
                        $d = $d->format('Y-m-d');
                    }
                    return $d >= $startStr && $d <= $endStr;
                });
            }
        }

        $byDate = $seancesForWeek->groupBy(function ($s) {
            $d = $s->date;
            return is_object($d) ? $d->format('Y-m-d') : (string) $d;
        })->map(fn ($items) => $items->values()->all())->toArray();

        return $this->success([
            'week_start' => $start->format('Y-m-d'),
            'week_end' => $end->format('Y-m-d'),
            'seances' => $seancesForWeek->values()->all(),
            'by_date' => $byDate,
        ]);
    }

    private function isStudentRole(?string $role): bool
    {
        return in_array(strtolower((string) $role), ['stagiaire', 'student', 'stagiair'], true);
    }

    /**
     * @return array{0:?int,1:\Illuminate\Support\Collection}
     */
    private function resolveStudentScope(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return [null, collect()];
        }

        $user->loadMissing('stagiaire.groupes');
        $stagiaire = $user->stagiaire;
        if (! $stagiaire) {
            return [null, collect()];
        }

        $filiereId = $stagiaire->getFiliereIdForScope();
        if ($filiereId === null) {
            return [null, collect()];
        }

        $groupeIds = $stagiaire->getGroupeIdsInFiliere($filiereId);
        if ($groupeIds->isEmpty() && $stagiaire->groupe_id) {
            $groupeIds = collect([(int) $stagiaire->groupe_id]);
        }

        return [(int) $filiereId, $groupeIds->map(fn ($id) => (int) $id)->unique()->values()];
    }
}
