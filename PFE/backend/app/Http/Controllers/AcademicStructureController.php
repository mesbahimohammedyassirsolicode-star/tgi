<?php

namespace App\Http\Controllers;

use App\Models\AnneeScolaire;
use App\Models\Filiere;
use App\Models\Groupe;
use App\Models\Niveau;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AcademicStructureController extends Controller
{
    public function indexYears()
    {
        $items = AnneeScolaire::orderBy('year_start', 'desc')->get();
        return $this->success($items);
    }

    public function storeYear(Request $request)
    {
        $validated = $request->validate([
            'year_start' => 'required|digits:4|integer',
            'year_end' => 'required|digits:4|integer|gt:year_start',
            'label' => 'required|string|max:20',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);
        $year = AnneeScolaire::create($validated);
        return $this->created($year);
    }

    public function updateYear(Request $request, AnneeScolaire $year)
    {
        $validated = $request->validate([
            'year_start' => 'digits:4|integer',
            'year_end' => 'digits:4|integer|gt:year_start',
            'label' => 'string|max:20',
            'start_date' => 'date',
            'end_date' => 'date|after:start_date',
            'is_current' => 'boolean',
        ]);
        if (isset($validated['is_current']) && $validated['is_current']) {
            AnneeScolaire::where('id', '!=', $year->id)->update(['is_current' => false]);
        }
        $year->update($validated);
        return $this->success($year->fresh());
    }

    public function destroyYear(AnneeScolaire $year)
    {
        $year->delete();
        return response()->json(null, 204);
    }

    public function indexLevels()
    {
        $levels = Cache::remember('gims.niveaux', 3600, fn () => Niveau::all());
        return $this->success($levels);
    }

    public function indexFilieres(Request $request)
    {
        $query = Filiere::with('niveau');
        if ($request->has('niveau_id')) {
            $query->where('niveau_id', $request->niveau_id);
        }
        return $this->success($query->get());
    }

    public function storeFiliere(Request $request)
    {
        $validated = $request->validate([
            'niveau_id' => 'required|exists:niveaux,id',
            'label' => 'required|string|max:150',
            'code' => 'required|string|max:20|unique:filieres,code',
            'description' => 'nullable|string',
        ]);
        $filiere = Filiere::create($validated);
        return $this->created($filiere->load('niveau'));
    }

    public function updateFiliere(Request $request, Filiere $filiere)
    {
        $validated = $request->validate([
            'niveau_id' => 'exists:niveaux,id',
            'label' => 'string|max:150',
            'code' => 'string|max:20|unique:filieres,code,'.$filiere->id,
            'description' => 'nullable|string',
        ]);
        $filiere->update($validated);
        return $this->success($filiere->fresh('niveau'));
    }

    public function destroyFiliere(Filiere $filiere)
    {
        $filiere->delete();
        return response()->json(null, 204);
    }
}
