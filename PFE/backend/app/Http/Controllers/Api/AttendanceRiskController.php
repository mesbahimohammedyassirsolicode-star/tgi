<?php

namespace App\Http\Controllers\Api;

use App\Models\Groupe;
use App\Models\Stagiaire;
use App\Services\AttendanceRiskService;
use Illuminate\Http\Request;

class AttendanceRiskController extends BaseApiController
{
    public function __construct(
        private AttendanceRiskService $attendanceRiskService
    ) {}

    /**
     * GET /groups/{group}/attendance-summary
     * List all stagiaires in the group with attendance rate, "À risque" flag, and exam eligibility.
     */
    public function summaryByGroup(Request $request, Groupe $group)
    {
        $anneeId = $request->get('annee_scolaire_id') ?? $group->annee_scolaire_id;
        $data = $this->attendanceRiskService->summaryForGroupe($group, $anneeId);
        return $this->success($data);
    }

    /**
     * GET /stagiaires/{stagiaire}/attendance-summary
     * Summary for one stagiaire (requires annee_scolaire_id in query).
     */
    public function summaryByStagiaire(Request $request, Stagiaire $stagiaire)
    {
        $anneeId = $request->get('annee_scolaire_id');
        if (! $anneeId) {
            return $this->error('annee_scolaire_id requis.', 422);
        }
        $data = $this->attendanceRiskService->summaryForStagiaire($stagiaire, (int) $anneeId);
        return $this->success($data);
    }
}
