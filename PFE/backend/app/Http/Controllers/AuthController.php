<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            return $this->error('Les identifiants fournis sont incorrects.', 401);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return $this->error('Utilisateur non trouvé.', 404);
        }

        if (! $user->is_active) {
            Auth::logout();
            return $this->error('Compte désactivé. Contactez l\'administration.', 403);
        }

        $this->loadUserProfile($user);
        $user->load('roles:id,name,slug');
        $permissions = $user->roles()->with('permissions:id,slug')->get()->pluck('permissions')->flatten()->pluck('slug')->unique()->values();
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'roles' => $user->roles,
            'permissions' => $permissions,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(['message' => 'Déconnexion réussie']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $this->loadUserProfile($user);
        $user->load('roles:id,name,slug');
        $permissions = $user->roles()->with('permissions:id,slug')->get()->pluck('permissions')->flatten()->pluck('slug')->unique()->values();
        return $this->success([
            'user' => $user,
            'roles' => $user->roles,
            'permissions' => $permissions,
        ]);
    }

    private function loadUserProfile(User $user): void
    {
        try {
            $role = strtolower((string) $user->role);
            match ($role) {
                'admin' => $user->loadMissing('administrator'),
                'formateur', 'teacher' => $user->loadMissing('formateur'),
                'stagiaire', 'student', 'stagiair' => $user->loadMissing('stagiaire.filiere', 'stagiaire.groupes'),
                'parent' => $user->loadMissing('parent'),
                default => null,
            };
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
