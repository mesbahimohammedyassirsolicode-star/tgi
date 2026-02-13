<?php

namespace App\Http\Controllers\Api;

use App\Models\Groupe;
use App\Models\Seance;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Timetable: weekly view of seances by groupe or by formateur.
 */
class TimetableController extends BaseApiController
{
    /**
     * GET /timetable
     * Query: groupe_id (optional), formateur_id (optional), week_start (Y-m-d, default this week Monday).
     * Returns seances grouped by date for the week (Mon–Sun).
     */
    public function index(Request $request)
    {
        $weekStart = $request->get('week_start');
        $date = $weekStart ? Carbon::parse($weekStart) : Carbon::now()->startOfWeek(Carbon::MONDAY);
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->addDays(6)->endOfDay();

        $query = Seance::with(['affectation.module', 'affectation.groupe', 'affectation.formateur.user'])
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('date')
            ->orderBy('start_time');

        if ($request->filled('groupe_id')) {
            $query->whereHas('affectation', fn ($q) => $q->where('groupe_id', $request->groupe_id));
        }
        if ($request->filled('formateur_id')) {
            $query->whereHas('affectation', fn ($q) => $q->where('formateur_id', $request->formateur_id));
        }

        $seances = $query->get();
        $byDate = $seances->groupBy('date')->map(fn ($items) => $items->values()->all())->toArray();

        return $this->success([
            'week_start' => $start->format('Y-m-d'),
            'week_end' => $end->format('Y-m-d'),
            'seances' => $seances->values()->all(),
            'by_date' => $byDate,
        ]);
    }
}
