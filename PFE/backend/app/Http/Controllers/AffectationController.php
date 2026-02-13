<?php

namespace App\Http\Controllers;

use App\Models\Affectation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AffectationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Affectation::with(['formateur.user', 'groupe', 'module', 'anneeScolaire']);

        if ($request->has('formateur_id')) {
            $query->where('formateur_id', $request->formateur_id);
        }
        if ($request->has('groupe_id')) {
            $query->where('groupe_id', $request->groupe_id);
        }

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'formateur_id' => 'required|exists:formateurs,id',
            'groupe_id' => 'required|exists:groupes,id',
            'module_id' => 'required|exists:modules,id',
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        // Prevent duplicate affectation for same module/group/year
        $exists = Affectation::where('groupe_id', $request->groupe_id)
            ->where('module_id', $request->module_id)
            ->where('annee_scolaire_id', $request->annee_scolaire_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Ce module est déjà affecté pour ce groupe et cette année.'], 422);
        }

        return Affectation::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(Affectation $affectation)
    {
        return $affectation->load(['formateur.user', 'groupe', 'module', 'anneeScolaire']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Affectation $affectation)
    {
        $validated = $request->validate([
            'formateur_id' => 'exists:formateurs,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        $affectation->update($validated);
        return $affectation;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Affectation $affectation)
    {
        $affectation->delete();
        return response()->noContent();
    }
}
