<?php

namespace App\Http\Controllers\Api;

use App\Models\Affectation;
use App\Models\Stagiaire;
use Illuminate\Http\Request;

/**
 * Gamification: syllabus progress (completed_items / total_items) per affectation for a stagiaire.
 */
class ProgressController extends BaseApiController
{
    /**
     * GET /stagiaires/{stagiaire}/progress
     * Query: annee_scolaire_id (optional). Returns syllabus completion % per module (affectation).
     */
    public function index(Request $request, Stagiaire $stagiaire)
    {
        $anneeId = $request->get('annee_scolaire_id');
        $groupeIds = $stagiaire->groupes()->when($anneeId, fn ($q) => $q->where('groupes.annee_scolaire_id', $anneeId))->pluck('groupes.id');
        $affectations = Affectation::whereIn('groupe_id', $groupeIds)
            ->when($anneeId, fn ($q) => $q->where('annee_scolaire_id', $anneeId))
            ->with('module')
            ->get();

        $result = [];
        foreach ($affectations as $aff) {
            $total = $aff->progressions()->count();
            $completed = $aff->progressions()->where('status', 'completed')->count();
            $percent = $total > 0 ? round($completed / $total * 100, 1) : 0;
            $result[] = [
                'affectation_id' => $aff->id,
                'module' => $aff->module?->label,
                'completed_count' => $completed,
                'total_count' => $total,
                'progress_percent' => $percent,
            ];
        }

        return $this->success([
            'stagiaire_id' => $stagiaire->id,
            'by_module' => $result,
        ]);
    }
}
