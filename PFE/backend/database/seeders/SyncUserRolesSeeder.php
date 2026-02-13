<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SyncUserRolesSeeder extends Seeder
{
    /**
     * Assign roles to users based on user.role (denormalized) when role_user is empty.
     */
    public function run(): void
    {
        $slugByRole = [
            'admin' => 'directeur',
            'formateur' => 'formateur',
            'stagiaire' => 'stagiaire',
            'parent' => 'parent',
        ];
        try {
            User::query()->whereDoesntHave('roles')->chunkById(100, function ($users) use ($slugByRole) {
                foreach ($users as $user) {
                    $slug = $slugByRole[$user->role] ?? null;
                    if ($slug) {
                        $role = Role::where('slug', $slug)->first();
                        if ($role) {
                            $user->roles()->syncWithoutDetaching([$role->id]);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            // e.g. SQLite without users.deleted_at: run migrations first
            report($e);
        }
    }
}
