<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Affectation;
use App\Models\Evaluation;
use App\Models\Stagiaire;
use App\Models\Note;
use App\Models\Groupe;

class MarksSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Marks (Grades)...');

        // Find Affectations for TSGMP
        $group = Groupe::where('label', 'TSGMP-1A')->first();
        if (!$group) {
            $this->command->error('TSGMP-1A not found.');
            return;
        }

        $affectations = Affectation::where('groupe_id', $group->id)->get();
        $stagiaires = $group->stagiaires()->get();

        if ($stagiaires->isEmpty()) {
            $this->command->info('Group empty. Enrolling 20 students...');
            $students = Stagiaire::whereDoesntHave('groupes', function($q) use ($group) {
                $q->where('groupes.id', $group->id);
            })->take(20)->get();
            
            foreach ($students as $s) {
                $s->groupes()->attach($group->id);
            }
            $stagiaires = $group->stagiaires()->get();
        }

        if ($stagiaires->isEmpty()) {
            $this->command->error('Still no stagiaires found.');
            return;
        }

        $evalTypes = [
            ['title' => 'Contrôle Continu 1', 'coefficient' => 1, 'type' => 'CC'],
            ['title' => 'Contrôle Continu 2', 'coefficient' => 1, 'type' => 'CC'],
            ['title' => 'Examen de Fin de Module', 'coefficient' => 2, 'type' => 'EFM'],
        ];

        foreach ($affectations as $affectation) {
            foreach ($evalTypes as $type) {
                $eval = Evaluation::firstOrCreate(
                    [
                        'affectation_id' => $affectation->id,
                        'title' => $type['title'],
                    ],
                    [
                        'coefficient' => $type['coefficient'],
                        'date' => now()->subDays(rand(1, 30))->toDateString(),
                        'max_note' => 20,
                    ]
                );

                foreach ($stagiaires as $stagiaire) {
                    // Random grade between 8 and 18
                    Note::updateOrCreate(
                        ['evaluation_id' => $eval->id, 'stagiaire_id' => $stagiaire->id],
                        ['value' => rand(8, 18) + (rand(0, 3) / 4)]
                    );
                }
            }
        }

        $this->command->info('Marks seeded successfully.');
    }
}
