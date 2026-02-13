<?php

namespace App\Http\Controllers;

use App\Models\Groupe;
use App\Models\Stagiaire;
use App\Models\Module;
use App\Models\AnneeScolaire;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Groupe::with(['filiere', 'anneeScolaire']);
        if ($request->has('filiere_id')) {
            $query->where('filiere_id', $request->filiere_id);
        }
        if ($request->has('year_id')) {
            $query->where('annee_scolaire_id', $request->year_id);
        }
        $perPage = min((int) $request->get('per_page', 15), 50);
        $paginator = $query->orderBy('label')->paginate($perPage);
        return $this->success($paginator->items(), [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'filiere_id' => 'required|exists:filieres,id',
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
            'label' => 'required|string|max:50',
            'year_level' => 'required|integer|in:1,2',
            'capacity' => 'integer|min:1'
        ]);

        $group = Groupe::create($validated);
        return $this->created($group->load(['filiere', 'anneeScolaire']));
    }

    public function show(Groupe $group)
    {
        return $this->success($group->load(['filiere', 'anneeScolaire', 'stagiaires.user']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Groupe $group)
    {
        $validated = $request->validate([
            'label' => 'string|max:50',
            'year_level' => 'integer|in:1,2',
            'capacity' => 'integer|min:1'
        ]);

        $group->update($validated);
        return $group;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Groupe $group)
    {
        $group->delete();
        return response()->noContent();
    }

    /**
     * Enroll students into the group.
     */
    public function enrollStudents(Request $request, Groupe $group)
    {
        $validated = $request->validate([
            'stagiaire_ids' => 'required|array',
            'stagiaire_ids.*' => 'exists:stagiaires,id'
        ]);

        $group->stagiaires()->syncWithoutDetaching($validated['stagiaire_ids']);
        return $this->success(['message' => 'Inscriptions enregistrées.']);
    }
}
