<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Seance;
use Illuminate\Auth\Access\Response;

class AttendancePolicy
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
    public function view(User $user, Seance $seance): bool
    {
        // Admin can view all
        if ($user->role === 'admin') {
            return true;
        }

        // Formateur can view their own affectations
        if ($user->role === 'formateur') {
            return $seance->affectation->formateur_id === $user->formateur->id;
        }

        // Stagiaire can only view if they are in the group (maybe?) 
        // But usually attendance is viewed via Absence model, not Seance directly in detail.
        // For timetable, yes.
        return false;
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
    public function update(User $user, Seance $seance): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'formateur') {
            return $seance->affectation->formateur_id === $user->formateur->id;
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Seance $seance): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        // Maybe allow formateur to delete if not realized yet?
        if ($user->role === 'formateur') {
            return $seance->affectation->formateur_id === $user->formateur->id && $seance->status === 'planifie';
        }
        return false;
    }
}
