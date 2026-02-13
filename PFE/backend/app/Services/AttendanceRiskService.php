<?php

namespace App\Services;

use App\Models\Affectation;
use App\Models\Groupe;
use App\Models\Seance;
use App\Models\Stagiaire;
use Illuminate\Support\Collection;

/**
 * Business rules: Attendance rate < 80% ⇒ "À risque"; block from final exam if below threshold.
 * Rate = (present + retard) / total seances for the scope (per affectation or per groupe in year).
 */
class AttendanceRiskService
{
    public static function thresholdPercent(): int
    {
        return (int) config('gims.attendance_threshold_percent', 80);
    }

    /**
     * For each stagiaire in the groupe (for the given year via affectations), compute attendance
     * per affectation and overall for the groupe, and risk / exam eligibility.
     *
     * @return array<int, array{stagiaire_id, stagiaire, by_affectation: array, global_rate_percent, is_risk, can_sit_exam}>
     */
    public function summaryForGroupe(Groupe $groupe, ?int $anneeScolaireId = null): array
    {
        $anneeId = $anneeScolaireId ?? $groupe->annee_scolaire_id;
        $stagiaireIds = $groupe->stagiaires()->pluck('stagiaires.id')->toArray();
        $affectations = Affectation::where('groupe_id', $groupe->id)
            ->where('annee_scolaire_id', $anneeId)
            ->with('module')
            ->get();

        $result = [];
        foreach ($stagiaireIds as $sid) {
            $stagiaire = Stagiaire::with('user')->find($sid);
            if (! $stagiaire) {
                continue;
            }
            $byAffectation = [];
            $totalPresent = 0;
            $totalSeances = 0;
            foreach ($affectations as $aff) {
                $stats = $this->rateForStagiaireAndAffectation($sid, $aff->id);
                $byAffectation[] = [
                    'affectation_id' => $aff->id,
                    'module' => $aff->module?->label,
                    'rate_percent' => $stats['rate_percent'],
                    'present_count' => $stats['present_count'],
                    'total_count' => $stats['total_count'],
                    'is_risk' => $stats['rate_percent'] < self::thresholdPercent(),
                ];
                $totalPresent += $stats['present_count'];
                $totalSeances += $stats['total_count'];
            }
            $globalRate = $totalSeances > 0 ? round($totalPresent / $totalSeances * 100, 2) : 0;
            $isRisk = $globalRate < self::thresholdPercent();
            $canSitExam = ! $isRisk;

            $result[] = [
                'stagiaire_id' => $sid,
                'stagiaire' => $stagiaire,
                'by_affectation' => $byAffectation,
                'global_rate_percent' => $globalRate,
                'is_risk' => $isRisk,
                'can_sit_exam' => $canSitExam,
            ];
        }

        return $result;
    }

    /**
     * Attendance rate for one stagiaire in one affectation (module).
     * present_count = count(status in ['present','retard']), total_count = seances for that affectation.
     */
    public function rateForStagiaireAndAffectation(int $stagiaireId, int $affectationId): array
    {
        $seanceIds = Seance::where('affectation_id', $affectationId)->pluck('id');
        $totalCount = $seanceIds->count();
        if ($totalCount === 0) {
            return ['present_count' => 0, 'total_count' => 0, 'rate_percent' => 0];
        }
        $presentCount = \App\Models\Attendance::where('stagiaire_id', $stagiaireId)
            ->whereIn('seance_id', $seanceIds)
            ->whereIn('status', ['present', 'retard'])
            ->count();
        $ratePercent = round($presentCount / $totalCount * 100, 2);
        return [
            'present_count' => $presentCount,
            'total_count' => $totalCount,
            'rate_percent' => $ratePercent,
        ];
    }

    /**
     * Summary for one stagiaire: all his affectations (via his groupes) in the given year.
     */
    public function summaryForStagiaire(Stagiaire $stagiaire, int $anneeScolaireId): array
    {
        $groupeIds = $stagiaire->groupes()->where('groupes.annee_scolaire_id', $anneeScolaireId)->pluck('groupes.id');
        $affectations = Affectation::whereIn('groupe_id', $groupeIds)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->with('module')
            ->get();

        $byAffectation = [];
        $totalPresent = 0;
        $totalSeances = 0;
        foreach ($affectations as $aff) {
            $stats = $this->rateForStagiaireAndAffectation($stagiaire->id, $aff->id);
            $byAffectation[] = [
                'affectation_id' => $aff->id,
                'module' => $aff->module?->label,
                'rate_percent' => $stats['rate_percent'],
                'present_count' => $stats['present_count'],
                'total_count' => $stats['total_count'],
                'is_risk' => $stats['rate_percent'] < self::thresholdPercent(),
            ];
            $totalPresent += $stats['present_count'];
            $totalSeances += $stats['total_count'];
        }
        $globalRate = $totalSeances > 0 ? round($totalPresent / $totalSeances * 100, 2) : 0;
        $isRisk = $globalRate < self::thresholdPercent();

        return [
            'stagiaire_id' => $stagiaire->id,
            'stagiaire' => $stagiaire->load('user'),
            'by_affectation' => $byAffectation,
            'global_rate_percent' => $globalRate,
            'is_risk' => $isRisk,
            'can_sit_exam' => ! $isRisk,
            'threshold_percent' => self::thresholdPercent(),
        ];
    }
}
