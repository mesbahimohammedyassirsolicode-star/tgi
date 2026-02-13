# PHASE 1: DATABASE SCHEMA & RELATIONS

**Project:** Group IKI Management System (GIMS)  
**Context:** Moroccan vocational training (OFPPT-like), Tangier  
**Stack:** Laravel 11+ / MySQL 8+  
**Conventions:** Foreign keys, indexes on frequent queries, soft deletes where applicable, timestamps on all tables.

---

## 1. ENTITY-RELATIONSHIP OVERVIEW

```
users ←→ roles (role_user)
users → administrators | formateurs | parents | stagiaires (1:1 profile)
stagiaires ←→ parents (optional parent_id; one primary guardian)
stagiaires ←→ groupes (groupe_stagiaire, many-to-many)
annees_scolaires → groupes, affectations
niveaux → filieres → modules, groupes
filieres → modules, groupes
groupes ← formateurs + modules (affectations)
affectations → seances, evaluations, progressions
seances → attendances (one row per stagiaire per seance)
evaluations → notes (one note per stagiaire per evaluation)
modules → syllabus_items; affectations + syllabus_items → progressions
stagiaires → stages (internships)
feedbacks (no user_id; submission_token only for one-time use)
notifications → user_id
```

---

## 2. AUTHENTICATION & USERS

### `users`
| Column       | Type                         | Constraints        |
|-------------|------------------------------|--------------------|
| id          | BIGINT                       | PK                 |
| name        | VARCHAR(255)                 |                    |
| email       | VARCHAR(255)                 | UNIQUE             |
| password    | VARCHAR(255)                 |                    |
| role        | ENUM(admin, formateur, stagiaire, parent) | INDEX (denormalized for quick checks) |
| avatar_url  | VARCHAR(255)                 | NULLABLE           |
| is_active   | BOOLEAN                      | DEFAULT true       |
| remember_token | VARCHAR(100)             |                    |
| created_at, updated_at, deleted_at | TIMESTAMP   | Soft deletes       |

### `roles` (RBAC)
| Column     | Type         | Constraints |
|------------|--------------|-------------|
| id         | BIGINT       | PK          |
| name       | VARCHAR(50)  | UNIQUE      |
| slug       | VARCHAR(50)  | UNIQUE, INDEX |
| description| TEXT         | NULLABLE    |
| timestamps |              |             |

**Seed values:** Directeur, Secrétariat, Formateur, Stagiaire, Parent.

### `permissions`
| Column     | Type          | Constraints |
|------------|---------------|-------------|
| id         | BIGINT        | PK          |
| name       | VARCHAR(100)  | UNIQUE      |
| slug       | VARCHAR(100)  | UNIQUE, INDEX |
| group      | VARCHAR(50)   | NULLABLE, INDEX |
| timestamps |               |             |

### `role_user` (pivot)
| Column     | Type   | Constraints        |
|------------|--------|--------------------|
| id         | BIGINT | PK                 |
| role_id    | FK → roles | CASCADE        |
| user_id    | FK → users | CASCADE        |
| timestamps |        | UNIQUE(role_id, user_id) |

### `permission_role` (pivot)
| Column        | Type   | Constraints           |
|---------------|--------|-----------------------|
| id            | BIGINT | PK                    |
| permission_id | FK → permissions | CASCADE   |
| role_id       | FK → roles | CASCADE           |
| timestamps    |        | UNIQUE(permission_id, role_id) |

### `administrators`
Profile for Directeur & Secrétariat.
| Column   | Type         | Constraints     |
|----------|--------------|-----------------|
| id       | BIGINT       | PK              |
| user_id  | FK → users   | CASCADE         |
| poste    | VARCHAR(100) | e.g. Directeur Pédagogique, Secrétaire |
| phone    | VARCHAR(20)  | NULLABLE        |
| timestamps, deleted_at |   | Soft deletes    |

### `parents`
| Column   | Type         | Constraints     |
|----------|--------------|-----------------|
| id       | BIGINT       | PK              |
| user_id  | FK → users   | CASCADE         |
| cin      | VARCHAR(20)  | UNIQUE          |
| phone    | VARCHAR(20)  |                 |
| address  | TEXT         | NULLABLE        |
| timestamps, deleted_at |   | Soft deletes    |

### `formateurs`
| Column       | Type              | Constraints     |
|--------------|-------------------|-----------------|
| id           | BIGINT            | PK              |
| user_id      | FK → users        | CASCADE         |
| matricule   | VARCHAR(50)       | UNIQUE          |
| specialty   | VARCHAR(100)      |                 |
| type         | ENUM(permanent, vacataire) |     |
| hourly_rate  | DECIMAL(8,2)      | NULLABLE        |
| timestamps, deleted_at |        | Soft deletes    |

### `stagiaires`
| Column        | Type                    | Constraints     |
|---------------|-------------------------|-----------------|
| id            | BIGINT                  | PK              |
| user_id       | FK → users              | CASCADE         |
| parent_id     | FK → parents            | NULLABLE, SET NULL |
| cef_number    | VARCHAR(50)            | UNIQUE (Massar/CEF) |
| cin           | VARCHAR(20)             | NULLABLE        |
| date_naissance| DATE                    |                 |
| status        | ENUM(actif, abandon, exclu, diplome) | DEFAULT actif, INDEX |
| photo_url     | VARCHAR(255)            | NULLABLE        |
| timestamps, deleted_at |                 | Soft deletes    |

---

## 3. ACADEMIC STRUCTURE

### `annees_scolaires`
| Column     | Type    | Constraints              |
|------------|---------|--------------------------|
| id         | BIGINT  | PK                       |
| year_start | YEAR    | UNIQUE(year_start, year_end) |
| year_end   | YEAR    |                          |
| label      | VARCHAR(20) | e.g. 2024-2025      |
| is_current | BOOLEAN | DEFAULT false, INDEX     |
| start_date | DATE    |                          |
| end_date   | DATE    |                          |
| timestamps, deleted_at |  | Soft deletes       |

### `niveaux`
| Column | Type         | Constraints |
|--------|--------------|-------------|
| id     | BIGINT       | PK          |
| label  | VARCHAR(50)  | e.g. Spécialisation, Technicien Spécialisé |
| code   | VARCHAR(10)  | e.g. S, Q, T, TS |
| timestamps, deleted_at |  | Soft deletes |

### `filieres`
| Column      | Type         | Constraints     |
|-------------|--------------|-----------------|
| id          | BIGINT       | PK              |
| niveau_id   | FK → niveaux | CASCADE, INDEX  |
| label       | VARCHAR(150) |                |
| code        | VARCHAR(20)  | INDEX(niveau_id, code) |
| description | TEXT         | NULLABLE        |
| timestamps, deleted_at |  | Soft deletes  |

### `groupes`
| Column             | Type    | Constraints     |
|--------------------|---------|-----------------|
| id                 | BIGINT  | PK              |
| filiere_id         | FK → filieres | CASCADE  |
| annee_scolaire_id  | FK → annees_scolaires | CASCADE |
| label              | VARCHAR(50) | e.g. DEV-101 |
| year_level         | INTEGER | 1 or 2, INDEX   |
| capacity           | INTEGER | DEFAULT 30     |
| timestamps, deleted_at |     | Soft deletes    |

### `modules`
| Column        | Type              | Constraints     |
|---------------|-------------------|-----------------|
| id            | BIGINT            | PK              |
| filiere_id    | FK → filieres     | CASCADE         |
| code          | VARCHAR(20)       | e.g. M104       |
| label         | VARCHAR(150)      |                 |
| masse_horaire | INTEGER           | Total hours     |
| coefficient   | INTEGER           | DEFAULT 1       |
| semester      | ENUM(S1,S2,S3,S4) | NULLABLE        |
| timestamps, deleted_at |        | Soft deletes    |

---

## 4. ASSIGNMENTS & ENROLLMENT

### `affectations`
Links formateur to (groupe + module) for one school year.
| Column             | Type   | Constraints     |
|--------------------|--------|-----------------|
| id                 | BIGINT | PK              |
| formateur_id       | FK → formateurs | CASCADE, INDEX |
| groupe_id          | FK → groupes | CASCADE     |
| module_id          | FK → modules | CASCADE     |
| annee_scolaire_id  | FK → annees_scolaires | CASCADE |
| start_date, end_date | DATE | NULLABLE        |
| timestamps, deleted_at |     | Soft deletes    |
| INDEX(formateur_id, annee_scolaire_id), INDEX(groupe_id, module_id) |

### `groupe_stagiaire` (pivot)
| Column       | Type   | Constraints     |
|--------------|--------|-----------------|
| id           | BIGINT | PK              |
| groupe_id    | FK → groupes | CASCADE     |
| stagiaire_id | FK → stagiaires | CASCADE  |
| timestamps   |        | UNIQUE(groupe_id, stagiaire_id) |

---

## 5. ATTENDANCE (ASSIDUITÉ) – MANDATORY

Business rules:  
- Attendance rate = (present + retard) / total seances per stagiaire (per module or year).  
- Rate &lt; 80% ⇒ flag "À risque"; block from final exam.

### `seances`
| Column         | Type                    | Constraints     |
|----------------|-------------------------|-----------------|
| id             | BIGINT                  | PK              |
| affectation_id | FK → affectations       | CASCADE, INDEX  |
| date           | DATE                    | INDEX, INDEX(affectation_id, date) |
| start_time     | TIME                    |                 |
| end_time       | TIME                    |                 |
| salle          | VARCHAR(20)             | NULLABLE        |
| status         | ENUM(planifie, realise, annule) | DEFAULT planifie, INDEX |
| type           | ENUM(presentiel, distance) | DEFAULT presentiel |
| timestamps, deleted_at |         | Soft deletes    |

### `attendances` (canonical)
One row per (seance, stagiaire). Use this for rate calculation.
| Column            | Type                    | Constraints     |
|-------------------|-------------------------|-----------------|
| id                | BIGINT                  | PK              |
| seance_id         | FK → seances            | CASCADE         |
| stagiaire_id      | FK → stagiaires         | CASCADE         |
| status            | ENUM(present, absent, retard) | DEFAULT present |
| retard_minutes    | UNSIGNED SMALLINT       | DEFAULT 0       |
| justifie          | BOOLEAN                 | DEFAULT false   |
| motif             | VARCHAR(255)            | NULLABLE        |
| justification_doc | VARCHAR(255)            | NULLABLE        |
| timestamps        |                         | UNIQUE(seance_id, stagiaire_id) |
| INDEX(stagiaire_id, seance_id), INDEX(stagiaire_id, status) |

**Note:** Table `absences` exists for legacy compatibility (only rows when student absent). Prefer `attendances` for new logic.

---

## 6. EVALUATIONS & GRADES

Types: contrôle continu (cc), examen fin module (efm), projet, stage.

### `evaluations`
| Column        | Type          | Constraints     |
|---------------|---------------|-----------------|
| id            | BIGINT        | PK              |
| affectation_id| FK → affectations | CASCADE     |
| item_label    | VARCHAR(100)  |                 |
| type          | ENUM(cc, efm, projet, stage) |     |
| max_points    | DECIMAL(5,2)  | DEFAULT 20.00   |
| coefficient   | DECIMAL(3,2)  | DEFAULT 1.00    |
| date          | DATE          |                 |
| timestamps, deleted_at |  | Soft deletes    |

### `notes`
| Column        | Type          | Constraints     |
|---------------|---------------|-----------------|
| id            | BIGINT        | PK              |
| evaluation_id | FK → evaluations | CASCADE     |
| stagiaire_id  | FK → stagiaires | CASCADE     |
| valeur        | DECIMAL(5,2)  |                 |
| observation   | TEXT          | NULLABLE        |
| timestamps, deleted_at |  | UNIQUE(evaluation_id, stagiaire_id) |

---

## 7. SYLLABUS & PROGRESSION

Syllabus progress = (completed_items / total_items) × 100 per affectation.

### `syllabus_items`
| Column          | Type         | Constraints     |
|-----------------|--------------|-----------------|
| id              | BIGINT       | PK              |
| module_id       | FK → modules | CASCADE         |
| label           | VARCHAR(255) |                 |
| estimated_hours | INTEGER      | DEFAULT 1       |
| order           | INTEGER      | DEFAULT 1       |
| timestamps, deleted_at |  | Soft deletes  |

### `progressions`
| Column           | Type                    | Constraints     |
|------------------|-------------------------|-----------------|
| id               | BIGINT                  | PK              |
| affectation_id   | FK → affectations       | CASCADE         |
| syllabus_item_id | FK → syllabus_items     | CASCADE         |
| status           | ENUM(not_started, in_progress, completed) | DEFAULT not_started |
| completed_at     | DATETIME                | NULLABLE        |
| timestamps, deleted_at |         | UNIQUE(affectation_id, syllabus_item_id) |

---

## 8. STAGES (INTERNSHIPS)

OFPPT-style stage en entreprise.

### `stages`
| Column       | Type                    | Constraints     |
|--------------|-------------------------|-----------------|
| id           | BIGINT                  | PK              |
| stagiaire_id | FK → stagiaires         | CASCADE         |
| groupe_id    | FK → groupes            | NULLABLE, SET NULL |
| formateur_id | FK → formateurs         | NULLABLE (tuteur), SET NULL |
| organisation | VARCHAR(255)            |                 |
| poste        | VARCHAR(150)            | NULLABLE        |
| date_debut   | DATE                    |                 |
| date_fin     | DATE                    |                 |
| status       | ENUM(en_cours, valide, non_valide) | DEFAULT en_cours, INDEX |
| rapport_path | TEXT                    | NULLABLE        |
| note         | DECIMAL(5,2)            | NULLABLE        |
| observation  | TEXT                    | NULLABLE        |
| timestamps, deleted_at |         | Soft deletes    |
| INDEX(stagiaire_id, date_debut) |

---

## 9. ANONYMOUS FEEDBACK (100% UNTRACEABLE)

No `user_id`, no IP storage. Optional one-time `submission_token` to prevent abuse without identifying the submitter.

### `feedbacks`
| Column           | Type    | Constraints     |
|------------------|---------|-----------------|
| id               | BIGINT  | PK              |
| submission_token | VARCHAR(64) | NULLABLE, UNIQUE (one-time use) |
| category         | ENUM(pedagogie, infrastructure, administration, autre) | INDEX |
| content          | TEXT    |                 |
| sentiment_score  | INTEGER | NULLABLE        |
| is_read          | BOOLEAN | DEFAULT false   |
| timestamps, deleted_at |  | Soft deletes    |

---

## 10. NOTIFICATIONS

### `notifications`
| Column   | Type      | Constraints     |
|----------|-----------|-----------------|
| id       | BIGINT    | PK              |
| user_id  | FK → users| CASCADE, INDEX  |
| title    | VARCHAR(255) |              |
| message  | TEXT      |                 |
| read_at  | TIMESTAMP | NULLABLE        |
| timestamps, deleted_at |  | INDEX(user_id, read_at) |

---

## 11. CONSTRAINTS & INDEXES SUMMARY

| Table / area      | Uniques / key indexes |
|-------------------|------------------------|
| users             | email; role (index)    |
| roles             | name, slug             |
| role_user         | (role_id, user_id)     |
| permission_role   | (permission_id, role_id) |
| stagiaires        | cef_number; status (index) |
| formateurs        | matricule              |
| parents           | cin                    |
| annees_scolaires  | (year_start, year_end); is_current (index) |
| groupe_stagiaire  | (groupe_id, stagiaire_id) |
| attendances       | (seance_id, stagiaire_id); (stagiaire_id, seance_id); (stagiaire_id, status) |
| notes             | (evaluation_id, stagiaire_id) |
| progressions      | (affectation_id, syllabus_item_id) |
| feedbacks         | submission_token; category (index) |

---

## 12. LARAVEL MIGRATIONS (ORDER)

1. `0001_01_01_000000_create_users_table` (+ password_reset_tokens, sessions)
2. `2026_02_13_082732_create_administrators_table`
3. `2026_02_13_082733_create_student_parents_table` (table name: `parents`)
4. `2026_02_13_082734_create_formateurs_table`
5. `2026_02_13_082734_create_stagiaires_table`
6. `2026_02_13_082747_create_annee_scolaires_table` (annees_scolaires)
7. `2026_02_13_082747_create_niveaux_table`
8. `2026_02_13_082748_create_filieres_table`
9. `2026_02_13_082748_create_groupes_table`
10. `2026_02_13_082749_create_modules_table`
11. `2026_02_13_082758_create_affectations_table`
12. `2026_02_13_082829_create_groupe_stagiaire_table`
13. `2026_02_13_082759_create_seances_table`
14. `2026_02_13_082759_create_absences_table` (legacy; prefer attendances)
15. `2026_02_13_100002_create_attendances_table`
16. `2026_02_13_082759_create_evaluations_table`
17. `2026_02_13_082800_create_notes_table`
18. `2026_02_13_082800_create_syllabus_items_table`
19. `2026_02_13_082801_create_progressions_table`
20. `2026_02_13_100001_create_stages_table`
21. `2026_02_13_082801_create_feedback_table`
22. `2026_02_13_082801_create_notifications_table`
23. `2026_02_13_100000_create_roles_and_permissions_tables`
24. `2026_02_13_100003_add_schema_indexes_and_uniques`
25. `2026_02_13_100004_add_feedback_anonymous_token_and_index` (adds `submission_token` to existing `feedbacks` if missing)

*(Ensure `stages` runs after `formateurs` and `groupes`; `attendances` after `seances`.)*

---

**PHASE COMPLETED**
