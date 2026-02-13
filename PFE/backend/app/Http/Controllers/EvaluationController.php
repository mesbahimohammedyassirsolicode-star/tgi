<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Evaluation;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluationController extends Controller
{
    public function index(Request $request)
    {
        $query = Evaluation::with(['affectation.module', 'affectation.groupe']);
        if ($request->has('affectation_id')) {
            $query->where('affectation_id', $request->affectation_id);
        }
        $perPage = min((int) $request->get('per_page', 15), 50);
        $paginator = $query->orderBy('date', 'desc')->paginate($perPage);
        return $this->success($paginator->items(), [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'affectation_id' => 'required|exists:affectations,id',
            'item_label' => 'required|string|max:100',
            'type' => 'required|in:cc,efm,projet,stage',
            'max_points' => 'required|numeric|min:0',
            'coefficient' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);
        $evaluation = Evaluation::create($validated);
        return $this->created($evaluation->load('affectation'));
    }

    public function show(Evaluation $evaluation)
    {
        return $this->success($evaluation->load(['notes.stagiaire.user', 'affectation.module']));
    }

    public function update(Request $request, Evaluation $evaluation)
    {
        $validated = $request->validate([
            'item_label' => 'string|max:100',
            'max_points' => 'numeric|min:0',
            'coefficient' => 'numeric|min:0',
            'date' => 'date',
        ]);
        $evaluation->update($validated);
        return $this->success($evaluation->fresh());
    }

    public function destroy(Evaluation $evaluation)
    {
        $evaluation->delete();
        return response()->json(null, 204);
    }

    public function getNotes(Evaluation $evaluation)
    {
        $groupe = $evaluation->affectation->groupe;
        $stagiaires = $groupe->stagiaires()->with('user')->get();
        $notes = $evaluation->notes()->get()->keyBy('stagiaire_id');
        $data = $stagiaires->map(fn ($s) => [
            'stagiaire' => $s,
            'note' => $notes->get($s->id),
            'max_points' => $evaluation->max_points,
        ]);
        return $this->success($data->values()->all());
    }

    public function saveNotes(Request $request, Evaluation $evaluation)
    {
        $validated = $request->validate([
            'notes' => 'required|array',
            'notes.*.stagiaire_id' => 'required|exists:stagiaires,id',
            'notes.*.valeur' => 'required|numeric|min:0|max:'.$evaluation->max_points,
            'notes.*.observation' => 'nullable|string',
        ]);
        $oldNotes = $evaluation->notes()->get()->keyBy('stagiaire_id')->map(fn ($n) => $n->only(['valeur', 'observation']))->all();
        DB::transaction(function () use ($evaluation, $validated) {
            foreach ($validated['notes'] as $noteData) {
                Note::updateOrCreate(
                    ['evaluation_id' => $evaluation->id, 'stagiaire_id' => $noteData['stagiaire_id']],
                    [
                        'valeur' => $noteData['valeur'],
                        'observation' => $noteData['observation'] ?? null,
                    ]
                );
            }
        });
        AuditLog::log('grades.save', Evaluation::class, $evaluation->id, $oldNotes, ['count' => count($validated['notes'])]);
        return $this->success(['message' => 'Notes enregistrées.']);
    }
}
