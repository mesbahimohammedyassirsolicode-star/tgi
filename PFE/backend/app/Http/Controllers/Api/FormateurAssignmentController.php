<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Formateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormateurAssignmentController extends Controller
{
    private function ensureAdminAssignmentAccess(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Non authentifie.');
        }

        $allowed = ['admin', 'directeur', 'secretariat'];
        $role = strtolower((string) $user->role);
        if (in_array($role, $allowed, true)) {
            return;
        }

        foreach ($allowed as $slug) {
            if ($user->hasRole($slug)) {
                return;
            }
        }

        abort(403, 'Acces refuse.');
    }

    /**
     * Admin: assign modules to a teacher for one academic year.
     * Body: { teacher_id, academic_year, module_ids[] }
     */
    public function store(Request $request)
    {
        $this->ensureAdminAssignmentAccess($request);

        $validated = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:formateurs,id'],
            'academic_year' => ['required', 'integer', 'exists:annees_scolaires,id'],
            'module_ids' => ['required', 'array'],
            'module_ids.*' => ['integer', 'exists:modules,id'],
        ]);

        DB::transaction(function () use ($validated) {
            DB::table('teacher_module')
                ->where('teacher_id', (int) $validated['teacher_id'])
                ->where('academic_year', (int) $validated['academic_year'])
                ->delete();

            $rows = collect($validated['module_ids'])
                ->map(fn ($moduleId) => (int) $moduleId)
                ->unique()
                ->values()
                ->map(fn ($moduleId) => [
                    'teacher_id' => (int) $validated['teacher_id'],
                    'module_id' => $moduleId,
                    'academic_year' => (int) $validated['academic_year'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if (! empty($rows)) {
                DB::table('teacher_module')->insert($rows);
            }
        });

        return response()->json(['data' => ['message' => 'Affectations formateur enregistrées.']]);
    }

    /**
     * Admin: get one teacher assignments for an academic year.
     * GET /formateur-assignments/formateurs/{formateur}?academic_year=ID
     */
    public function byTeacher(Request $request, Formateur $formateur)
    {
        $this->ensureAdminAssignmentAccess($request);

        $academicYearId = (int) ($request->query('academic_year') ?: 0);
        if ($academicYearId <= 0) {
            $academicYearId = (int) (AnneeScolaire::where('is_current', true)->value('id') ?? 0);
        }

        if ($academicYearId <= 0) {
            return response()->json(['data' => [
                'teacher_id' => $formateur->id,
                'academic_year' => null,
                'modules' => [],
            ]]);
        }

        $modules = $formateur->modules()
            ->wherePivot('academic_year', $academicYearId)
            ->with(['groupes' => fn ($q) => $q
                ->wherePivot('academic_year', $academicYearId)
                ->with('filiere:id,code,label')
                ->select('groupes.id', 'groupes.label', 'groupes.filiere_id'),
            ])
            ->select('modules.id', 'modules.code', 'modules.label')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'code' => $m->code,
                'label' => $m->label,
                'groupes' => $m->groupes->map(fn ($g) => [
                    'id' => $g->id,
                    'label' => $g->label,
                    'filiere' => $g->filiere ? [
                        'id' => $g->filiere->id,
                        'code' => $g->filiere->code,
                        'label' => $g->filiere->label,
                    ] : null,
                ])->values(),
            ])
            ->values();

        return response()->json(['data' => [
            'teacher_id' => $formateur->id,
            'academic_year' => $academicYearId,
            'modules' => $modules,
        ]]);
    }

    /**
     * Teacher: get own assigned modules for dashboard.
     * GET /formateur-assignments/me?academic_year=ID
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $formateur = $user?->formateur;
        if (! $formateur) {
            return response()->json(['data' => ['modules' => [], 'academic_year' => null]]);
        }

        return $this->byTeacher($request, $formateur);
    }
}
