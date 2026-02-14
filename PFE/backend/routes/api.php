<?php

use App\Http\Controllers\Api\StageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AcademicStructureController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\AffectationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Api\AttendanceRiskController;
use App\Http\Controllers\Api\GradesSummaryController;
use App\Http\Controllers\Api\TimetableController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GIMS API - Version 1
|--------------------------------------------------------------------------
| Prefix: /api/v1
| Rate limit: 60 requests/minute per user (authenticated), 10/min (guest)
| Response format: { data?, meta?, errors? }
*/

Route::prefix('v1')->group(function () {

    // ----- Health (no auth, no throttle) -----
    Route::get('/health', [HealthController::class, 'index']);

    // ----- Public (stricter throttle on login to limit brute force) -----
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/feedbacks', [FeedbackController::class, 'store']); // Anonymous; no auth

    // ----- Protected (60 requests/min) -----
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Admin / Directeur / Secrétariat (admin = can manage users e.g. create formateur)
        Route::middleware('role:directeur,secretariat,admin')->group(function () {
            Route::apiResource('users', UserController::class);
            Route::get('/feedbacks', [FeedbackController::class, 'index']);
            
            // Dashboard Stats
            Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'index']);
        });

        Route::scopeBindings()->group(function () {
            // Academic structure (admin)
            Route::middleware('role:directeur,secretariat')->group(function () {
                Route::get('/academic-structure/years', [AcademicStructureController::class, 'indexYears']);
                Route::post('/academic-structure/years', [AcademicStructureController::class, 'storeYear']);
                Route::put('/academic-structure/years/{year}', [AcademicStructureController::class, 'updateYear']);
                Route::delete('/academic-structure/years/{year}', [AcademicStructureController::class, 'destroyYear']);
                Route::get('/academic-structure/filieres', [AcademicStructureController::class, 'indexFilieres']);
                Route::post('/academic-structure/filieres', [AcademicStructureController::class, 'storeFiliere']);
                Route::put('/academic-structure/filieres/{filiere}', [AcademicStructureController::class, 'updateFiliere']);
                Route::delete('/academic-structure/filieres/{filiere}', [AcademicStructureController::class, 'destroyFiliere']);
            });

            Route::get('/academic-structure/levels', [AcademicStructureController::class, 'indexLevels']);
            Route::get('/academic-structure/niveaux', [AcademicStructureController::class, 'indexLevels']); // alias

            // Groups (admin + formateur for own). GET /groups or /groupes?filiere_id=ID
            Route::get('/groups', [GroupController::class, 'index']);
            Route::get('/groupes', [GroupController::class, 'index']);
            Route::get('/groups/{group}', [GroupController::class, 'show']);
            Route::post('/groups', [GroupController::class, 'store'])->middleware('role:directeur,secretariat');
            Route::put('/groups/{group}', [GroupController::class, 'update'])->middleware('role:directeur,secretariat');
            Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->middleware('role:directeur,secretariat');
            Route::post('/groups/{group}/enroll', [GroupController::class, 'enrollStudents'])->middleware('role:directeur,secretariat');

            // Modules
            Route::get('/modules', [ModuleController::class, 'index']);
            Route::get('/modules/{module}', [ModuleController::class, 'show']);
            Route::post('/modules', [ModuleController::class, 'store'])->middleware('role:directeur,secretariat');
            Route::put('/modules/{module}', [ModuleController::class, 'update'])->middleware('role:directeur,secretariat');
            Route::delete('/modules/{module}', [ModuleController::class, 'destroy'])->middleware('role:directeur,secretariat');
            Route::get('/modules/{module}/syllabus', [ModuleController::class, 'showSyllabus']);
            Route::post('/modules/{module}/syllabus', [ModuleController::class, 'updateSyllabus'])->middleware('role:directeur,formateur');

            // Affectations
            Route::get('/affectations', [AffectationController::class, 'index']);
            Route::get('/affectations/{affectation}', [AffectationController::class, 'show']);
            Route::post('/affectations', [AffectationController::class, 'store'])->middleware('role:directeur,secretariat');
            Route::put('/affectations/{affectation}', [AffectationController::class, 'update'])->middleware('role:directeur,secretariat');
            Route::delete('/affectations/{affectation}', [AffectationController::class, 'destroy'])->middleware('role:directeur,secretariat');

            // Seances (sessions) & Attendances (canonical roll call)
            Route::get('/seances', [AttendanceController::class, 'index']);
            Route::get('/seances/{seance}', [AttendanceController::class, 'show']);
            Route::post('/seances', [AttendanceController::class, 'store']);
            Route::put('/seances/{seance}', [AttendanceController::class, 'update']);
            Route::delete('/seances/{seance}', [AttendanceController::class, 'destroy']);
            Route::get('/seances/{seance}/roll-call', [AttendanceController::class, 'getRollCall']);
            Route::post('/seances/{seance}/roll-call', [AttendanceController::class, 'submitRollCall']);
            // Legacy absences (optional)
            Route::get('/seances/{seance}/absences', [AttendanceController::class, 'getAbsencesForSeance']);
            Route::post('/seances/{seance}/absences', [AttendanceController::class, 'markAbsences']);

            // Attendance risk (Phase 4): rate, À risque, exam eligibility
            Route::get('/groups/{group}/attendance-summary', [AttendanceRiskController::class, 'summaryByGroup']);
            Route::get('/stagiaires/{stagiaire}/attendance-summary', [AttendanceRiskController::class, 'summaryByStagiaire']);

            // Grades summary (Phase 4): weighted module average
            Route::get('/affectations/{affectation}/grades-summary', [GradesSummaryController::class, 'summaryByAffectation']);
            Route::get('/stagiaires/{stagiaire}/grades-summary', [GradesSummaryController::class, 'summaryByStagiaire']);

            // Timetable (Phase 4): weekly view — stagiaire sees only own filière/groupe
            Route::get('/timetable', [TimetableController::class, 'index']);
            Route::get('/emploi-du-temps', [TimetableController::class, 'index']);

            // Progress / Gamification (Phase 4): syllabus completion %
            Route::get('/stagiaires/{stagiaire}/progress', [ProgressController::class, 'index']);

            // Evaluations & Notes
            Route::get('/evaluations', [EvaluationController::class, 'index']);
            Route::get('/evaluations/{evaluation}', [EvaluationController::class, 'show']);
            Route::post('/evaluations', [EvaluationController::class, 'store']);
            Route::put('/evaluations/{evaluation}', [EvaluationController::class, 'update']);
            Route::delete('/evaluations/{evaluation}', [EvaluationController::class, 'destroy']);
            Route::get('/evaluations/{evaluation}/notes', [EvaluationController::class, 'getNotes']);
            Route::post('/evaluations/{evaluation}/notes', [EvaluationController::class, 'saveNotes']);

            // Stages
            Route::get('/stages', [StageController::class, 'index']);
            Route::get('/stages/{stage}', [StageController::class, 'show']);
            Route::post('/stages', [StageController::class, 'store'])->middleware('role:directeur,secretariat,formateur');
            Route::put('/stages/{stage}', [StageController::class, 'update'])->middleware('role:directeur,secretariat,formateur');
            Route::delete('/stages/{stage}', [StageController::class, 'destroy'])->middleware('role:directeur,secretariat');

            // Notifications
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        });

        // Parent-scoped: only their children's data (read-only)
        Route::middleware('role:parent')->prefix('parent')->group(function () {
            Route::get('/children', [\App\Http\Controllers\Api\ParentScopeController::class, 'children']);
            Route::get('/children/{stagiaire}/grades', [\App\Http\Controllers\Api\ParentScopeController::class, 'grades']);
            Route::get('/children/{stagiaire}/attendance', [\App\Http\Controllers\Api\ParentScopeController::class, 'attendance']);
        });
    });
});
