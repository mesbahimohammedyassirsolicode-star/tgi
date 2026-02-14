<?php

namespace App\Http\Controllers;

use App\Models\AnneeScolaire;
use App\Models\Filiere;
use App\Models\Formateur;
use App\Models\Groupe;
use App\Models\Note;
use App\Models\Seance;
use App\Models\Stagiaire;
use App\Models\User;
use App\Services\AttendanceRiskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private AttendanceRiskService $attendanceRiskService
    ) {}

    /**
     * GET /api/v1/dashboard
     * Single endpoint: returns role-specific payload. Backend decides content.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role;

        if ($role === null || $role === '') {
            return response()->json(['message' => 'Rôle utilisateur non défini.'], 403);
        }

        try {
            $payload = match ($role) {
                'admin' => $this->adminPayload(),
                'formateur', 'teacher' => $this->formateurPayload($user),
                'stagiaire', 'student', 'stagiair' => $this->stagiairePayload($user),
                'parent' => $this->parentPayload($user),
                default => null,
            };
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'Erreur lors du chargement du tableau de bord.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        if ($payload === null) {
            return response()->json(['message' => 'Rôle non reconnu pour le tableau de bord.'], 403);
        }

        return response()->json([
            'role' => (string) $role,
            'data' => $payload,
        ]);
    }

    private function adminPayload(): array
    {
        $stats = [
            'total_students' => Stagiaire::where('status', 'actif')->count(),
            'total_teachers' => Formateur::count(),
            'total_filieres' => Filiere::count(),
            'total_groupes' => Groupe::count(),
        ];

        $studentsPerFiliere = Filiere::orderBy('code')
            ->get()
            ->map(function (Filiere $f) {
                $groupeIds = $f->groupes()->pluck('id');
                $value = $groupeIds->isEmpty()
                    ? 0
                    : (int) DB::table('groupe_stagiaire')
                        ->whereIn('groupe_id', $groupeIds)
                        ->selectRaw('COUNT(DISTINCT stagiaire_id) as c')
                        ->value('c');
                return ['name' => $f->code, 'value' => $value];
            })
            ->values()
            ->all();

        $quick_actions = [
            ['label' => 'Gérer les utilisateurs', 'path' => '/users'],
            ['label' => 'Filières et groupes', 'path' => '/academic/filieres'],
            ['label' => 'Groupes', 'path' => '/groups'],
        ];

        return [
            'stats' => $stats,
            'charts' => [
                'students_per_filiere' => $studentsPerFiliere,
            ],
            'quick_actions' => $quick_actions,
        ];
    }

    private function formateurPayload(User $user): array
    {
        $formateur = $user->formateur;
        if (! $formateur) {
            return ['message' => 'Profil formateur non trouvé.', 'todays_sessions' => [], 'assigned_modules' => [], 'quick_actions' => []];
        }

        $today = now()->toDateString();
        $affectationIds = $formateur->affectations()->pluck('id');

        $todaysSessions = Seance::whereIn('affectation_id', $affectationIds)
            ->where('date', $today)
            ->with(['affectation.module', 'groupe', 'filiere'])
            ->orderBy('start_time')
            ->get()
            ->map(fn (Seance $s) => [
                'id' => $s->id,
                'module' => $s->affectation?->module?->label,
                'groupe' => $s->groupe?->label,
                'filiere' => $s->filiere?->code,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
            ])
            ->values()
            ->all();

        $academicYearId = (int) (AnneeScolaire::where('is_current', true)->value('id') ?? 0);
        $assignedModules = $academicYearId > 0
            ? $formateur->modules()
                ->wherePivot('academic_year', $academicYearId)
                ->with(['groupes' => fn ($q) => $q
                    ->wherePivot('academic_year', $academicYearId)
                    ->with('filiere:id,code,label')
                    ->select('groupes.id', 'groupes.label', 'groupes.filiere_id'),
                ])
                ->select('modules.id', 'modules.code', 'modules.label')
                ->get()
                ->map(function ($m) {
                    $groupes = $m->groupes->map(fn ($g) => [
                        'id' => $g->id,
                        'label' => $g->label,
                        'filiere' => $g->filiere ? [
                            'id' => $g->filiere->id,
                            'code' => $g->filiere->code,
                            'label' => $g->filiere->label,
                        ] : null,
                    ])->values()->all();

                    return [
                        'module_id' => $m->id,
                        'module_code' => $m->code,
                        'module_label' => $m->label,
                        'groupes' => $groupes,
                    ];
                })
                ->values()
                ->all()
            : [];

        $quick_actions = [
            ['label' => 'Marquer les présences', 'path' => '/attendance'],
            ['label' => 'Saisir les notes', 'path' => '/evaluations'],
        ];

        return [
            'todays_sessions' => $todaysSessions,
            'assigned_modules' => $assignedModules,
            'quick_actions' => $quick_actions,
        ];
    }

    private function stagiairePayload(User $user): array
    {
        $stagiaire = $user->stagiaire;
        if (! $stagiaire) {
            return $this->emptyStagiairePayload();
        }

        $stagiaire->load(['filiere:id,code,label', 'groupes:id,label,filiere_id', 'groupe:id,label,filiere_id']);

        // 1. Canonical filière: User → Stagiaire → (filiere_id or Groupe → Filière). No other source.
        $filiereId = $stagiaire->getFiliereIdForScope();
        if ($filiereId === null) {
            return $this->emptyStagiairePayload();
        }
        $filiereModel = $stagiaire->filiere ?? Filiere::find($filiereId);
        if (! $filiereModel) {
            return $this->emptyStagiairePayload();
        }

        // 2. Groupe(s) ONLY in this filière: Stagiaire → Groupe where groupe.filiere_id = filiereId (null-safe)
        $groupeIds = $stagiaire->getGroupeIdsInFiliere($filiereId);
        $groupeRel = $stagiaire->groupe ?? $stagiaire->groupes->first();
        $groupeFiliereMatch = $groupeRel !== null
            && $groupeRel->filiere_id !== null
            && (int) $groupeRel->filiere_id === $filiereId;
        $groupeModel = $groupeFiliereMatch
            ? $groupeRel
            : $stagiaire->groupes()->where('groupes.filiere_id', $filiereId)->with('filiere:id,code,label')->first();
        if ($groupeIds->isEmpty() && $groupeModel !== null) {
            $groupeIds = collect([$groupeModel->id]);
        }

        $filiere = [
            'id' => $filiereModel->id,
            'code' => $filiereModel->code ?? '',
            'label' => $filiereModel->label ?? '',
        ];
        $groupe = $groupeModel !== null ? [
            'id' => $groupeModel->id,
            'label' => $groupeModel->label ?? '',
        ] : null;

        // 3. Affectations: ONLY for this student's groupes AND module must belong to student's filière
        $affectations = $groupeIds->isEmpty()
            ? collect([])
            : \App\Models\Affectation::whereIn('groupe_id', $groupeIds)
                ->whereHas('module', fn ($q) => $q->where('filiere_id', $filiereId))
                ->with('module:id,code,label,filiere_id')
                ->get();

        $syllabusProgress = [];
        foreach ($affectations as $aff) {
            $total = $aff->progressions()->count();
            $completed = $aff->progressions()->where('status', 'completed')->count();
            $percent = $total > 0 ? round($completed / $total * 100, 1) : 0;
            $syllabusProgress[] = [
                'module' => $aff->module?->label,
                'progress_percent' => $percent,
                'completed_count' => $completed,
                'total_count' => $total,
            ];
        }

        // 4. Latest grades: ONLY notes for evaluations in this student's filière (module.filiere_id = filiereId)
        $latestGrades = Note::where('stagiaire_id', $stagiaire->id)
            ->whereHas('evaluation.affectation.module', fn ($q) => $q->where('filiere_id', $filiereId))
            ->with('evaluation.affectation.module')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (Note $n) => [
                'evaluation' => $n->evaluation?->item_label ?? $n->evaluation?->type ?? 'Note',
                'module' => $n->evaluation?->affectation?->module?->label,
                'value' => $n->valeur,
                'date' => $n->created_at?->toDateString(),
            ])
            ->values()
            ->all();

        $quick_actions = [
            ['label' => 'Emploi du temps', 'path' => '/timetable'],
            ['label' => 'Ma progression', 'path' => '/progress'],
        ];

        return [
            'filiere' => $filiere,
            'groupe' => $groupe,
            'syllabus_progress' => $syllabusProgress,
            'latest_grades' => $latestGrades,
            'quick_actions' => $quick_actions,
        ];
    }

    private function emptyStagiairePayload(): array
    {
        return [
            'message' => 'Profil stagiaire non trouvé ou aucune filière assignée.',
            'filiere' => null,
            'groupe' => null,
            'syllabus_progress' => [],
            'latest_grades' => [],
            'quick_actions' => [
                ['label' => 'Emploi du temps', 'path' => '/timetable'],
                ['label' => 'Ma progression', 'path' => '/progress'],
            ],
        ];
    }

    private function parentPayload(User $user): array
    {
        $parent = $user->parent;
        if (! $parent) {
            return ['message' => 'Profil parent non trouvé.', 'children' => [], 'quick_actions' => []];
        }

        $annee = AnneeScolaire::orderByDesc('id')->first();
        $anneeId = $annee?->id;

        $children = $parent->children()
            ->with(['user:id,name', 'filiere:id,code,label', 'groupe:id,label'])
            ->get()
            ->map(function (Stagiaire $s) use ($anneeId) {
                $attendancePercent = null;
                $is_risk = false;
                if ($anneeId) {
                    try {
                        $summary = $this->attendanceRiskService->summaryForStagiaire($s, $anneeId);
                        $attendancePercent = $summary['global_rate_percent'];
                        $is_risk = $summary['is_risk'];
                    } catch (\Throwable) {
                        // ignore
                    }
                }

                $latestNotes = Note::where('stagiaire_id', $s->id)
                    ->with('evaluation.affectation.module')
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(fn (Note $n) => [
                        'evaluation' => $n->evaluation?->item_label ?? $n->evaluation?->type ?? 'Note',
                        'module' => $n->evaluation?->affectation?->module?->label,
                        'value' => $n->valeur,
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => $s->id,
                    'name' => $s->user?->name,
                    'filiere' => $s->filiere?->code,
                    'groupe' => $s->groupe?->label,
                    'attendance_percent' => $attendancePercent,
                    'is_risk' => $is_risk,
                    'latest_grades' => $latestNotes,
                ];
            })
            ->values()
            ->all();

        $alerts = array_filter($children, fn ($c) => $c['is_risk']);

        $quick_actions = [
            ['label' => 'Voir les enfants', 'path' => '/parent/children'],
        ];

        return [
            'children' => $children,
            'alerts' => array_values($alerts),
            'quick_actions' => $quick_actions,
        ];
    }
}
