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

This creates roles/permissions and syncs user roles. If you need a user to log in, create one (e.g. via tinker or a seeder):

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
$user = \App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@gims.ma',
    'password' => bcrypt('Password123'),
    'role' => 'admin',
    'is_active' => true,
]);
$role = \App\Models\Role::where('slug', 'directeur')->first();
if ($role) $user->roles()->attach($role->id);
exit
```

Then log in in the frontend with `admin@gims.ma` / `Password123` (password must match the policy: 8+ chars, letter + number).
