<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Stagiaire;
use App\Models\Groupe;
use App\Models\Formateur;
use App\Models\Administrator;
use App\Models\StudentParent; // Renamed because Parent is PHP keyword
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Rules\CinFormat;
use App\Rules\OfpptEligibility;
use App\Rules\PasswordPolicy;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::with(['stagiaire.filiere', 'stagiaire.groupe', 'formateur', 'administrator', 'parent']);

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        return $query->latest()->paginate(20);
    }

    /**
     * Store a newly created resource in storage.
     * Roles: admin, formateur (teacher), stagiaire (student), parent.
     * filiere_id and groupe_id are required ONLY for stagiaire.
     */
    public function store(Request $request)
    {
        $role = $request->input('role');
        if (in_array($role, ['formateur', 'teacher'], true)) {
            $request->merge([
                'role' => 'formateur',
                'type' => $request->input('type') ?: 'permanent',
                'hourly_rate' => $request->filled('hourly_rate') && is_numeric($request->hourly_rate) ? (float) $request->hourly_rate : null,
            ]);
        }
        $role = $request->input('role');

        $baseRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', new PasswordPolicy()],
            'role' => 'required|in:admin,formateur,stagiaire,parent',
            'avatar_url' => 'nullable|string',
        ];

        if ($role === 'formateur') {
            $baseRules['matricule'] = 'required|string|unique:formateurs,matricule';
            $baseRules['specialty'] = 'required|string';
            $baseRules['type'] = 'required|in:permanent,vacataire';
            $baseRules['hourly_rate'] = 'nullable|numeric';
        }

        if ($role === 'stagiaire') {
            $baseRules['cin'] = ['required', 'string', new CinFormat(), Rule::unique('stagiaires', 'cin')];
            $baseRules['cef_number'] = 'required|string|unique:stagiaires,cef_number';
            $baseRules['date_naissance'] = 'required|date';
            $baseRules['niveau_scolaire'] = [
                'required',
                'in:COLLEGE,BAC,BAC+2,BAC+3,MASTER',
                new OfpptEligibility($request->input('niveau_formation')),
            ];
            $baseRules['niveau_formation'] = 'required|in:Q,T,TS,BACHELOR,MASTER';
            $baseRules['filiere_id'] = 'required|integer|exists:filieres,id';
            $baseRules['groupe_id'] = 'required|integer|exists:groupes,id';
            $baseRules['status'] = 'nullable|in:actif,abandon,exclu,diplome';
        }

        if ($role === 'parent') {
            $baseRules['cin'] = ['required', 'string', new CinFormat(), Rule::unique('parents', 'cin')];
            $baseRules['phone'] = 'nullable|string';
            $baseRules['address'] = 'nullable|string';
        }

        if ($role === 'admin') {
            $baseRules['poste'] = 'nullable|string';
            $baseRules['phone'] = 'nullable|string';
        }

        $validated = $request->validate($baseRules, [], [
            'filiere_id' => 'filière',
            'groupe_id' => 'groupe',
        ]);

        if ($role === 'stagiaire') {
            $groupe = Groupe::where('id', $validated['groupe_id'])->where('filiere_id', $validated['filiere_id'])->first();
            if (! $groupe) {
                return response()->json([
                    'message' => 'Le groupe doit appartenir à la filière sélectionnée.',
                    'errors' => ['groupe_id' => ['Groupe invalide pour cette filière.']],
                ], 422);
            }
        }

        return DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'avatar_url' => $validated['avatar_url'] ?? null,
                'is_active' => true,
            ]);

            if ($user->role === 'formateur') {
                $user->formateur()->create([
                    'matricule' => $validated['matricule'],
                    'specialty' => $validated['specialty'],
                    'type' => $validated['type'],
                    'hourly_rate' => $validated['hourly_rate'] ?? null,
                ]);
            } elseif ($user->role === 'stagiaire') {
                $stagiaire = $user->stagiaire()->create([
                    'filiere_id' => $validated['filiere_id'],
                    'groupe_id' => $validated['groupe_id'],
                    'cin' => strtoupper(trim($validated['cin'])),
                    'cef_number' => $validated['cef_number'],
                    'date_naissance' => $validated['date_naissance'],
                    'niveau_scolaire' => $validated['niveau_scolaire'],
                    'niveau_formation' => $validated['niveau_formation'],
                    'status' => $validated['status'] ?? 'actif',
                ]);
                $stagiaire->groupes()->syncWithoutDetaching([$validated['groupe_id']]);
            } elseif ($user->role === 'parent') {
                $user->parent()->create([
                    'cin' => $validated['cin'],
                    'phone' => $validated['phone'] ?? '',
                    'address' => $validated['address'] ?? '',
                ]);
            } elseif ($user->role === 'admin') {
                $user->administrator()->create([
                    'poste' => $validated['poste'] ?? 'Administrateur',
                    'phone' => $validated['phone'] ?? null,
                ]);
            }

            return $user->load(['stagiaire.filiere', 'stagiaire.groupe', 'formateur', 'administrator', 'parent']);
        });
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'email' => ['email', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', new PasswordPolicy()],
            'is_active' => 'boolean',
            // Profile fields (simplified validation for update, usually need unique ignore logic)
             'matricule' => 'nullable|string', // Unique check should ideally ignore current ID, handled below if needed
             'specialty' => 'nullable|string',
             'type' => 'in:permanent,vacataire',
             'hourly_rate' => 'nullable|numeric',
             'cin' => array_values(array_filter([
                 'nullable',
                 'string',
                 new CinFormat(),
                 $user->role === 'stagiaire' ? Rule::unique('stagiaires', 'cin')->ignore($user->stagiaire?->id) : null,
                 $user->role === 'parent' ? Rule::unique('parents', 'cin')->ignore($user->parent?->id) : null,
             ])),
             'cef_number' => 'nullable|string',
             'date_naissance' => 'nullable|date',
             'niveau_scolaire' => [
                 'nullable',
                 'in:COLLEGE,BAC,BAC+2,BAC+3,MASTER',
                 new OfpptEligibility($request->input('niveau_formation') ?? $user->stagiaire?->niveau_formation),
             ],
             'niveau_formation' => 'nullable|in:Q,T,TS,BACHELOR,MASTER',
             'filiere_id' => 'nullable|exists:filieres,id',
             'groupe_id' => 'nullable|exists:groupes,id',
             'status' => 'in:actif,abandon,exclu,diplome',
             'phone' => 'nullable|string',
             'address' => 'nullable|string',
             'poste' => 'nullable|string',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        // Update Profile
        if ($user->role === 'formateur' && $user->formateur) {
            $user->formateur()->update($request->only(['matricule', 'specialty', 'type', 'hourly_rate']));
        } elseif ($user->role === 'stagiaire' && $user->stagiaire) {
            $stagiaireData = $request->only(['cef_number', 'date_naissance', 'niveau_scolaire', 'niveau_formation', 'filiere_id', 'groupe_id', 'status']);
            if ($request->has('type_formation')) {
                $stagiaireData['niveau_formation'] = $request->type_formation;
            }
            if ($request->filled('filiere_id')) {
                $stagiaireData['filiere_id'] = (int) $request->filiere_id;
            }
            if ($request->filled('groupe_id')) {
                $gid = (int) $request->groupe_id;
                $stagiaireData['groupe_id'] = $gid;
                $user->stagiaire->groupes()->syncWithoutDetaching([$gid]);
            }
            if ($request->has('cin')) {
                $stagiaireData['cin'] = strtoupper(trim($request->cin));
            }
            $user->stagiaire()->update($stagiaireData);
        } elseif ($user->role === 'parent' && $user->parent) {
            $user->parent()->update($request->only(['cin', 'phone', 'address']));
        } elseif ($user->role === 'admin' && $user->administrator) {
            $user->administrator()->update($request->only(['poste', 'phone']));
        }

        return $user->load(['stagiaire.filiere', 'stagiaire.groupe', 'formateur', 'administrator', 'parent']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete(); // Soft delete
        return response()->noContent();
    }
}
