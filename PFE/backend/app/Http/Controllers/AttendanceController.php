<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\Attendance;
use App\Models\Seance;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Seance::with(['affectation.module', 'affectation.groupe', 'affectation.formateur.user']);
        if ($request->has('groupe_id')) {
            $query->whereHas('affectation', fn ($q) => $q->where('groupe_id', $request->groupe_id));
        }
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }
        $perPage = min((int) $request->get('per_page', 15), 50);
        $paginator = $query->orderBy('date')->orderBy('start_time')->paginate($perPage);
        return $this->success($paginator->items(), [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function show(Seance $seance)
    {
        return $this->success($seance->load(['affectation.module', 'affectation.groupe', 'affectation.formateur.user']));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'affectation_id' => 'required|exists:affectations,id',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'salle' => 'nullable|string',
            'type' => 'in:presentiel,distance',
        ]);
        $seance = Seance::create($validated);
        return $this->created($seance->load('affectation'));
    }

    /**
     * Update Seance.
     */
    public function update(Request $request, Seance $seance)
    {
        $validated = $request->validate([
            'date' => 'date',
            'start_time' => 'required',
            'end_time' => 'after:start_time',
            'status' => 'in:planifie,realise,annule',
            'salle' => 'string',
        ]);

        $seance->update($validated);
        return $this->success($seance->fresh());
    }

    public function destroy(Seance $seance)
    {
        $seance->delete();
        return response()->json(null, 204);
    }

    /** Canonical roll call using attendances table (one row per stagiaire per seance). */
    public function getRollCall(Seance $seance)
    {
        $groupe = $seance->affectation->groupe;
        $stagiaires = $groupe->stagiaires()->with('user')->get();
        $attendances = $seance->attendances()->get()->keyBy('stagiaire_id');
        $rows = $stagiaires->map(fn ($s) => [
            'stagiaire' => $s,
            'attendance' => $attendances->get($s->id),
            'status' => $attendances->get($s->id)?->status ?? 'present',
        ]);
        return $this->success($rows);
    }

    public function submitRollCall(Request $request, Seance $seance)
    {
        $validated = $request->validate([
            'attendances' => 'required|array',
            'attendances.*.stagiaire_id' => 'required|exists:stagiaires,id',
            'attendances.*.status' => 'required|in:present,absent,retard',
            'attendances.*.retard_minutes' => 'nullable|integer|min:0',
            'attendances.*.justifie' => 'nullable|boolean',
            'attendances.*.motif' => 'nullable|string|max:255',
        ]);
        DB::transaction(function () use ($seance, $validated) {
            foreach ($validated['attendances'] as $row) {
                Attendance::updateOrCreate(
                    ['seance_id' => $seance->id, 'stagiaire_id' => $row['stagiaire_id']],
                    [
                        'status' => $row['status'],
                        'retard_minutes' => $row['retard_minutes'] ?? 0,
                        'justifie' => $row['justifie'] ?? false,
                        'motif' => $row['motif'] ?? null,
                    ]
                );
            }
            if ($seance->status === 'planifie') {
                $seance->update(['status' => 'realise']);
            }
        });
        AuditLog::log('attendance.mark', Seance::class, $seance->id, null, ['count' => count($validated['attendances'])]);
        return $this->success(['message' => 'Présences enregistrées.']);
    }

    // --- Legacy Absences (optional) ---

    /**
     * Get absences for a specific seance (Roll call view).
     */
    public function getAbsencesForSeance(Seance $seance)
    {
        // Return list of students in the group with their absence status for this seance
        // If absence record exists, return it. If not, they are present.
        
        $groupe = $seance->affectation->groupe;
        $stagiaires = $groupe->stagiaires()->get();

        $absences = $seance->absences()->get()->keyBy('stagiaire_id');

        $data = $stagiaires->map(fn ($s) => [
            'stagiaire' => $s->load('user'),
            'is_absent' => (bool) $absences->get($s->id),
            'absence_details' => $absences->get($s->id),
        ]);
        return $this->success($data->values()->all());
    }

    /**
     * Mark absences for a seance (Submit Roll Call).
     */
    public function markAbsences(Request $request, Seance $seance)
    {
        $validated = $request->validate([
            'absences' => 'required|array',
            'absences.*.stagiaire_id' => 'required|exists:stagiaires,id',
            'absences.*.is_absent' => 'required|boolean',
            'absences.*.retard_minutes' => 'nullable|integer',
            'absences.*.motif' => 'nullable|string',
        ]);

        DB::transaction(function () use ($seance, $validated) {
            foreach ($validated['absences'] as $record) {
                if ($record['is_absent'] || ($record['retard_minutes'] ?? 0) > 0) {
                    Absence::updateOrCreate(
                        ['seance_id' => $seance->id, 'stagiaire_id' => $record['stagiaire_id']],
                        [
                            'justifie' => false, // Default unless justification provided
                            'retard_minutes' => $record['retard_minutes'] ?? 0,
                            'motif' => $record['motif'] ?? null,
                        ]
                    );
                } else {
                    // If marked present, remove any existing absence record
                    Absence::where('seance_id', $seance->id)
                           ->where('stagiaire_id', $record['stagiaire_id'])
                           ->delete();
                }
            }
            
            // Mark seance as 'realise' logic could go here if needed
            if ($seance->status === 'planifie') {
                $seance->update(['status' => 'realise']);
            }
        });

        AuditLog::log('attendance.mark_legacy', Seance::class, $seance->id, null, ['count' => count($validated['absences'])]);
        return $this->success(['message' => 'Absences enregistrées.']);
    }

    /**
     * Update specific absence (e.g., Justification).
     */
    public function updateAbsence(Request $request, Absence $absence)
    {
        $validated = $request->validate([
            'justifie' => 'boolean',
            'motif' => 'nullable|string',
            'justification_doc' => 'nullable|string', // Path to uploaded file
        ]);

        $absence->update($validated);
        return $this->success($absence->fresh());
    }
}
