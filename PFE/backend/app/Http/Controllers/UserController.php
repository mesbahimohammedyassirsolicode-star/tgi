<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Stagiaire;
use App\Models\Formateur;
use App\Models\Administrator;
use App\Models\StudentParent; // Renamed because Parent is PHP keyword
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Rules\PasswordPolicy;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::with(['stagiaire', 'formateur', 'administrator', 'parent']);

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        return $query->latest()->paginate(20);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', new PasswordPolicy()],
            'role' => 'required|in:admin,formateur,stagiaire,parent',
            'avatar_url' => 'nullable|string',
            
            // Formateur
            'matricule' => 'required_if:role,formateur|nullable|string|unique:formateurs,matricule',
            'specialty' => 'required_if:role,formateur|nullable|string',
            'type' => 'required_if:role,formateur|in:permanent,vacataire',
            'hourly_rate' => 'nullable|numeric',

            // Stagiaire
            'cef_number' => 'required_if:role,stagiaire|nullable|string|unique:stagiaires,cef_number',
            'date_naissance' => 'required_if:role,stagiaire|nullable|date',
            'status' => 'nullable|in:actif,abandon,exclu,diplome',
            
            // Parent
            'cin' => 'required_if:role,parent|nullable|string|unique:parents,cin',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',

            // Admin
            'poste' => 'nullable|string',
        ]);

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
                $user->stagiaire()->create([
                    'cef_number' => $validated['cef_number'],
                    'date_naissance' => $validated['date_naissance'],
                    'status' => $validated['status'] ?? 'actif',
                ]);
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

            return $user->load(['stagiaire', 'formateur', 'administrator', 'parent']);
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
             'cef_number' => 'nullable|string',
             'date_naissance' => 'nullable|date',
             'status' => 'in:actif,abandon,exclu,diplome',
             'cin' => 'nullable|string',
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
            $user->stagiaire()->update($request->only(['cef_number', 'date_naissance', 'status']));
        } elseif ($user->role === 'parent' && $user->parent) {
            $user->parent()->update($request->only(['cin', 'phone', 'address']));
        } elseif ($user->role === 'admin' && $user->administrator) {
            $user->administrator()->update($request->only(['poste', 'phone']));
        }

        return $user->load(['stagiaire', 'formateur', 'administrator', 'parent']);
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
