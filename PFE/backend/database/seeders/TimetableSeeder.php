<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AnneeScolaire;
use App\Models\Filiere;
use App\Models\Groupe;
use App\Models\Module;
use App\Models\User;
use App\Models\Formateur;
use App\Models\Affectation;
use App\Models\Seance;
use App\Models\Niveau;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TimetableSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding TSGMP Timetable...');

        // 1. Ensure Year & Niveau
        $annee = AnneeScolaire::where('is_current', true)->first();
        if (!$annee) {
            $this->command->error('No current academic year found. Please seed basic data first.');
            return;
        }

        $niveauTS = Niveau::firstOrCreate(['code' => 'TS'], ['label' => 'Technicien Spécialisé']);

        // 2. Filiere: TSGMP
        $filiere = Filiere::firstOrCreate(
            ['code' => 'TSGMP'],
            [
                'label' => 'Génie Mécanique et Productique',
                'description' => 'Conception et fabrication mécanique',
                'niveau_id' => $niveauTS->id,
            ]
        );

        // 3. Modules (Matin & Soir mix)
        $modulesList = [
            ['code' => 'M_MECA_01', 'label' => 'Analyse de fabrication', 'hours' => 80],
            ['code' => 'M_MECA_02', 'label' => 'Conception (CAO)', 'hours' => 120],
            ['code' => 'M_MECA_03', 'label' => 'Mécanique appliquée', 'hours' => 60],
            ['code' => 'M_MECA_04', 'label' => 'Procédés d\'usinage', 'hours' => 140],
            ['code' => 'M_MECA_05', 'label' => 'Métrologie', 'hours' => 40],
        ];

        $modules = [];
        foreach ($modulesList as $m) {
            $modules[$m['code']] = Module::firstOrCreate(
                ['code' => $m['code']],
                [
                    'label' => $m['label'],
                    'masse_horaire' => $m['hours'],
                    'filiere_id' => $filiere->id,
                    'coefficient' => 2,
                    'semester' => 'S1',
                ]
            );
        }

        // 4. Groupe: 1A
        $group = Groupe::firstOrCreate(
            ['label' => 'TSGMP-1A'],
            [
                'filiere_id' => $filiere->id,
                'annee_scolaire_id' => $annee->id,
                'year_level' => 1,
                'capacity' => 25,
            ]
        );

        // 5. Teachers
        $teachersList = [
            ['name' => 'Mr. Hassan El Mekki', 'email' => 'elmekki@gims.ma', 'specialty' => 'Fabrication'],
            ['name' => 'Mme. Nadia Tazi', 'email' => 'ntazi@gims.ma', 'specialty' => 'Conception'],
        ];

        $teachers = [];
        foreach ($teachersList as $t) {
            $user = User::firstOrCreate(
                ['email' => $t['email']],
                [
                    'name' => $t['name'],
                    'password' => 'Password123',
                    'role' => 'formateur',
                ]
            );
            // Ensure formateur profile
            $teachers[$t['specialty']] = Formateur::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'matricule' => 'F_MECA_' . $user->id,
                    'specialty' => $t['specialty'],
                    'type' => 'permanent',
                ]
            );
        }

        // 6. Affectations
        // Map Modules to Teachers
        $assignments = [
            'M_MECA_01' => $teachers['Fabrication'], // Analyse fab -> El Mekki
            'M_MECA_04' => $teachers['Fabrication'], // Usinage -> El Mekki
            'M_MECA_02' => $teachers['Conception'],  // CAO -> Tazi
            'M_MECA_03' => $teachers['Conception'],  // Meca App -> Tazi
        ];

        $affectations = [];
        foreach ($assignments as $modCode => $prof) {
            if (!isset($modules[$modCode])) continue;
            
            $affectations[$modCode] = Affectation::firstOrCreate(
                [
                    'formateur_id' => $prof->id,
                    'groupe_id' => $group->id,
                    'module_id' => $modules[$modCode]->id,
                    'annee_scolaire_id' => $annee->id,
                ]
            );
        }

        // 7. Generate Timetable (Seances) for Current Week
        // Template: Day => [ [Start, End, ModuleCode, Room] ]
        $template = [
            'Monday' => [
                ['08:30:00', '11:00:00', 'M_MECA_01', 'B12'],
                ['14:30:00', '17:00:00', 'M_MECA_02', 'Salle Info 1'],
            ],
            'Tuesday' => [
                ['08:30:00', '11:00:00', 'M_MECA_04', 'Atelier 1'],
                ['14:30:00', '16:00:00', 'M_MECA_03', 'B12'],
            ],
            'Wednesday' => [
                ['08:30:00', '12:30:00', 'M_MECA_02', 'Salle Info 1'], // Long CAO session
            ],
            'Thursday' => [
                ['08:30:00', '11:00:00', 'M_MECA_01', 'B12'],
                ['14:30:00', '18:30:00', 'M_MECA_04', 'Atelier 1'],
            ],
            'Friday' => [
                ['09:30:00', '12:00:00', 'M_MECA_03', 'B12'],
            ],
        ];

        $startOfWeek = Carbon::now()->startOfWeek(); // Monday
        
        $this->command->info('Generating seances for week of ' . $startOfWeek->toDateString());

        foreach ($template as $dayName => $slots) {
            $date = $startOfWeek->clone()->next($dayName === 'Monday' ? Carbon::MONDAY : 
                ($dayName === 'Tuesday' ? Carbon::TUESDAY : 
                ($dayName === 'Wednesday' ? Carbon::WEDNESDAY : 
                ($dayName === 'Thursday' ? Carbon::THURSDAY : Carbon::FRIDAY))));
            
            // If we are already past this day in current week, maybe generate for next week too? 
            // For demo, let's strictly stick to this week's dates even if past.
            
            foreach ($slots as $slot) {
                [$start, $end, $modCode, $salle] = $slot;
                
                if (!isset($affectations[$modCode])) {
                    $this->command->warn("No affectation for $modCode, skipping seance.");
                    continue;
                }

                Seance::firstOrCreate(
                    [
                        'affectation_id' => $affectations[$modCode]->id,
                        'date' => $date->format('Y-m-d'),
                        'start_time' => $start,
                        'end_time' => $end,
                    ],
                    [
                        'salle' => $salle,
                        'status' => 'planifie',
                        'type' => 'presentiel',
                    ]
                );
            }
        }
        
        $this->command->info('TSGMP Timetable seeded successfully.');
    }
}
