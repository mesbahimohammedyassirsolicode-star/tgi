<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Evaluation;
use Illuminate\Auth\Access\Response;

class EvaluationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'formateur';
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Evaluation $evaluation): bool
    {
        if ($user->role === 'admin') return true;
        if ($user->role === 'formateur') {
            return $evaluation->affectation->formateur_id === $user->formateur->id;
        }
        return false; // Stagiaires see notes individually, not the whole evaluation object with everyone's grades
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'formateur';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Evaluation $evaluation): bool
    {
        if ($user->role === 'admin') return true;
        if ($user->role === 'formateur') {
            return $evaluation->affectation->formateur_id === $user->formateur->id;
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Evaluation $evaluation): bool
    {
         if ($user->role === 'admin') return true;
        if ($user->role === 'formateur') {
            return $evaluation->affectation->formateur_id === $user->formateur->id;
        }
        return false;
    }
}
