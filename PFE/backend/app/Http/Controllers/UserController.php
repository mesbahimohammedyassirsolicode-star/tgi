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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;
use App\Rules\CinFormat;
use App\Rules\OfpptEligibility;
use App\Rules\PasswordPolicy;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     * Eager load role-specific relations only when present; null-safe for stagiaire without filière/groupe.
     */
    public function index(Request $request)
    {
        try {
            $query = User::query()
                ->with([
                    'stagiaire' => fn ($q) => $q->with(['filiere:id,code,label', 'groupes:id,label,filiere_id']),
                    'formateur',
                    'administrator',
                    'parent',
                ]);

            if ($request->filled('role')) {
                $query->where('role', $request->role);
            }

            $paginator = $query->latest()->paginate(20);

            // Return paginator as JSON; relations may be null for users without that role
            return $this->success($paginator);
        } catch (Throwable $e) {
            Log::error('UserController::index failed', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Erreur de chargement des utilisateurs.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * Roles: admin, formateur (teacher), stagiaire (student), parent.
     * filiere_id and groupe_id are required ONLY for stagiaire.
     */
    public function store(Request $request)
    {
        Log::info('DEBUG UserController::store', ['input_role' => $request->input('role')]);
        $inputRole = strtolower(trim((string) $request->input('role', '')));
        $normalizedRole = $inputRole;
        if (str_contains($inputRole, 'teacher') || str_contains($inputRole, 'formateur')) {
            $normalizedRole = 'teacher';
        } elseif (str_contains($inputRole, 'student') || str_contains($inputRole, 'stagiaire')) {
            $normalizedRole = 'student';
        } elseif (str_contains($inputRole, 'admin')) {
            $normalizedRole = 'admin';
        }

        $request->merge([
            'role' => $normalizedRole,
            'specialite' => trim((string) ($request->input('specialite') ?? $request->input('specialty') ?? '')),
        ]);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'string', new PasswordPolicy()],
            'role' => 'required|in:admin,teacher,student,parent',
            'avatar_url' => 'nullable|string',
        ];

        if ($normalizedRole === 'teacher') {
            $rules['matricule'] = 'required|string|max:50|unique:formateurs,matricule';
            $rules['specialite'] = 'required|string|max:100';
        }

        if ($normalizedRole === 'student') {
            $rules['cin'] = ['required', 'string', new CinFormat(), Rule::unique('stagiaires', 'cin')];
            $rules['cef_number'] = 'required|string|unique:stagiaires,cef_number';
            $rules['date_naissance'] = 'required|date';
            $rules['niveau_scolaire'] = [
                'required',
                'in:COLLEGE,BAC,BAC+2,BAC+3,MASTER',
                new OfpptEligibility($request->input('niveau_formation')),
            ];
            $rules['niveau_formation'] = 'required|in:Q,T,TS,BACHELOR,MASTER';
            $rules['filiere_id'] = 'required|integer|exists:filieres,id';
            $rules['groupe_id'] = 'required|integer|exists:groupes,id';
            $rules['status'] = 'nullable|in:actif,abandon,exclu,diplome';
        }

        if ($normalizedRole === 'admin') {
            $rules['poste'] = 'nullable|string';
            $rules['phone'] = 'nullable|string';
        }

        if ($normalizedRole === 'parent') {
            $rules['cin'] = ['required', 'string', 'max:20', Rule::unique('parents', 'cin')];
            $rules['phone'] = 'required|string|max:20';
            $rules['address'] = 'nullable|string';
        }

        $validator = Validator::make($request->all(), $rules, [], [
            'filiere_id' => 'filiere',
            'groupe_id' => 'groupe',
            'specialite' => 'specialite',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if ($normalizedRole === 'student') {
            $groupe = Groupe::where('id', $validated['groupe_id'])
                ->where('filiere_id', $validated['filiere_id'])
                ->first();

            if (! $groupe) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['groupe_id' => ['Groupe invalide pour cette filiere.']],
                ], 422);
            }
        }

        $dbRole = match ($normalizedRole) {
            'teacher' => 'formateur',
            'student' => 'stagiaire',
            default => $normalizedRole,
        };

        try {
            $user = DB::transaction(function () use ($validated, $normalizedRole, $dbRole) {
                $createdUser = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'role' => $dbRole,
                    'avatar_url' => $validated['avatar_url'] ?? null,
                    'is_active' => true,
                ]);

                if ($normalizedRole === 'student') {
                    $stagiaire = $createdUser->stagiaire()->create([
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
                }

                if ($normalizedRole === 'teacher') {
                    $createdUser->formateur()->create([
                        'matricule' => $validated['matricule'],
                        'specialty' => $validated['specialite'],
                        'type' => 'permanent',
                        'hourly_rate' => null,
                    ]);
                }

                if ($normalizedRole === 'admin') {
                    $createdUser->administrator()->create([
                        'poste' => $validated['poste'] ?? 'Administrateur',
                        'phone' => $validated['phone'] ?? null,
                    ]);
                }

                if ($normalizedRole === 'parent') {
                    $createdUser->parent()->create([
                        'cin' => strtoupper(trim($validated['cin'])),
                        'phone' => $validated['phone'],
                        'address' => $validated['address'] ?? null,
                    ]);
                }

                return $createdUser->load(['stagiaire.filiere', 'stagiaire.groupe', 'formateur', 'administrator', 'parent']);
            });

            return response()->json([
                'message' => 'User created successfully.',
                'data' => $user,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to create user.',
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
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

        // Password hashing is handled by User model's 'hashed' cast — do NOT Hash::make() here
        // (double-hashing breaks login)

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
                $user->stagiaire->groupes()->syncWithoutDetaching([$gid]);
                if (Schema::hasColumn('stagiaires', 'groupe_id')) {
                    $stagiaireData['groupe_id'] = $gid;
                }
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

        try {
            $user->load([
                'stagiaire' => fn ($q) => $q->with(['filiere:id,code,label', 'groupes:id,label,filiere_id']),
                'formateur',
                'administrator',
                'parent',
            ]);
            return $this->success($user);
        } catch (Throwable $e) {
            Log::error('UserController::update load failed', ['user_id' => $user->id, 'message' => $e->getMessage()]);
            return response()->json([
                'message' => 'Utilisateur mis à jour mais erreur lors du rechargement des données.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
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
