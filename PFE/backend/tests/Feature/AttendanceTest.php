<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Formateur;
use App\Models\Stagiaire;
use App\Models\Groupe;
use App\Models\Module;
use App\Models\AnneeScolaire;
use App\Models\Affectation;
use App\Models\Seance;
use App\Models\Filiere;
use App\Models\Niveau;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_formateur_can_create_seance()
    {
        // Setup
        // Setup
        $user = User::create([
            'name' => 'Formateur Test',
            'email' => 'formateur@test.com',
            'password' => bcrypt('password'),
            'role' => 'formateur',
            'is_active' => true,
        ]);
        $formateur = Formateur::create([
            'user_id' => $user->id,
            'matricule' => 'TEST1234',
            'specialty' => 'Test',
            'type' => 'permanent'
        ]);

        $annee = AnneeScolaire::create([
            'year_start' => 2024,
            'year_end' => 2025,
            'label' => '2024-2025',
            'start_date' => '2024-09-01',
            'end_date' => '2025-06-30'
        ]);

        $niveau = Niveau::create(['label' => 'TS', 'code' => 'TS']);
        $filiere = Filiere::create(['niveau_id' => $niveau->id, 'label' => 'Dev', 'code' => 'DEV']);
        $groupe = Groupe::create(['filiere_id' => $filiere->id, 'annee_scolaire_id' => $annee->id, 'label' => 'DEV101']);
        $module = Module::create(['filiere_id' => $filiere->id, 'code' => 'M101', 'label' => 'PHP', 'masse_horaire' => 120]);

        $affectation = Affectation::create([
            'formateur_id' => $formateur->id,
            'groupe_id' => $groupe->id,
            'module_id' => $module->id,
            'annee_scolaire_id' => $annee->id,
        ]);

        Sanctum::actingAs($user);

        // Action
        $response = $this->postJson('/api/v1/seances', [
            'affectation_id' => $affectation->id,
            'date' => '2024-10-10',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'type' => 'presentiel',
        ]);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('seances', [
            'affectation_id' => $affectation->id,
            'date' => '2024-10-10',
        ]);
    }
}
