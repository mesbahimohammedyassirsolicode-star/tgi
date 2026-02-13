# GIMS API Documentation

## Overview
Group IKI Management System (GIMS) is a RESTful API built with Laravel 11.

## Authentication
**Base URL**: `/api`
All protected routes require `Authorization: Bearer <token>` header.

### Endpoints

#### 1. Authentication
- `POST /login`
  - Body: `{ "email": "user@example.com", "password": "password" }`
  - Response: `{ "access_token": "...", "user": { ... } }`
- `POST /logout` (Auth required)
- `GET /me` (Auth required)

#### 2. Academic Structure (Admin)
- `GET /academic-structure/years`
- `POST /academic-structure/years`
- `GET /academic-structure/filieres`
- `POST /academic-structure/filieres`

#### 3. Groups & Enrollments
- `GET /groups?filiere_id=X&year_id=Y`
- `POST /groups`
- `POST /groups/{group}/enroll`
  - Body: `{ "stagiaire_ids": [1, 2, 3] }`

#### 4. Modules & Syllabus
- `GET /modules`
- `GET /modules/{module}/syllabus`
- `POST /modules/{module}/syllabus`
  - Body: `{ "items": [ { "label": "Intro", "estimated_hours": 4, "order": 1 } ] }`

#### 5. Teacher Assignments (Affectations)
- `GET /affectations`
- `POST /affectations`

#### 6. Attendance (TimeTable & Absences)
- `GET /seances?groupe_id=X&start_date=Y&end_date=Z`
- `POST /seances` (Create Session)
- `GET /seances/{seance}/absences` (Roll Call View)
- `POST /seances/{seance}/absences` (Submit Roll Call)
  - Body: `{ "absences": [ { "stagiaire_id": 1, "is_absent": true, "retard_minutes": 15 } ] }`

#### 7. Evaluations & Grades
- `GET /evaluations?affectation_id=X`
- `POST /evaluations`
- `GET /evaluations/{evaluation}/notes` (Grade Entry View)
- `POST /evaluations/{evaluation}/notes` (Submit Grades)
  - Body: `{ "notes": [ { "stagiaire_id": 1, "valeur": 18.5 } ] }`

## Error Handling
- **401 Unauthorized**: Token missing or invalid.
- **403 Forbidden**: User role not allowed.
- **422 Unprocessable Entity**: Validation error.
