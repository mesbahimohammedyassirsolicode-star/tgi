<?php

namespace App\Http\Controllers\Api;

use App\Models\Affectation;
use App\Models\Stagiaire;
use App\Services\GradesSummaryService;
use Illuminate\Http\Request;

class GradesSummaryController extends BaseApiController
{
    public function __construct(
        private GradesSummaryService $gradesSummaryService
    ) {}

    /**
     * GET /affectations/{affectation}/grades-summary
     * Per-stagiaire weighted module average for this affectation.
     */
    public function summaryByAffectation(Affectation $affectation)
    {
        $data = $this->gradesSummaryService->summaryForAffectation($affectation);
        return $this->success($data);
    }

    /**
     * GET /stagiaires/{stagiaire}/grades-summary
     * Query: affectation_id required. Returns one stagiaire's grades for that module.
     */
    public function summaryByStagiaire(Request $request, Stagiaire $stagiaire)
    {
        $affectationId = $request->get('affectation_id');
        if (! $affectationId) {
            return $this->error('affectation_id requis.', 422);
        }
        $affectation = Affectation::findOrFail($affectationId);
        $data = $this->gradesSummaryService->summaryForStagiaireAndAffectation($stagiaire, $affectation);
        return $this->success($data);
    }
}
