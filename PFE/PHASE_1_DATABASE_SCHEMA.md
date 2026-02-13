# PHASE 1: DATABASE SCHEMA DESIGN
**Project:** Group IKI Management System (GIMS)
**Context:** Moroccan Vocational Training (OFPPT-style)
**Stack:** Laravel 11 / MySQL 8

## Overview
This schema is designed to support a multi-tenant-like structure for academic years, strict attendance tracking, and module-based training. All tables (except pivots where appropriate) include timestamps (`created_at`, `updated_at`) and `deleted_at` for Soft Deletes as requested.

## 1. Authentication & Users (Core)

### `users`
Central authentication table.
- `id`: BIGINT (PK)
- `name`: VARCHAR(255)
- `email`: VARCHAR(255) (Unique)
- `password`: VARCHAR(255)
- `role`: ENUM('admin', 'formateur', 'stagiaire', 'parent') (Indexed for fast lookups)
- `avatar_url`: VARCHAR(255) (Nullable)
- `is_active`: BOOLEAN (Default true)
- `remember_token`: VARCHAR(100)
- `created_at`, `updated_at`, `deleted_at`

### `administrators`
Profile data for Directeurs & Secrétariat.
- `id`: BIGINT (PK)
- `user_id`: BIGINT (FK -> users.id)
- `poste`: VARCHAR(100) (e.g., 'Directeur Pédagogique', 'Secrétaire')
- `phone`: VARCHAR(20)

### `parents`
- `id`: BIGINT (PK)
- `user_id`: BIGINT (FK -> users.id)
- `cin`: VARCHAR(20) (National Content ID, Unique)
- `phone`: VARCHAR(20)
- `address`: TEXT

### `formateurs` (Teachers)
- `id`: BIGINT (PK)
- `user_id`: BIGINT (FK -> users.id)
- `matricule`: VARCHAR(50) (Unique, Employee ID)
- `specialty`: VARCHAR(100) (e.g., 'Développement Digital')
- `type`: ENUM('permanent', 'vacataire')
- `hourly_rate`: DECIMAL(8,2) (Nullable, for payroll calculations)

### `stagiaires` (Students)
- `id`: BIGINT (PK)
- `user_id`: BIGINT (FK -> users.id)
- `parent_id`: BIGINT (FK -> parents.id, Nullable - some students are adults)
- `cef_number`: VARCHAR(50) (Unique, Massar/CEF ID)
- `cin`: VARCHAR(20) (Nullable)
- `date_naissance`: DATE
- `status`: ENUM('actif', 'abandon', 'exclu', 'diplome') (Default 'actif')
- `photo_url`: VARCHAR(255)

---

## 2. Academic Structure

### `annees_scolaires` (School Years)
- `id`: BIGINT (PK)
- `year_start`: YEAR (e.g., 2024)
- `year_end`: YEAR (e.g., 2025)
- `label`: VARCHAR(20) (e.g., "2024-2025")
- `is_current`: BOOLEAN (Only one active at a time)
- `start_date`: DATE
- `end_date`: DATE

### `niveaux` (Levels)
- `id`: BIGINT (PK)
- `label`: VARCHAR(50) (e.g., 'Spécialisation', 'Technicien Spécialisé')
- `code`: VARCHAR(10) (e.g., 'TS', 'Q')

### `filieres` (Departments)
- `id`: BIGINT (PK)
- `niveau_id`: BIGINT (FK -> niveaux.id)
- `label`: VARCHAR(150) (e.g., 'Développement Digital')
- `code`: VARCHAR(20) (e.g., 'DEV')
- `description`: TEXT

### `groupes` (Classes)
- `id`: BIGINT (PK)
- `filiere_id`: BIGINT (FK -> filieres.id)
- `annee_scolaire_id`: BIGINT (FK -> annees_scolaires.id)
- `label`: VARCHAR(50) (e.g., 'DEV-101')
- `year_level`: INTEGER (1 or 2, for 1st year/2nd year)
- `capacity`: INTEGER (Default 30)

### `modules` (Subjects)
- `id`: BIGINT (PK)
- `filiere_id`: BIGINT (FK -> filieres.id)
- `code`: VARCHAR(20) (e.g., 'M104')
- `label`: VARCHAR(150) (e.g., 'Bases de Données')
- `masse_horaire`: INTEGER (Total hours, e.g., 120)
- `coefficient`: INTEGER (For global grade calculation)
- `semester`: ENUM('S1', 'S2', 'S3', 'S4')

---

## 3. Operations & Assignments

### `affectations` (Teacher Assignments)
Links a teacher to a module for a specific group in a specific year.
- `id`: BIGINT (PK)
- `formateur_id`: BIGINT (FK -> formateurs.id)
- `groupe_id`: BIGINT (FK -> groupes.id)
- `module_id`: BIGINT (FK -> modules.id)
- `annee_scolaire_id`: BIGINT (FK -> annees_scolaires.id)
- `start_date`: DATE
- `end_date`: DATE

### `groupe_stagiaire` (Pivot)
Enrollment of students in groups.
- `id`: BIGINT (PK)
- `groupe_id`: BIGINT (FK -> groupes.id)
- `stagiaire_id`: BIGINT (FK -> stagiaires.id)
- `created_at`

---

## 4. Attendance (Assiduité) - MANDATORY
*Strict enforcement rules logic will check these tables.*

### `seances` (Class Sessions)
- `id`: BIGINT (PK)
- `affectation_id`: BIGINT (FK -> affectations.id)
- `date`: DATE
- `start_time`: TIME
- `end_time`: TIME
- `salle`: VARCHAR(20) (Nullable)
- `status`: ENUM('planifie', 'realise', 'annule')
- `type`: ENUM('presentiel', 'distance')

### `absences`
- `id`: BIGINT (PK)
- `stagiaire_id`: BIGINT (FK -> stagiaires.id)
- `seance_id`: BIGINT (FK -> seances.id)
- `justifie`: BOOLEAN (Default false)
- `retard_minutes`: INTEGER (Default 0, >0 implies late arrival, not full absence)
- `motif`: VARCHAR(255) (Nullable)
- `justification_doc`: VARCHAR(255) (Path to uploaded medical cert, etc.)
- *Note: Attendance rate is calculated dynamically: 1 - (Total Absent Hours / Total Module Hours).*

---

## 5. Evaluations & Grades

### `evaluations` (Assessments)
- `id`: BIGINT (PK)
- `affectation_id`: BIGINT (FK -> affectations.id)
- `item_label`: VARCHAR(100) (e.g., "Contrôle 1", "Projet Fin Module")
- `type`: ENUM('cc', 'efm', 'projet', 'stage') (CC=Contrôle Continu, EFM=Examen Fin Module)
- `max_points`: DECIMAL(5,2) (Default 20.00)
- `coefficient`: DECIMAL(3,2) (Weight inside the module)
- `date`: DATE

### `notes` (Grades)
- `id`: BIGINT (PK)
- `evaluation_id`: BIGINT (FK -> evaluations.id)
- `stagiaire_id`: BIGINT (FK -> stagiaires.id)
- `valeur`: DECIMAL(5,2) (The grade)
- `observation`: TEXT (Teacher comments)

---

## 6. Syllabus Tracking (Cahier de Texte)

### `syllabus_items` (Curriculum Breakdown)
- `id`: BIGINT (PK)
- `module_id`: BIGINT (FK -> modules.id)
- `label`: VARCHAR(255) (Competency or Chapter name)
- `estimated_hours`: INTEGER
- `order`: INTEGER

### `progressions`
- `id`: BIGINT (PK)
- `affectation_id`: BIGINT (FK -> affectations.id)
- `syllabus_item_id`: BIGINT (FK -> syllabus_items.id)
- `status`: ENUM('not_started', 'in_progress', 'completed')
- `completed_at`: DATETIME
- *Business Rule: Progress % = (Count(completed) / Count(total items)) * 100*

---

## 7. Anonymous Feedback System
*Strict privacy rules apply.*

### `feedbacks`
- `id`: BIGINT (PK)
- `category`: ENUM('pedagogie', 'infrastructure', 'administration', 'autre')
- `content`: TEXT
- `sentiment_score`: INTEGER (Optional, AI analysis placeholer)
- `is_read`: BOOLEAN
- `created_at` 
- *CRITICAL: No user_id FK here. 100% Traceability removal required.*

## 8. Notifications & System
### `notifications`
- `id`: BIGINT (PK)
- `user_id`: BIGINT (FK -> users.id)
- `title`: VARCHAR(255)
- `message`: TEXT
- `read_at`: DATETIME

## Constraints Summary
1.  **Unique Constraints**: `users.email`, `stagiaires.cef_number`, `formateurs.matricule`, `parents.cin`.
2.  **Indexes**: `users.role`, `stagiaires.status`, `seances.date`, `absences.stagiaire_id`.
3.  **Foreign Keys**: Standard strict constraints with `onDelete('cascade')` primarily (though SoftDeletes handles logical deletion).
