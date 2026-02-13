# Phase 5: Security, Performance, QA, Final Polish

## 1. Security

### Password policy
- **Rule:** `App\Rules\PasswordPolicy`: minimum 8 characters, at least one letter, at least one number.
- **Applied in:** `UserController@store` and `UserController@update` (create/update user).
- **Env:** No dedicated env; message in French.

### Rate limiting
- **Login:** `throttle:5,1` (5 attempts per minute per IP) to limit brute force.
- **Authenticated API:** `throttle:60,1` (60 requests per minute per user) on the protected group.

### Security headers (middleware)
- **`App\Http\Middleware\SecurityHeaders`** (appended to `api` group):
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`
  - `X-XSS-Protection: 1; mode=block`
  - `Referrer-Policy: strict-origin-when-cross-origin`

### Audit logging
- Already in place (Phase 2): `AuditLog::log()` for `grades.save` and `attendance.mark`.
- Table: `audit_logs` (user_id, action, model_type, model_id, old_values, new_values, ip_address).

### Input validation
- All API inputs validated via `$request->validate()` in controllers; 422 with `errors` for validation failures.

---

## 2. Performance

### Caching
- **Niveaux (levels):** `GET /api/v1/academic-structure/levels` cached for 1 hour (`Cache::remember('gims.niveaux', 3600)`).

### Targets (documented)
- **Page load:** < 2 s (frontend responsibility: code-split, lazy load).
- **API response:** < 500 ms average (backend: indexes in place from Phase 1; avoid N+1 via eager loading in controllers).

---

## 3. QA – Tests

### PHPUnit
- **Feature**
  - **AuthTest:** login invalid credentials (422), login valid (200 + data.access_token), me without token (401).
  - **HealthTest:** GET `/api/v1/health` returns 200 and `data.status` healthy when DB is ok.
  - **AttendanceTest:** formateur can create seance (POST `/api/v1/seances`, 201); uses RefreshDatabase.
- **Unit**
  - **PasswordPolicyTest:** accepts valid passwords (letters + numbers, min 8), rejects too short / no letter / no number.

### Running tests
```bash
cd backend && php artisan test
```

---

## 4. Final polish

### Health check
- **GET /api/v1/health** (no auth, no throttle):
  - Returns `{ data: { status: 'healthy'|'degraded', checks: { app: 'ok', database: 'ok'|'error' } } }`.
  - HTTP 503 if database check fails.

### Laravel health
- Default route **GET /up** (Laravel) remains available.

### API error format
- **401:** Unauthenticated (missing/invalid token).
- **403:** Forbidden (role/permission).
- **422:** Validation error; body includes `message` and `errors` (field => messages).

---

## Files added/updated (main)

| Path | Change |
|------|--------|
| `app/Rules/PasswordPolicy.php` | New |
| `app/Http/Middleware/SecurityHeaders.php` | New |
| `app/Http/Controllers/Api/HealthController.php` | New |
| `bootstrap/app.php` | Append SecurityHeaders to api |
| `routes/api.php` | Health route, login throttle 5,1, protected throttle 60,1 |
| `app/Http/Controllers/UserController.php` | PasswordPolicy on password |
| `app/Http/Controllers/AcademicStructureController.php` | Cache niveaux 1h |
| `tests/Feature/AuthTest.php` | New |
| `tests/Feature/HealthTest.php` | New |
| `tests/Feature/AttendanceTest.php` | Use /api/v1/seances |
| `tests/Unit/PasswordPolicyTest.php` | New |
| `database/factories/UserFactory.php` | role, is_active; remove email_verified_at |
