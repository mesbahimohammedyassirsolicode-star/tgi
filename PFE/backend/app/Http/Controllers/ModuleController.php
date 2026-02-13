<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\SyllabusItem;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Module::with('filiere');
        if ($request->has('filiere_id')) {
            $query->where('filiere_id', $request->filiere_id);
        }
        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'filiere_id' => 'required|exists:filieres,id',
            'code' => 'required|string|max:20',
            'label' => 'required|string|max:150',
            'masse_horaire' => 'required|integer|min:1',
            'coefficient' => 'required|integer|min:1',
            'semester' => 'required|in:S1,S2,S3,S4',
        ]);

        return Module::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(Module $module)
    {
        return $module->load(['filiere', 'syllabusItems']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Module $module)
    {
        $validated = $request->validate([
            'filiere_id' => 'exists:filieres,id',
            'code' => 'string|max:20',
            'label' => 'string|max:150',
            'masse_horaire' => 'integer|min:1',
            'coefficient' => 'integer|min:1',
            'semester' => 'in:S1,S2,S3,S4',
        ]);

        $module->update($validated);
        return $module;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Module $module)
    {
        $module->delete();
        return response()->noContent();
    }

    // --- Syllabus Management ---
    public function showSyllabus(Module $module)
    {
        return $module->syllabusItems()->orderBy('order')->get();
    }

    public function updateSyllabus(Request $request, Module $module)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.label' => 'required|string',
            'items.*.estimated_hours' => 'required|integer',
            'items.*.order' => 'required|integer',
        ]);

        // Replace syllabus items (simple approach: delete all and recreate, or sync)
        // For simplicity in this phase, we'll delete and recreate to ensure order matches
        $module->syllabusItems()->delete();
        
        $module->syllabusItems()->createMany($validated['items']);

        return response()->json(['message' => 'Syllabus updated successfully', 'items' => $module->syllabusItems]);
    }
}
