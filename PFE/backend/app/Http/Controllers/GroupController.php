<?php

namespace App\Http\Controllers;

use App\Models\Groupe;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     * GET /groups?filiere_id=&year_id=&per_page=
     * Returns { data: Groupe[], meta: { total, current_page, last_page, per_page } }.
     * Groups are from groupes table only (not inferred from users).
     */
    public function index(Request $request)
    {
        $query = Groupe::with(['filiere', 'anneeScolaire']);

        if ($this->isStudentRole($request->user()?->role)) {
            [, $groupeIds] = $this->resolveStudentScope($request);
            if ($groupeIds->isEmpty()) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('id', $groupeIds);
            }
        } elseif ($request->filled('filiere_id')) {
            $query->where('filiere_id', (int) $request->filiere_id);
        }

        if ($request->filled('year_id')) {
            $query->where('annee_scolaire_id', (int) $request->year_id);
        }

        $perPage = min((int) $request->get('per_page', 50), 100);
        $paginator = $query->orderBy('label')->paginate($perPage);
        $items = $paginator->items();
        $list = is_array($items) ? $items : collect($items)->values()->all();

        return $this->success($list, [
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
            'capacity' => 'integer|min:1',
        ]);

        $group = Groupe::create($validated);
        return $this->created($group->load(['filiere', 'anneeScolaire']));
    }

    public function show(Request $request, Groupe $group)
    {
        if ($this->isStudentRole($request->user()?->role)) {
            [, $groupeIds] = $this->resolveStudentScope($request);
            if (! $groupeIds->contains((int) $group->id)) {
                abort(403, 'Acces refuse a ce groupe.');
            }
        }

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
            'capacity' => 'integer|min:1',
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
            'stagiaire_ids.*' => 'exists:stagiaires,id',
        ]);

        $group->stagiaires()->syncWithoutDetaching($validated['stagiaire_ids']);
        return $this->success(['message' => 'Inscriptions enregistrees.']);
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
