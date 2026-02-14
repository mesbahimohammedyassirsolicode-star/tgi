<?php

namespace App\Http\Controllers;

use App\Models\Affectation;
use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Module::with('filiere');

        if ($this->isStudentRole($request->user()?->role)) {
            [$filiereId, $groupeIds] = $this->resolveStudentScope($request);
            if ($filiereId === null || $groupeIds->isEmpty()) {
                return collect([]);
            }

            $moduleIds = Affectation::query()
                ->whereIn('groupe_id', $groupeIds)
                ->pluck('module_id')
                ->filter()
                ->unique()
                ->values();

            if ($moduleIds->isEmpty()) {
                return collect([]);
            }

            $query->where('filiere_id', $filiereId)
                ->whereIn('id', $moduleIds);
        } elseif ($request->filled('filiere_id')) {
            $query->where('filiere_id', (int) $request->filiere_id);
        }

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'filiere_id' => 'required|exists:filieres,id',
            'code' => 'required|string|max:20',
            'label' => 'required|string|max:150',
            'masse_horaire' => 'required|integer|min:1',
            'coefficient' => 'required|integer|min:1',
            'semester' => 'required|in:S1,S2,S3,S4',
        ]);

        return Module::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Module $module)
    {
        if ($this->isStudentRole($request->user()?->role)) {
            [$filiereId, $groupeIds] = $this->resolveStudentScope($request);
            if ($filiereId === null || $groupeIds->isEmpty() || (int) $module->filiere_id !== $filiereId) {
                abort(403, 'Acces refuse a ce module.');
            }

            $isAssigned = Affectation::query()
                ->where('module_id', $module->id)
                ->whereIn('groupe_id', $groupeIds)
                ->exists();

            if (! $isAssigned) {
                abort(403, 'Acces refuse a ce module.');
            }
        }

        return $module->load(['filiere', 'syllabusItems']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Module $module)
    {
        $validated = $request->validate([
            'filiere_id' => 'exists:filieres,id',
            'code' => 'string|max:20',
            'label' => 'string|max:150',
            'masse_horaire' => 'integer|min:1',
            'coefficient' => 'integer|min:1',
            'semester' => 'in:S1,S2,S3,S4',
        ]);

        $module->update($validated);
        return $module;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Module $module)
    {
        $module->delete();
        return response()->noContent();
    }

    // --- Syllabus Management ---
    public function showSyllabus(Request $request, Module $module)
    {
        if ($this->isStudentRole($request->user()?->role)) {
            [$filiereId, $groupeIds] = $this->resolveStudentScope($request);
            if ($filiereId === null || $groupeIds->isEmpty() || (int) $module->filiere_id !== $filiereId) {
                abort(403, 'Acces refuse a ce module.');
            }

            $isAssigned = Affectation::query()
                ->where('module_id', $module->id)
                ->whereIn('groupe_id', $groupeIds)
                ->exists();

            if (! $isAssigned) {
                abort(403, 'Acces refuse a ce module.');
            }
        }

        return $module->syllabusItems()->orderBy('order')->get();
    }

    public function updateSyllabus(Request $request, Module $module)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.label' => 'required|string',
            'items.*.estimated_hours' => 'required|integer',
            'items.*.order' => 'required|integer',
        ]);

        $module->syllabusItems()->delete();
        $module->syllabusItems()->createMany($validated['items']);

        return response()->json(['message' => 'Syllabus updated successfully', 'items' => $module->syllabusItems]);
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
