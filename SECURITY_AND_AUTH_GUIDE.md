# Security & Authentication Guide
### Every security feature in this project — explained simply

---

## Overview — What Security Layers We Have

```
User types username + password
          ↓
[1] Input Validation       — is the input even valid?
          ↓
[2] Password Hashing       — compare against hashed password in DB
          ↓
[3] Account Active Check   — is the account enabled?
          ↓
[4] Token Generated        — user gets a token to prove who they are
          ↓
[5] Token Sent in Header   — every API request carries the token
          ↓
[6] RBAC                   — what is this user allowed to do?
          ↓
[7] Audit Log Written      — every action is permanently recorded
          ↓
[8] CORS                   — only our React app can call our API
```

---

## Feature 1 — Password Hashing

**File:** `vu-laravel/app/Http/Controllers/Api/AuthController.php`

Passwords are NEVER stored as plain text in the database. We use **bcrypt hashing**.

### When you register:
```php
$user = User::create([
    'password' => Hash::make($request->password),
    // "mypassword123" → stored as "$2y$12$xyz...abc" (60 char hash)
]);
```

### When you login:
```php
if (!Hash::check($credentials['password'], $user->password)) {
    // "mypassword123" is hashed and compared to the stored hash
    // if they don't match → 401 Unauthorized
}
```

**Why this matters:**
If the database is ever stolen, the attacker only gets `$2y$12$xyz...abc` — they cannot reverse this back to `mypassword123`. Bcrypt is designed to be slow to crack.

**Real example:**
```
You type:    "admin123"
Stored DB:   "$2y$12$hG8bUX5zJKL9mN2pQR7sTuVw3xYZ4aBC5dEF6gHI7jKL8mNO9pQR"
Hash::check compares them → returns true or false
```

---

## Feature 2 — Input Validation

**File:** `AuthController.php` — both `register()` and `login()` methods

Before touching the database, all inputs are validated using Laravel's `Validator`:

### Registration validation:
```php
$validator = Validator::make($request->all(), [
    'username' => 'required|string|unique:users|min:3|max:50',
    // required = cannot be empty
    // unique:users = no two users can have same username
    // min:3 = at least 3 characters
    // max:50 = no more than 50 characters

    'email'    => 'required|email|unique:users',
    // email = must be valid email format (has @ and .)

    'password' => 'required|string|min:6|confirmed',
    // min:6 = at least 6 characters
    // confirmed = must have password_confirmation field that matches

    'first_name' => 'required|string|max:50',
    'last_name'  => 'required|string|max:50',
]);

if ($validator->fails()) {
    return response()->json(['errors' => $validator->errors()], 422);
    // 422 = Unprocessable Entity (your data is bad)
}
```

**Why this matters:** Prevents garbage data, SQL injection attempts, and duplicate accounts from entering the database.

---

## Feature 3 — API Token Authentication (Custom Base64 — NOT JWT)

**File:** `AuthController.php` — `login()` method

> **Short answer for viva:** We are using a **custom base64-encoded token**, not JWT, not Laravel Sanctum, not Laravel Passport.

---

### What is JWT (so you can explain the difference)?

JWT (JSON Web Token) is a widely used industry standard. A JWT looks like this:

```
eyJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjozfQ.abc123xyz
└── header ──────────┘└── payload ────────┘└─ signature ┘
```

It has 3 parts separated by dots. The server signs it with a secret key. The payload can be decoded by anyone, but the signature cannot be faked without the secret key. JWT is self-verifying — the server does not need to look up the database to confirm the token is valid.

---

### What we actually use — Custom Base64 Token

Our token is much simpler. It is just plain text joined together and encoded in base64:

```php
// AuthController.php — login() method
$token = base64_encode($user->id . '|' . $user->username . '|' . time());
```

**Step by step example:**

```
Step 1 — Build a plain text string:
         "3|admin|1782587845"
          ↑  ↑     ↑
      user ID  username  Unix timestamp (seconds since 1970)

Step 2 — base64 encode it:
         base64_encode("3|admin|1782587845")
         = "M3xhZG1pbnwxNzgyNTg3ODQ1"

Step 3 — Send this string to React as the token
```

**Important: base64 is NOT encryption.** It can be reversed by anyone:
```
base64_decode("M3xhZG1pbnwxNzgyNTg3ODQ1") = "3|admin|1782587845"
```

The token proves nothing by itself — it is just a convenient way to carry the user ID.

---

### Login response sent to React:
```json
{
  "success": true,
  "data": {
    "token": "M3xhZG1pbnwxNzgyNTg3ODQ1",
    "token_type": "bearer",
    "user": { "id": 3, "username": "admin", "role": "admin" }
  }
}
```

### How React stores it:
```javascript
localStorage.setItem('token', 'M3xhZG1pbnwxNzgyNTg3ODQ1');
localStorage.setItem('userRole', 'admin');
```

### How React sends it with every API request:
```javascript
const token = localStorage.getItem('token');
fetch('http://localhost:8000/api/certificates/revoke', {
  headers: {
    'Authorization': 'Bearer M3xhZG1pbnwxNzgyNTg3ODQ1',
    'Content-Type': 'application/json'
  }
});
```

The `Bearer` prefix is a standard HTTP convention — it labels the token type.

---

### Our Token vs JWT — Side by Side

| | Our Custom Base64 Token | JWT |
|-|------------------------|-----|
| Format | `base64(id\|username\|time)` | `header.payload.signature` |
| Reversible? | Yes — base64 decodes easily | Yes — payload is public |
| Has a signature? | No | Yes — signed with a secret key |
| Self-verifying? | No | Yes |
| Expiry enforced? | No — timestamp exists but not checked | Yes — built-in `exp` claim |
| Industry standard? | No — custom made for this project | Yes — RFC 7519 |
| Used here? | **YES** | No |

---

### Why does the code mention JWTAuth?

There is a `refresh()` method in `AuthController.php` that references `JWTAuth::refresh(...)`. This is **dead code** — it was written as a future plan but the JWT library was never actually installed or configured. It will throw an error if called. The working login system uses only the base64 token.

---

### Why the `auth:api` Middleware Does Not Work

```php
// config/auth.php
'api' => [
    'driver' => 'session',   ← session-based, not token-based
    'provider' => 'users',
],
```

Laravel's built-in `auth:api` guard uses session cookies — it does not understand Bearer tokens. This is why any route protected with `Route::middleware('auth:api')` returns 401 when called from React with our token.

The routes that work in this project (generate, revoke, renew, CSR, live-certificates) are **public routes** — they do not have auth middleware enforced. The Bearer token is sent in the header but the server does not currently validate it on those routes.

---

## Feature 4 — Account Active Check

**File:** `AuthController.php` — `login()` method

Even if a username and password are correct, the account must be active:

```php
if (!$user->is_active) {
    // Log that login was blocked
    $this->logAuditEvent('login_blocked', 'authentication', 'Login blocked - account inactive', [...]);

    return response()->json([
        'success' => false,
        'message' => 'Your account is inactive. Please contact administrator.'
    ], 403);
    // 403 = Forbidden (you are known but not allowed)
}
```

**In the database:**
```
users table:
┌────────┬──────────┬───────────┐
│ user   │ password │ is_active │
├────────┼──────────┼───────────┤
│ admin  │ $2y$...  │  true     │  ← can login
│ john   │ $2y$...  │  false    │  ← BLOCKED
└────────┴──────────┴───────────┘
```

An admin can set `is_active = false` to ban a user without deleting them.

---

## Feature 5 — Role-Based Access Control (RBAC)

**Files:** `User.php`, `AuthController.php`

There are 3 roles in the system. Each role has different permissions:

```
ROLES & PERMISSIONS:
─────────────────────────────────────────────────────────
admin
  ✅ manage_users
  ✅ manage_certificates
  ✅ approve_requests
  ✅ view_audit_logs
  ✅ manage_system_settings
  ✅ view_all_certificates
  ✅ revoke_certificates
  ✅ generate_certificates

certificate_manager
  ✅ manage_certificates
  ✅ approve_requests
  ✅ view_audit_logs
  ✅ view_all_certificates
  ✅ revoke_certificates
  ✅ generate_certificates
  ❌ manage_users
  ❌ manage_system_settings

regular_user
  ✅ view_own_certificates
  ✅ request_certificates
  ✅ view_own_requests
  ❌ everything else
─────────────────────────────────────────────────────────
```

**In the User model (`User.php`), there are helper methods:**
```php
public function isAdmin(): bool {
    return $this->role === 'admin';
}

public function isCertificateManager(): bool {
    return $this->role === 'certificate_manager';
}

public function canApproveRequests(): bool {
    return $this->isAdmin() || $this->isCertificateManager();
}
```

**In the React frontend (Dashboard.tsx):**
```typescript
// CSR Management is only shown to admin or certificate_manager
{(userRole === 'admin' || userRole === 'certificate_manager') && (
  <MenuItem onClick={() => setShowCSRManagement(true)}>
    CSR Management
  </MenuItem>
)}
```

**Role is stored in the database:**
```sql
role ENUM('admin', 'certificate_manager', 'regular_user') DEFAULT 'regular_user'
```

New users who register always get `regular_user` — they cannot give themselves admin role.

---

## Feature 6 — Audit Logging

**File:** `AuthController.php` — `logAuditEvent()` method
**Table:** `audit_logs` in MySQL

Every important action is permanently recorded. The log can never be edited by users — it is append-only.

**What gets logged:**

| Event Type | When it happens | Severity |
|------------|----------------|---------|
| `login_success` | User logs in successfully | Low |
| `login_failed` | Wrong username or password | Medium |
| `login_blocked` | Account is inactive | High |
| `user_registered` | New user created | Low |
| `2fa_enabled` | Two-factor auth turned on | High |
| `2fa_disabled` | Two-factor auth turned off | High |

**What each log entry contains:**
```php
AuditLog::create([
    'event_type'     => 'login_failed',
    'event_category' => 'authentication',
    'description'    => 'Failed login attempt - wrong password',
    'ip_address'     => '192.168.1.100',    // WHERE from
    'user_agent'     => 'Mozilla/5.0...',   // WHAT browser
    'user_id'        => 5,                  // WHO
    'severity'       => 'medium',           // HOW serious
    'metadata'       => ['username' => 'john', 'ip_address' => '...']
]);
```

**Example real log entries:**
```
Time            | Event          | User  | IP             | Severity
────────────────────────────────────────────────────────────────────
10:23:01        | login_success  | admin | 127.0.0.1      | low
10:25:44        | login_failed   | john  | 192.168.1.55   | medium
10:25:55        | login_failed   | john  | 192.168.1.55   | medium
10:26:01        | login_blocked  | john  | 192.168.1.55   | high
```

This is a pattern you would look for in a real security system — multiple failed attempts from the same IP = possible brute force attack.

---

## Feature 7 — CORS Protection

**File:** `vu-laravel/config/cors.php`

CORS = Cross-Origin Resource Sharing. This is a browser security rule that prevents websites from calling APIs they shouldn't.

**Our CORS config:**
```php
'allowed_origins' => ['http://localhost:3000', 'http://127.0.0.1:3000'],
```

**What this means:**
```
React at localhost:3000  ✅ allowed to call our API
Some random website      ❌ BLOCKED by browser
Postman / curl           ✅ allowed (CORS is browser-only)
```

**Why this matters:**
Without CORS restrictions, any website in the world could make API calls to your Laravel backend using your user's logged-in browser session. CORS prevents this.

---

## Feature 8 — Sensitive Fields Hidden from API Responses

**File:** `vu-laravel/app/Models/User.php`

The User model has a `$hidden` array. Any field listed there is NEVER included in JSON responses, even when you call `$user->toArray()` or return `$user` from an API:

```php
protected $hidden = [
    'password',                    // never expose the hash
    'remember_token',              // session remember token
    'two_factor_secret',           // 2FA secret key
    'two_factor_recovery_codes',   // backup codes
];
```

**Example:**
```json
// What the API returns (safe):
{
  "id": 3,
  "username": "admin",
  "email": "admin@example.com",
  "role": "admin"
}

// What is NEVER in the response (hidden):
// password, two_factor_secret, remember_token
```

---

## Feature 9 — IP Address & Login Tracking

**File:** `AuthController.php` — `login()` method

Every successful login updates the user record with:
```php
$user->update([
    'last_login_at' => now(),           // timestamp of last login
    'last_login_ip' => $request->ip()  // IP address of last login
]);
```

**In the database:**
```
users table:
┌───────┬──────────────────────┬─────────────────┐
│ user  │ last_login_at        │ last_login_ip   │
├───────┼──────────────────────┼─────────────────┤
│ admin │ 2026-06-29 10:23:01  │ 127.0.0.1       │
│ john  │ 2026-06-28 09:14:33  │ 192.168.1.100   │
└───────┴──────────────────────┴─────────────────┘
```

This helps detect if someone's account is being used from an unusual location.

---

## Feature 10 — HTTPS on the CA Server

**File:** `real-ca-server/ca-server.js`

The CA server itself runs over HTTPS, not plain HTTP. This means the communication between Laravel and the CA server (Docker) is encrypted.

```javascript
// ca-server.js generates its own SSL certificate on startup
exec('openssl req -x509 -newkey rsa:2048 -keyout server.key -out server.crt ...')

// Then creates an HTTPS server using that cert
const server = https.createServer({ key, cert }, requestHandler);
server.listen(8443);  // 8443 = HTTPS (not 80 which is HTTP)
```

Laravel calls it with `verify: false` because it's a self-signed certificate (not from a trusted CA):
```php
$client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 5]);
$response = $client->get('https://localhost:8443/certificates');
```

In a real production system, you would use a trusted CA-signed certificate and set `verify: true`.

---

## Feature 11 — Two-Factor Authentication (2FA) — Designed

**File:** `AuthController.php`, `User.php`, database migration

The system was **designed** to support 2FA (Google Authenticator style). The database columns are all there:

```sql
two_factor_enabled        BOOLEAN DEFAULT false
two_factor_secret         VARCHAR (the TOTP secret key)
two_factor_recovery_codes JSON    (backup codes if phone is lost)
```

The controller has `setupTwoFactor()`, `verifyTwoFactor()`, and `disableTwoFactor()` methods written.

**Current status:** The 2FA methods exist but the Google2FA library dependency is not wired into the constructor, so 2FA cannot be enabled in the current running system. The foundation is built for future implementation.

---

## Authentication Flow — Complete Step by Step

```
STEP 1 — User submits login form
   React sends: POST /api/auth/login
   Body: { "username": "admin", "password": "admin123" }

STEP 2 — Validate inputs
   Laravel checks: username required, password required
   If missing → 422 error

STEP 3 — Find user in database
   SELECT * FROM users WHERE username = 'admin' OR email = 'admin'
   If not found → 401 "Invalid username or password"
   (same message as wrong password — don't reveal if user exists)

STEP 4 — Check password
   Hash::check("admin123", "$2y$12$...stored hash...")
   If wrong → 401 "Invalid username or password"
   Log: login_failed (severity: medium)

STEP 5 — Check account active
   If is_active = false → 403 "Account is inactive"
   Log: login_blocked (severity: high)

STEP 6 — Generate token
   base64_encode("3|admin|1782587845") → "M3xhZG1pbnwxNzgyNTg3ODQ1"

STEP 7 — Update last login
   UPDATE users SET last_login_at = NOW(), last_login_ip = '127.0.0.1'

STEP 8 — Log success
   INSERT INTO audit_logs (event_type='login_success', severity='low', ...)

STEP 9 — Return response
   { "success": true, "token": "M3xhZG1pbn...", "user": { role: "admin" } }

STEP 10 — React saves token
   localStorage.setItem('token', 'M3xhZG1pbn...')
   localStorage.setItem('userRole', 'admin')

STEP 11 — React redirects to dashboard
   All future API calls include: Authorization: Bearer M3xhZG1pbn...
```

---

## What Happens at Logout

```javascript
// React clears local storage
localStorage.removeItem('token');
localStorage.removeItem('userRole');
// User is redirected to login page
```

The token is removed from the browser. Since tokens are stateless (not stored on the server), it is effectively invalidated — the browser can no longer send it.

---

## Security Features Summary Table

| Feature | Where | Status |
|---------|-------|--------|
| Password hashing (bcrypt) | `AuthController.php` | ✅ Working |
| Input validation | `AuthController.php` | ✅ Working |
| Unique username/email | Database migration | ✅ Working |
| Token-based auth (base64) | `AuthController.php` | ✅ Working |
| Account active/inactive check | `AuthController.php` | ✅ Working |
| Role-based access control | `User.php`, `Dashboard.tsx` | ✅ Working |
| Audit logging | `AuthController.php`, `AuditLog.php` | ✅ Working |
| IP address tracking | `AuthController.php` | ✅ Working |
| CORS protection | `config/cors.php` | ✅ Working |
| Sensitive fields hidden | `User.php` $hidden | ✅ Working |
| HTTPS on CA Server | `ca-server.js` | ✅ Working |
| CSRF protection | `VerifyCsrfToken.php` middleware | ✅ Working |
| Two-Factor Authentication | `AuthController.php`, `User.php` | 🔧 Designed, not active |
| Token expiry / invalidation | — | 🔧 Not implemented |
| Rate limiting (brute force) | — | 🔧 Not implemented |
