<?php

namespace App\Services;

use App\Models\Affectation;
use App\Models\Evaluation;
use App\Models\Stagiaire;
use Illuminate\Support\Collection;

/**
 * Module-grade summary: weighted average per stagiaire per affectation (module).
 * Evaluations have coefficient; note valeur is out of max_points.
 * Average = sum(valeur / max_points * coefficient) / sum(coefficient) * 20 (scale to /20).
 */
class GradesSummaryService
{
    /**
     * For each stagiaire in the affectation's groupe, compute module average (weighted by evaluation coefficient).
     *
     * @return array<int, array{stagiaire_id, stagiaire, evaluations: array, module_average, module_average_over_20}>
     */
    public function summaryForAffectation(Affectation $affectation): array
    {
        $groupe = $affectation->groupe;
        $stagiaireIds = $groupe->stagiaires()->pluck('stagiaires.id')->toArray();
        $evaluations = $affectation->evaluations()->get();
        $totalCoeff = $evaluations->sum('coefficient');
        if ($totalCoeff <= 0) {
            $totalCoeff = 1;
        }

        $result = [];
        foreach ($stagiaireIds as $sid) {
            $stagiaire = Stagiaire::with('user')->find($sid);
            if (! $stagiaire) {
                continue;
            }
            $evalRows = [];
            $weightedSum = 0;
            $coeffSum = 0;
            foreach ($evaluations as $ev) {
                $note = $ev->notes()->where('stagiaire_id', $sid)->first();
                $valeur = $note ? (float) $note->valeur : 0;
                $max = (float) $ev->max_points ?: 20;
                $coef = (float) $ev->coefficient ?: 1;
                $normalized = $max > 0 ? ($valeur / $max) * 20 : 0; // scale to /20
                $weightedSum += $normalized * $coef;
                $coeffSum += $coef;
                $evalRows[] = [
                    'evaluation_id' => $ev->id,
                    'type' => $ev->type,
                    'item_label' => $ev->item_label,
                    'valeur' => $valeur,
                    'max_points' => $ev->max_points,
                    'coefficient' => $ev->coefficient,
                    'normalized_over_20' => round($normalized, 2),
                ];
            }
            $moduleAverage = $coeffSum > 0 ? round($weightedSum / $coeffSum, 2) : 0;

            $result[] = [
                'stagiaire_id' => $sid,
                'stagiaire' => $stagiaire,
                'evaluations' => $evalRows,
                'module_average' => $moduleAverage,
                'module_average_over_20' => $moduleAverage,
            ];
        }

        return $result;
    }

    /**
     * One stagiaire's grades for one affectation.
     */
    public function summaryForStagiaireAndAffectation(Stagiaire $stagiaire, Affectation $affectation): array
    {
        $evaluations = $affectation->evaluations()->get();
        $evalRows = [];
        $weightedSum = 0;
        $coeffSum = 0;
        foreach ($evaluations as $ev) {
            $note = $ev->notes()->where('stagiaire_id', $stagiaire->id)->first();
            $valeur = $note ? (float) $note->valeur : 0;
            $max = (float) $ev->max_points ?: 20;
            $coef = (float) $ev->coefficient ?: 1;
            $normalized = $max > 0 ? ($valeur / $max) * 20 : 0;
            $weightedSum += $normalized * $coef;
            $coeffSum += $coef;
            $evalRows[] = [
                'evaluation_id' => $ev->id,
                'type' => $ev->type,
                'item_label' => $ev->item_label,
                'valeur' => $valeur,
                'max_points' => $ev->max_points,
                'coefficient' => $ev->coefficient,
                'normalized_over_20' => round($normalized, 2),
            ];
        }
        $moduleAverage = $coeffSum > 0 ? round($weightedSum / $coeffSum, 2) : 0;
        return [
            'stagiaire' => $stagiaire->load('user'),
            'affectation' => $affectation->load('module'),
            'evaluations' => $evalRows,
            'module_average_over_20' => $moduleAverage,
        ];
    }
}
