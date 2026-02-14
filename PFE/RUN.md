# How to run GIMS

Run the **backend** (Laravel API) and **frontend** (React) separately. You need **PHP 8.2+**, **Composer**, **Node.js 18+**, and a **database** (SQLite or MySQL).

---

## 1. Backend (Laravel API)

### 1.1 Install dependencies

```bash
cd backend
composer install
```

### 1.2 Environment

Copy the example env and generate the app key:

```bash
copy .env.example .env
php artisan key:generate
```

**Database (choose one):**

- **SQLite (easiest for local):**  
  In `.env` keep:
  ```env
  DB_CONNECTION=sqlite
  ```
  Then create the DB file (if it doesn’t exist):
  ```bash
  # Windows (PowerShell)
  New-Item -ItemType File -Path database\database.sqlite -Force

  # Or on Linux/macOS
  touch database/database.sqlite
  ```

- **MySQL:**  
  In `.env` set:
  ```env
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=gims
  DB_USERNAME=root
  DB_PASSWORD=your_password
  ```
  Create the `gims` database in MySQL, then run migrations.

### 1.3 Database and seed

```bash
php artisan migrate
php artisan db:seed
```

This creates roles/permissions, demo data, **TSGE 1A**, **TGI 2A**, **TSGMP 1A**, **TSGTL 1A**, **TSDI 2A**, **TSGQ 2A**, **BEGI 1A**, **BEMRH 1A**, **BEQSE 1A**, **BETL 1A**, **METL 1A**, **MEGIQ 1A**, and **MGRH 1A** timetables. After seeding, you should see these groups in the list (e.g. on the Emploi du temps page).

If you already ran `db:seed` before and don’t see TSGE, run a timetable seeder:

```bash
php artisan db:seed --class=Tsge1ATimetableSeeder
php artisan db:seed --class=Tgi2ATimetableSeeder
php artisan db:seed --class=Tsgmp1ATimetableSeeder
php artisan db:seed --class=Tsgtl1ATimetableSeeder
php artisan db:seed --class=Tsdi2ATimetableSeeder
php artisan db:seed --class=Tsgq2ATimetableSeeder
php artisan db:seed --class=Begi1ATimetableSeeder
php artisan db:seed --class=Bemrh1ATimetableSeeder
php artisan db:seed --class=Beqse1ATimetableSeeder
php artisan db:seed --class=Betl1ATimetableSeeder
php artisan db:seed --class=Metl1ATimetableSeeder
php artisan db:seed --class=Megiq1ATimetableSeeder
php artisan db:seed --class=Mgrh1ATimetableSeeder
php artisan db:seed --class=MoroccanStagiairesSeeder
```

**CIN & Moroccan names:** Stagiaires require a mandatory, unique CIN (format: 2 letters + 6 digits, e.g. AB123456). Run the migration, then add students via the Users form. To replace existing Western names with Moroccan names, run `MoroccanStagiairesSeeder` (adds demo stagiaires with realistic names) or manually update via the app.

**Eligibilité (Type de formation vs Niveau scolaire):**

| Type de formation (visée) | Niveau scolaire minimum requis |
|---------------------------|-------------------------------|
| Qualification             | Collège                       |
| Technicien                | Baccalauréat                  |
| Technicien Spécialisé     | Baccalauréat                  |
| Bachelor                  | Bac+2                         |
| Master                    | Bac+3                         |

Then open **Emploi du temps**, choose a group (TSGE-1A, TGI-2A, TSGMP-1A, TSGTL-1A, TSDI-2A, TSGQ-2A, BEGI-1A, BEMRH-1A, BEQSE-1A, BETL-1A, METL-1A, MEGIQ-1A, MGRH-1A) and a week to see the grid (module, formateur, salle). If you need a user to log in, create one (e.g. via tinker or a seeder):

```bash
php artisan tinker
>>> \App\Models\User::factory()->create(['email'=>'admin@test.com','password'=>bcrypt('password123'),'role'=>'admin']);
>>> exit
```

(Then assign the “directeur” role to that user via the `roles` and `role_user` tables if you use RBAC.)

**If you see "The provided credentials are incorrect"** but the user exists in the database, the password must be stored **hashed**. Reset it with Tinker:

```bash
cd backend
php artisan tinker
```

Then in Tinker (replace the email with yours):

```php
$user = \App\Models\User::where('email', 'admin@test.com')->first();
$user->password = 'Password123';
$user->save();
exit
```

Then log in with that email and password `Password123`.

### 1.4 Start the API server

```bash
php artisan serve
```

API base: **http://localhost:8000**  
- Health: http://localhost:8000/api/v1/health  
- Login: `POST http://localhost:8000/api/v1/login` with `{"email":"...","password":"..."}`  

---

## 2. Frontend (React)

### 2.1 Install dependencies

```bash
cd frontend
npm install
```

### 2.2 Point to the API (optional)

If the API is **not** at `http://localhost:8000`, create `.env` in `frontend/`:

```env
VITE_API_URL=http://localhost:8000/api
```

If you don’t set this, the app uses `http://localhost:8000/api` by default (and appends `/v1` for requests).

### 2.3 Start the dev server

```bash
npm run dev
```

The app will open at **http://localhost:5173** (or the port Vite shows).  
Log in with a user you created (e.g. `admin@test.com` / `password123`).

**If login shows a generic error:** ensure the API URL matches where the backend runs. If you use `php artisan serve` on the same machine, `http://localhost:8000` or `http://127.0.0.1:8000` both work; set `VITE_API_URL=http://127.0.0.1:8000/api` if your backend is on 127.0.0.1. CORS is configured to allow `http://localhost:5173` and `http://127.0.0.1:5173`. After changing `backend/config/cors.php`, run `php artisan config:clear`.

---

## 3. Run both (two terminals)

**Terminal 1 – Backend**

```bash
cd backend
php artisan serve
```

**Terminal 2 – Frontend**

```bash
cd frontend
npm run dev
```

Then open **http://localhost:5173** in your browser and log in.

---

## 4. Production build (frontend)

To build the frontend for production:

```bash
cd frontend
npm run build
```

Serve the `frontend/dist` folder with your web server (Nginx, Apache, or Laravel’s `public` if you copy the build there). The backend stays as a separate API (e.g. `php artisan serve` or a PHP-FPM + Nginx setup).

---

## 5. Optional: create an admin user (backend)

If you have no user after seeding, create one and attach the “directeur” role:

```bash
cd backend
php artisan tinker
```

```php
$user = \App\Models\User::firstOrCreate(
    ['email' => 'admin@gims.ma'],
    ['name' => 'Admin', 'password' => 'Password123', 'role' => 'admin', 'is_active' => true]
);
$user->password = 'Password123';
$user->save();
$role = \App\Models\Role::where('slug', 'directeur')->first();
if ($role && !$user->roles()->where('roles.id', $role->id)->exists()) {
    $user->roles()->attach($role->id);
}
exit
```

Then log in in the frontend with `admin@gims.ma` / `Password123` (password must match the policy: 8+ chars, letter + number).

---

## 6. Testing user creation (Teacher / Admin)

See `backend/docs/USER_CREATION_API.md` for curl and Postman examples. Quick test:

```bash
# 1. Login to get token (copy "token" from response)
# curl -X POST "http://localhost:8000/api/v1/login" -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"email\":\"admin@gims.ma\",\"password\":\"Password123\"}"

# 2. Create teacher (replace YOUR_TOKEN with the token from step 1)
# curl -X POST "http://localhost:8000/api/v1/users" -H "Authorization: Bearer YOUR_TOKEN" -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"name\":\"Test Formateur\",\"email\":\"formateur1@gims.ma\",\"password\":\"Password123\",\"role\":\"formateur\",\"matricule\":\"F99999\",\"specialty\":\"Informatique\",\"type\":\"permanent\"}"
```

See `backend/docs/USER_CREATION_API.md` for full examples and Postman-ready JSON.

---

## 7. Emergency debug: is the backend reached? Is the token sent?

**1. Backend reachable (no auth):**  
Open in browser or curl:

```text
GET http://localhost:8000/api/v1/debug-ping
```

Expected: `{"ok":true,"message":"Backend reached","ts":"..."}`  
If you get nothing or connection refused → wrong host/port or backend not running.

**2. Token sent and Sanctum OK:**  
After logging in, open (with the app’s token in localStorage) or use curl with the token:

```text
GET http://localhost:8000/api/v1/debug-auth
Header: Authorization: Bearer YOUR_TOKEN
```

Expected: `{"ok":true,"user_id":1,"user_role":"admin"}`  
If 401 → token missing, wrong, or Sanctum blocking.  
If 200 → backend is reached and auth works; next check is role middleware on `/users`.
