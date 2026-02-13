# Phase 4: Advanced Modules — Summary

## 1. Attendance logic (À risque, exam block)

**Backend**
- **`App\Services\AttendanceRiskService`**
  - `summaryForGroupe(Groupe $groupe, ?int $anneeScolaireId)`: for each stagiaire in the group, computes attendance rate per affectation (module) and globally; sets `is_risk` when rate < threshold, `can_sit_exam` = !is_risk.
  - `rateForStagiaireAndAffectation(int $stagiaireId, int $affectationId)`: present_count (status in ['present','retard']), total_count (seances), rate_percent.
  - `summaryForStagiaire(Stagiaire $stagiaire, int $anneeScolaireId)`: same logic for one stagiaire across his groupes in that year.
- **Config:** `config/gims.php` → `attendance_threshold_percent` (default 80), env `GIMS_ATTENDANCE_THRESHOLD`.
- **API**
  - `GET /api/v1/groups/{group}/attendance-summary?annee_scolaire_id=` — list stagiaires with rate, by_affectation, is_risk, can_sit_exam.
  - `GET /api/v1/stagiaires/{stagiaire}/attendance-summary?annee_scolaire_id=` — one stagiaire summary.

**Frontend**
- **GroupAttendanceRiskPage** (`/groups/:id/attendance-summary`): table of stagiaires with global rate, “À risque” / “OK”, “examen bloqué” when applicable, and per-module rates.
- **GroupDetailPage**: button “Voir présences (à risque)” linking to the above.

---

## 2. Grades (module average, weights)

**Backend**
- **`App\Services\GradesSummaryService`**
  - Weighted average per stagiaire per affectation: for each evaluation, normalized note = (valeur / max_points) * 20; module_average = sum(normalized * coefficient) / sum(coefficient).
  - `summaryForAffectation(Affectation $affectation)`: all stagiaires of the affectation’s groupe with evaluations and module_average_over_20.
  - `summaryForStagiaireAndAffectation(Stagiaire $stagiaire, Affectation $affectation)`: one stagiaire, one module.
- **API**
  - `GET /api/v1/affectations/{affectation}/grades-summary` — list of stagiaires with evaluations and module average.
  - `GET /api/v1/stagiaires/{stagiaire}/grades-summary?affectation_id=` — one stagiaire, one module.

**Frontend**
- **AffectationGradesPage** (`/affectations/:id/grades`): list of stagiaires with module average (/20) and breakdown by evaluation.
- **AffectationsPage**: button “Voir notes (moyenne module)” per affectation linking to the above.

---

## 3. Timetable (weekly view)

**Backend**
- **`App\Http\Controllers\Api\TimetableController`**
  - `GET /api/v1/timetable?groupe_id=&formateur_id=&week_start=` (week_start = Monday Y-m-d): seances for that week, optionally filtered by groupe or formateur; response includes `seances` and `by_date` (seances grouped by date).

**Frontend**
- **TimetablePage** (`/timetable`): week picker, optional groupe filter, table (days Mon–Sat × hours 8h–17h) with seance cards (module, start–end time).

---

## 4. Progress / gamification (syllabus completion)

**Backend**
- **`App\Http\Controllers\Api\ProgressController`**
  - `GET /api/v1/stagiaires/{stagiaire}/progress?annee_scolaire_id=`: for each affectation (module) of the stagiaire in the given year, returns completed_count / total_count (from progressions) and progress_percent.

**Frontend**
- **ProgressPage** (`/progress`): for logged-in stagiaire, shows progress bars per module (syllabus completion %).
- **Nav:** “Progression” and “Emploi du temps” added to the dashboard sidebar.

---

## 5. Feedback

- Anonymous feedback (no user_id, optional submission_token) was implemented in Phase 2/3; no change in Phase 4.

---

## Files touched (main)

**Backend**
- `app/Services/AttendanceRiskService.php` (new)
- `app/Services/GradesSummaryService.php` (new)
- `app/Http/Controllers/Api/AttendanceRiskController.php` (new)
- `app/Http/Controllers/Api/GradesSummaryController.php` (new)
- `app/Http/Controllers/Api/TimetableController.php` (new)
- `app/Http/Controllers/Api/ProgressController.php` (new)
- `config/gims.php` (new)
- `routes/api.php` (new routes)

**Frontend**
- `services/api/attendanceRisk.ts`, `gradesSummary.ts`, `timetable.ts`, `progress.ts` (new)
- `pages/GroupAttendanceRiskPage.tsx`, `TimetablePage.tsx`, `AffectationGradesPage.tsx`, `ProgressPage.tsx` (new)
- `pages/GroupDetailPage.tsx`, `pages/AffectationsPage.tsx` (links to new pages)
- `App.tsx` (routes), `layouts/DashboardLayout.tsx` (nav)
