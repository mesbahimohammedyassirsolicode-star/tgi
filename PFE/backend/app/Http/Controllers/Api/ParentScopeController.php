<?php

namespace App\Http\Controllers\Api;

use App\Models\Note;
use App\Models\Stagiaire;
use App\Models\Attendance;
use Illuminate\Http\Request;

/**
 * Parent-scoped API: parents can ONLY access their own children's data (read-only).
 */
class ParentScopeController extends BaseApiController
{
    public function children(Request $request)
    {
        $parent = $request->user()->parent;
        if (! $parent) {
            return $this->error('Profil parent non trouvé.', 403);
        }
        $children = $parent->children()->with(['user', 'groupes.filiere'])->get();
        return $this->success($children);
    }

    public function grades(Request $request, Stagiaire $stagiaire)
    {
        $parent = $request->user()->parent;
        if (! $parent || ! $parent->children()->where('stagiaires.id', $stagiaire->id)->exists()) {
            return $this->error('Accès refusé.', 403);
        }
        $notes = Note::where('stagiaire_id', $stagiaire->id)
            ->with(['evaluation.affectation.module'])
            ->orderBy('created_at', 'desc')
            ->get();
        return $this->success($notes);
    }

    public function attendance(Request $request, Stagiaire $stagiaire)
    {
        $parent = $request->user()->parent;
        if (! $parent || ! $parent->children()->where('stagiaires.id', $stagiaire->id)->exists()) {
            return $this->error('Accès refusé.', 403);
        }
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));
        $attendances = Attendance::where('stagiaire_id', $stagiaire->id)
            ->whereHas('seance', fn ($q) => $q->whereBetween('date', [$from, $to]))
            ->with('seance.affectation.module')
            ->orderBy('created_at', 'desc')
            ->get();
        $total = Attendance::where('stagiaire_id', $stagiaire->id)
            ->whereHas('seance', fn ($q) => $q->whereBetween('date', [$from, $to]));
        $presentCount = (clone $total)->whereIn('status', ['present', 'retard'])->count();
        $totalCount = (clone $total)->count();
        return $this->success([
            'attendances' => $attendances,
            'summary' => [
                'from' => $from,
                'to' => $to,
                'present_count' => $presentCount,
                'total_count' => $totalCount,
                'rate_percent' => $totalCount > 0 ? round($presentCount / $totalCount * 100, 2) : 0,
            ],
        ]);
    }
}
