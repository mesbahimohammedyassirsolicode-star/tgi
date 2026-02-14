<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Stagiaire;
use App\Models\Formateur;
use App\Models\Groupe;
use App\Models\Filiere;
use App\Models\Affectation;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Global stats (no soft-delete scope needed; models define their own)
        $stats = [
            'stagiaires_count' => Stagiaire::where('status', 'actif')->count(),
            'formateurs_count' => Formateur::count(),
            'groupes_count' => Groupe::count(),
            'filieres_count' => Filiere::count(),
            'affectations_count' => Affectation::count(),
        ];

        // 2. Stagiaires per filière (via groupes; distinct stagiaires, respects soft deletes)
        $studentsPerFiliere = Filiere::orderBy('code')->get()->map(function (Filiere $filiere) {
            $groupeIds = $filiere->groupes()->pluck('id');
            $value = $groupeIds->isEmpty()
                ? 0
                : (int) DB::table('groupe_stagiaire')
                    ->whereIn('groupe_id', $groupeIds)
                    ->selectRaw('COUNT(DISTINCT stagiaire_id) as c')
                    ->value('c');
            return ['name' => $filiere->code, 'value' => $value];
        })->all();

        $recentUsers = User::latest()->take(5)->get(['id', 'name', 'role', 'created_at']);

        return response()->json([
            'data' => [
                'stats' => $stats,
                'charts' => [
                    'students_per_filiere' => $studentsPerFiliere,
                ],
                'recent_users' => $recentUsers,
            ],
        ]);
    }
}
