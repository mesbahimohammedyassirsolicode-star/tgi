<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Directeur', 'slug' => 'directeur', 'description' => 'Super Admin'],
            ['name' => 'Secrétariat', 'slug' => 'secretariat', 'description' => 'Administration'],
            ['name' => 'Formateur', 'slug' => 'formateur', 'description' => 'Enseignant'],
            ['name' => 'Stagiaire', 'slug' => 'stagiaire', 'description' => 'Étudiant'],
            ['name' => 'Parent', 'slug' => 'parent', 'description' => 'Lecture seule enfants'],
        ];
        foreach ($roles as $r) {
            Role::firstOrCreate(['slug' => $r['slug']], $r);
        }

        $permissions = [
            ['name' => 'Gérer les utilisateurs', 'slug' => 'users.manage', 'group' => 'users'],
            ['name' => 'Gérer la structure académique', 'slug' => 'academic.manage', 'group' => 'academic'],
            ['name' => 'Gérer les groupes et inscriptions', 'slug' => 'groups.manage', 'group' => 'groups'],
            ['name' => 'Gérer les modules et syllabus', 'slug' => 'modules.manage', 'group' => 'modules'],
            ['name' => 'Gérer les affectations', 'slug' => 'affectations.manage', 'group' => 'affectations'],
            ['name' => 'Saisir les présences', 'slug' => 'attendance.write', 'group' => 'attendance'],
            ['name' => 'Consulter les présences', 'slug' => 'attendance.read', 'group' => 'attendance'],
            ['name' => 'Saisir les notes', 'slug' => 'grades.write', 'group' => 'grades'],
            ['name' => 'Consulter les notes', 'slug' => 'grades.read', 'group' => 'grades'],
            ['name' => 'Gérer les stages', 'slug' => 'stages.manage', 'group' => 'stages'],
            ['name' => 'Consulter les feedbacks', 'slug' => 'feedbacks.read', 'group' => 'feedbacks'],
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['slug' => $p['slug']], $p);
        }

        $directeur = Role::where('slug', 'directeur')->first();
        $secretariat = Role::where('slug', 'secretariat')->first();
        $formateur = Role::where('slug', 'formateur')->first();
        if ($directeur) {
            $directeur->permissions()->sync(Permission::pluck('id'));
        }
        if ($secretariat) {
            $secretariat->permissions()->sync(Permission::pluck('id'));
        }
        if ($formateur) {
            $formateur->permissions()->sync(
                Permission::whereIn('slug', ['attendance.write', 'attendance.read', 'grades.write', 'grades.read', 'modules.manage', 'affectations.manage'])->pluck('id')
            );
        }
    }
}
