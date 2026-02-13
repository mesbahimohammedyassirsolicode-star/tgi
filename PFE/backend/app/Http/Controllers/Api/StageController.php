<?php

namespace App\Http\Controllers\Api;

use App\Models\Stage;
use Illuminate\Http\Request;

class StageController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Stage::with(['stagiaire.user', 'groupe', 'formateur.user']);
        if ($request->filled('stagiaire_id')) {
            $query->where('stagiaire_id', $request->stagiaire_id);
        }
        if ($request->filled('groupe_id')) {
            $query->where('groupe_id', $request->groupe_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $perPage = min((int) $request->get('per_page', 15), 50);
        $paginator = $query->orderBy('date_debut', 'desc')->paginate($perPage);
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
            'stagiaire_id' => 'required|exists:stagiaires,id',
            'groupe_id' => 'nullable|exists:groupes,id',
            'formateur_id' => 'nullable|exists:formateurs,id',
            'organisation' => 'required|string|max:255',
            'poste' => 'nullable|string|max:150',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'status' => 'in:en_cours,valide,non_valide',
        ]);
        $validated['status'] = $validated['status'] ?? 'en_cours';
        $stage = Stage::create($validated);
        return $this->created($stage->load(['stagiaire.user', 'groupe', 'formateur.user']));
    }

    public function show(Stage $stage)
    {
        return $this->success($stage->load(['stagiaire.user', 'groupe', 'formateur.user']));
    }

    public function update(Request $request, Stage $stage)
    {
        $validated = $request->validate([
            'groupe_id' => 'nullable|exists:groupes,id',
            'formateur_id' => 'nullable|exists:formateurs,id',
            'organisation' => 'string|max:255',
            'poste' => 'nullable|string|max:150',
            'date_debut' => 'date',
            'date_fin' => 'date|after_or_equal:date_debut',
            'status' => 'in:en_cours,valide,non_valide',
            'note' => 'nullable|numeric|min:0|max:20',
            'observation' => 'nullable|string',
            'rapport_path' => 'nullable|string',
        ]);
        $stage->update($validated);
        return $this->success($stage->fresh(['stagiaire.user', 'groupe', 'formateur.user']));
    }

    public function destroy(Stage $stage)
    {
        $stage->delete();
        return response()->json(null, 204);
    }
}
