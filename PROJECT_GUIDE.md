# VuProject – Complete Study Guide for Viva/Interview

> This guide explains everything about this project in simple language so you can confidently answer any question in your viva or interview.

---

## Table of Contents

1. [What is this Project?](#1-what-is-this-project)
2. [Big Picture – How Everything Connects](#2-big-picture--how-everything-connects)
3. [Languages & Technologies Used](#3-languages--technologies-used)
4. [Frontend (React) – Explained Simply](#4-frontend-react--explained-simply)
5. [Backend (Laravel) – Explained Simply](#5-backend-laravel--explained-simply)
6. [CA Server (Node.js) – Explained Simply](#6-ca-server-nodejs--explained-simply)
7. [Docker – Explained Simply](#7-docker--explained-simply)
8. [Database – Explained Simply](#8-database--explained-simply)
9. [How Authentication Works (Login/Logout)](#9-how-authentication-works-loginlogout)
10. [How Certificates Work (The Core Logic)](#10-how-certificates-work-the-core-logic)
11. [CSR Workflow – The Approval Process](#11-csr-workflow--the-approval-process)
12. [User Roles & Permissions](#12-user-roles--permissions)
13. [Monitoring & Logging](#13-monitoring--logging)
14. [All Service Ports (Quick Reference)](#14-all-service-ports-quick-reference)
15. [How to Run the Project](#15-how-to-run-the-project)
16. [API Endpoints Quick Reference](#16-api-endpoints-quick-reference)
17. [Viva Questions & Answers](#17-viva-questions--answers)

---

## 1. What is this Project?

This project is an **SSL/TLS Certificate Management System**.

**What does that mean in simple words?**

When you open a website like `https://google.com`, the little padlock icon in your browser means the website has an **SSL certificate** — a digital document that proves the website is safe and real.

This project is a **web application that:**
- Creates and manages those SSL certificates
- Allows admins to approve or reject certificate requests
- Shows all certificates on a dashboard
- Tracks everything in an audit log
- Has a built-in Certificate Authority (CA) that actually signs and issues certificates

Think of a **Certificate Authority** like a government office that issues passports. Just like a passport proves who you are, an SSL certificate proves a website is genuine.

---

## 2. Big Picture – How Everything Connects

```
┌─────────────────────────────────────────────────────────────────┐
│                        USER'S BROWSER                           │
│                    (Opens localhost:3000)                        │
└─────────────────────┬───────────────────────────────────────────┘
                      │ HTTP Requests (React sends API calls)
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│               FRONTEND  (React + TypeScript)                     │
│                    Port: 3000                                    │
│   - Shows Dashboard, Login, Certificate List, CSR Forms          │
│   - Sends requests to Backend using Axios                        │
└─────────────────────┬───────────────────────────────────────────┘
                      │ API Calls to http://localhost:8000/api
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│               BACKEND  (Laravel + PHP)                           │
│                    Port: 8000                                    │
│   - Handles all business logic                                   │
│   - Manages users, certificates, CSRs                            │
│   - Talks to Database and CA Server                              │
└────────────┬──────────────────────────┬───────────────────────  ┘
             │                          │
             ▼                          ▼
┌────────────────────┐      ┌──────────────────────────────────┐
│   MySQL Database   │      │   CA Server  (Node.js)           │
│    Port: 3306      │      │      Port: 8443 (HTTPS)          │
│  Stores all data   │      │  Actually creates & signs        │
│  (users, certs,    │      │  SSL certificates                │
│   logs, CSRs)      │      └──────────────────────────────────┘
└────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     DOCKER                                       │
│   Runs ALL of the above services in isolated containers          │
│   One command starts everything together                         │
└─────────────────────────────────────────────────────────────────┘
```

**Simple flow for a user requesting a certificate:**
1. User opens the website (React on port 3000)
2. User fills out a CSR (Certificate Signing Request) form
3. React sends that data to Laravel API (port 8000)
4. Laravel saves it to MySQL database
5. Admin logs in and approves it
6. Laravel tells the CA Server to generate the certificate
7. CA Server creates the certificate and sends it back
8. Laravel saves the certificate to the database
9. React shows the certificate on the dashboard

---

## 3. Languages & Technologies Used

| Layer | Technology | Language | What it does |
|-------|-----------|----------|--------------|
| Frontend | React 19 | TypeScript | The website/user interface |
| Backend | Laravel 12 | PHP 8.3 | Business logic and API |
| CA Server | Node.js | JavaScript | Creates SSL certificates |
| Database | MySQL 8.0 | SQL | Stores all data |
| Container | Docker | YAML (config) | Runs all services together |
| Reverse Proxy | Nginx | Config file | Routes traffic |
| Monitoring | Prometheus + Grafana | Config/Query | Tracks system health |
| Logging | Elasticsearch + Kibana | Config | Stores and displays logs |
| Styling | Material-UI (MUI) | TypeScript/CSS | Makes the UI look good |

### Why these specific technologies?

- **React** – Most popular frontend framework. Component-based (reusable pieces of UI).
- **TypeScript** – JavaScript with type checking. Catches bugs before running code.
- **Laravel** – Most popular PHP framework. Follows MVC pattern. Has built-in tools for auth, database, routing.
- **Node.js for CA Server** – Lightweight, fast, has built-in `crypto` and `https` modules.
- **MySQL** – Reliable relational database, perfect for structured data like certificates.
- **Docker** – Makes it easy to run the project on any computer without installing everything manually.

---

## 4. Frontend (React) – Explained Simply

**Location:** `vu-react/` folder

### What is React?

React is a JavaScript library for building user interfaces. Instead of writing a whole page, you build small **components** (like Lego pieces) and put them together.

### Main Pages/Components

| File | What it shows |
|------|--------------|
| `Login.tsx` | Login form |
| `Register.tsx` | Registration form |
| `Dashboard.tsx` | Main page showing all certificates (1,122 lines – the biggest page) |
| `CSRGenerator.tsx` | Form to create a new Certificate Signing Request |
| `CSRManagement.tsx` | Admin page to approve/reject CSRs |
| `CertificateCharts.tsx` | Visual charts showing certificate statistics |
| `StatusPage.tsx` | Shows if all services are running |

### How React talks to the Backend

React uses a library called **Axios** to send HTTP requests to the Laravel API.

**File:** `vu-react/src/services/api.ts`

```
React Component  →  Calls api.ts  →  Axios sends HTTP request  →  Laravel API
```

**Example in simple terms:**
```
When user clicks "Login":
  1. Login.tsx collects username + password
  2. Calls api.login(username, password)
  3. api.ts sends POST request to http://localhost:8000/api/auth/login
  4. Laravel checks credentials, returns a token
  5. React stores the token in localStorage
  6. React redirects user to Dashboard
```

### Axios Interceptors (Important Concept)

Axios has two interceptors set up:

1. **Request interceptor** – Before every request, it automatically adds the auth token to the header:
   ```
   Authorization: Bearer <token>
   ```

2. **Response interceptor** – If the server returns `401 Unauthorized`, it automatically logs the user out and redirects to login page.

### TypeScript in this Project

TypeScript adds "types" to JavaScript. For example:
```typescript
// Instead of just: let name = "John"
// TypeScript makes you define what type it is:
let name: string = "John"
let age: number = 25
let isAdmin: boolean = true
```

This prevents bugs where you might accidentally pass a number where a string is expected.

---

## 5. Backend (Laravel) – Explained Simply

**Location:** `vu-laravel/` folder

### What is Laravel?

Laravel is a PHP framework that follows the **MVC pattern**:
- **M = Model** → Represents database tables (e.g., `User.php`, `Certificate.php`)
- **V = View** → In this project, React is the View (Laravel is API-only)
- **C = Controller** → Handles requests, applies logic, returns responses

### How Laravel handles a request

```
HTTP Request comes in
       ↓
routes/api.php (decides which controller handles it)
       ↓
Controller (e.g., CertificateController.php)
       ↓
Service (e.g., CertificateService.php) – does the heavy logic
       ↓
Model (e.g., Certificate.php) – talks to the database
       ↓
Database (MySQL) – saves/retrieves data
       ↓
Controller sends JSON response back to React
```

### Controllers (6 total)

| Controller | Handles |
|-----------|---------|
| `AuthController.php` (413 lines) | Login, register, logout, get current user |
| `CertificateController.php` (442 lines) | Create, list, delete, renew, revoke certificates |
| `ACMEController.php` (415 lines) | ACME protocol (automated certificate issuance) |
| `UserController.php` (210 lines) | User management (admin only) |
| `AuditLogController.php` | View audit logs |
| `NotificationController.php` | Check and list notifications |

### Services (The Business Logic Layer)

Services contain the actual complex logic so controllers stay clean:

| Service | What it does |
|---------|-------------|
| `CertificateService.php` | Manages certificate lifecycle (create, renew, revoke) |
| `ACMEService.php` | Implements the ACME protocol (RFC 8555) for automated cert issuance |
| `StepCAService.php` | Communicates with the Step CA server |
| `NotificationService.php` | Sends email notifications about expiring certs |
| `CacheService.php` | Caches frequently accessed data (faster responses) |
| `VaultService.php` | Integration with HashiCorp Vault (secret storage) |
| `DatabaseBackupService.php` | Automated database backups |
| `OAuth2Service.php` | OAuth 2.0 authentication support |

### Database Models

| Model | Table | Stores |
|-------|-------|--------|
| `User.php` | users | User accounts, roles, 2FA info |
| `Certificate.php` | certificates | All SSL certificates |
| `CertificateRequest.php` | certificate_requests | CSR approval workflow |
| `AuditLog.php` | audit_logs | Complete history of all actions |
| `Notification.php` | notifications | System notifications |

### CORS Configuration (Important)

CORS = Cross-Origin Resource Sharing. Since React runs on port 3000 and Laravel on port 8000, the browser would normally block requests between them. Laravel is configured to allow requests from the React frontend.

---

## 6. CA Server (Node.js) – Explained Simply

**Location:** `real-ca-server/` folder  
**Key File:** `real-ca-server/ca-server.js`

### What is a CA (Certificate Authority)?

A Certificate Authority is a trusted entity that **signs digital certificates**. When a CA signs a certificate, it's saying: "I verify this website is legitimate."

Think of it like a notary public who stamps documents to make them officially recognized.

### What does this CA Server do?

- Runs as a separate HTTPS server on port 8443
- Has its own self-signed certificate (`server.crt` + `server.key`)
- Stores certificates in memory
- Exposes REST API endpoints for Laravel to call

### Why Node.js for the CA Server?

Node.js has built-in modules:
- `https` – Run an HTTPS server
- `crypto` – Generate cryptographic keys and certificates
- `fs` – Read certificate files from disk

No external libraries needed, which keeps it lightweight.

### Pre-loaded Certificates

The CA server starts with 4 default certificates already in memory:
- `127.0.0.1` (localhost IP)
- `localhost`
- `wacman.com`
- `192.168.3.92` (local network IP)

### CA Server Endpoints

| Endpoint | Method | Does |
|---------|--------|------|
| `/health` | GET | Returns "I'm running" status |
| `/certificates` | GET | Lists all certificates |
| `/certificates/generate` | POST | Creates a new certificate |
| `/certificates/renew` | POST | Renews an existing certificate |
| `/certificates/revoke` | POST | Revokes a certificate |

### How Laravel uses the CA Server

```
User requests a certificate in React
       ↓
Laravel receives the request
       ↓
CertificateService.php calls CA Server:
POST https://localhost:8443/certificates/generate
       ↓
CA Server generates the certificate
       ↓
CA Server returns certificate data (public key, serial number, expiry)
       ↓
Laravel saves it to MySQL database
       ↓
Laravel sends response back to React
       ↓
React shows the new certificate on the dashboard
```

---

## 7. Docker – Explained Simply

**Key File:** `docker-compose.yml`

### What is Docker?

Docker packages an application and all its dependencies into a **container** – like a sealed box that has everything the application needs to run.

**Without Docker:** You need to install PHP, Node.js, MySQL, Redis, etc. on your computer manually.

**With Docker:** Run one command and everything starts automatically.

### What is Docker Compose?

Docker Compose is a tool that lets you define and run **multiple containers at once** using a single YAML file (`docker-compose.yml`).

### All 12 Services in Docker

```
docker-compose.yml defines these services:

┌─────────────────┐
│     Nginx       │  Port 80, 443  ← Entry point (like a receptionist)
│  (Reverse Proxy)│               Routes traffic to the right service
└────────┬────────┘
         │
    ┌────┴────┐
    ▼         ▼
┌───────┐  ┌──────────┐
│ React │  │  Laravel │
│ :3000 │  │  :8000   │
└───────┘  └────┬─────┘
                │
       ┌────────┼────────┐
       ▼        ▼        ▼
┌────────┐ ┌───────┐ ┌─────────┐
│ MySQL  │ │ Redis │ │ Step-CA │
│ :3306  │ │ :6379 │ │ :8443   │
└────────┘ └───────┘ └─────────┘

Monitoring Stack:
┌────────────┐  ┌─────────┐
│ Prometheus │  │ Grafana │
│   :9090    │  │  :3001  │
└────────────┘  └─────────┘

Logging Stack:
┌───────────────┐  ┌─────────┐  ┌──────────┐
│ Elasticsearch │  │ Kibana  │  │ Logstash │
│    :9200      │  │  :5601  │  │  :5044   │
└───────────────┘  └─────────┘  └──────────┘

Security:
┌───────┐
│ Vault │  ← Stores secrets securely
│ :8200 │
└───────┘
```

### What each Docker service does

| Service | Port | Role |
|---------|------|------|
| **Nginx** | 80, 443 | Reverse proxy – routes incoming requests |
| **React** | 3000 | Serves the frontend website |
| **Laravel** | 8000 | Serves the backend API |
| **MySQL** | 3307 | Database – stores all application data |
| **Redis** | 6379 | Cache – speeds up repeated queries |
| **Step-CA** | 8443 | Certificate Authority |
| **Prometheus** | 9090 | Collects metrics from all services |
| **Grafana** | 3001 | Displays metrics as visual dashboards |
| **Elasticsearch** | 9200 | Stores and searches through logs |
| **Kibana** | 5601 | Visual interface for Elasticsearch logs |
| **Logstash** | 5044 | Processes and forwards logs |
| **Vault** | 8200 | Securely stores passwords and secrets |

### What is a Reverse Proxy (Nginx)?

Nginx acts like a traffic director. Instead of remembering different ports, users just go to `http://localhost` and Nginx figures out where to send the request:

```
/api/*        →  Laravel (port 8000)
/*            →  React (port 3000)
```

### Docker Networking

All containers are on the same **internal Docker network** called `vuproject-network`. This means containers can talk to each other using service names:
- Laravel connects to MySQL using hostname `mysql` (not `127.0.0.1`)
- Laravel connects to Redis using hostname `redis`
- React connects to Laravel using hostname `laravel`

---

## 8. Database – Explained Simply

**Database:** MySQL 8.0

### The 5 Tables

#### 1. `users` table
Stores everyone who has an account.
```
id | username | email | password (hashed) | role | two_factor_enabled | last_login | active
```

#### 2. `certificates` table
Stores all SSL certificates.
```
id | serial_number | common_name | issuer | subject | valid_from | valid_to | status | certificate_data | user_id
```

#### 3. `certificate_requests` table
Stores CSR submissions (before they're approved).
```
id | user_id | common_name | organization | status (pending/approved/rejected) | admin_notes | approved_by | approved_at
```

#### 4. `audit_logs` table
Records every action taken in the system (like a CCTV record).
```
id | user_id | event_type | description | severity | ip_address | user_agent | created_at
```

#### 5. `notifications` table
Stores alerts (e.g., "Certificate expiring in 7 days").
```
id | user_id | type | title | message | read_at | created_at
```

### How Laravel talks to MySQL

Laravel uses **Eloquent ORM** (Object-Relational Mapping). Instead of writing raw SQL, you write PHP code:

```php
// Raw SQL (old way):
SELECT * FROM certificates WHERE user_id = 1;

// Eloquent (Laravel way):
Certificate::where('user_id', 1)->get();
```

Eloquent converts PHP to SQL automatically. Each Model (like `Certificate.php`) represents a database table.

---

## 9. How Authentication Works (Login/Logout)

### Step-by-step Login Flow

```
1. User enters username + password on React Login page

2. React sends POST request:
   POST http://localhost:8000/api/auth/login
   Body: { username: "admin", password: "admin123" }

3. Laravel's AuthController receives it
   - Checks if user exists in the database
   - Verifies password using bcrypt hash comparison
   - Creates a session token

4. Laravel returns:
   { token: "abc123xyz...", user: { id: 1, role: "admin", ... } }

5. React stores the token in localStorage

6. Every future request includes the token:
   Authorization: Bearer abc123xyz...

7. Laravel validates the token on each request
   (via auth:api middleware in routes)
```

### User Roles

| Role | What they can do |
|------|----------------|
| `admin` | Everything – manage users, approve CSRs, revoke certs, view logs |
| `certificate_manager` | Approve/reject CSRs, manage certificates |
| `regular_user` | Submit CSR requests, view their own certificates |

### 2FA (Two-Factor Authentication)

The system supports TOTP (Time-based One-Time Password) – like Google Authenticator. When enabled:
1. User logs in with password
2. User is asked for a 6-digit code from their authenticator app
3. Only then are they granted access

---

## 10. How Certificates Work (The Core Logic)

### What is an SSL Certificate?

An SSL certificate is a digital document containing:
- **Common Name (CN)** – The domain (e.g., `example.com`)
- **Serial Number** – Unique ID
- **Valid From / Valid To** – Expiry dates
- **Public Key** – Used for encryption
- **Signature** – Signed by the CA (proves it's genuine)

### Certificate Lifecycle

```
REQUEST → PENDING → APPROVED → ACTIVE → EXPIRED
                    ↓
               (or REJECTED)
               
ACTIVE certificates can also be → REVOKED (cancelled early)
```

### What is a CSR (Certificate Signing Request)?

A CSR is like a passport application form. It contains:
- Who is requesting (organization name, email)
- What domain it's for (common name)
- A public key that the certificate should be associated with

The CA server takes the CSR, verifies it, and issues a signed certificate.

### How Certificate Generation Works

```
React → POST /api/certificates/generate
         ↓
      CertificateController.php
         ↓
      CertificateService.php
         ↓ calls CA Server
      POST https://localhost:8443/certificates/generate
         ↓
      CA Server (Node.js)
      - Generates key pair
      - Creates certificate with validity period
      - Signs it with CA's private key
      - Returns certificate data
         ↓
      Laravel saves to MySQL
         ↓
      Response sent to React
         ↓
      Dashboard shows new certificate
```

### Certificate Renewal

When a certificate is close to expiry:
- The notification system sends an alert
- Admin or user can request renewal
- Process is same as generation but with the existing domain

### Certificate Revocation

Revoking a certificate means marking it as invalid before its expiry date. This is done when:
- Private key is compromised
- Domain ownership changes
- No longer needed

---

## 11. CSR Workflow – The Approval Process

### The Full Flow

```
Step 1: Regular user submits CSR form in React
        (fills: domain name, organization, email, etc.)
        ↓
Step 2: React sends POST /api/csr/generate
        ↓
Step 3: Laravel saves it to certificate_requests table
        Status: "pending"
        ↓
Step 4: Notification is created for admins
        ↓
Step 5: Admin sees pending CSR on CSR Management page
        ↓
Step 6a: Admin APPROVES → POST /api/csr/approve/{id}
         Laravel calls CA Server to generate certificate
         Certificate saved to certificates table
         CSR status updated to "approved"
         User notified

Step 6b: Admin REJECTS → POST /api/csr/reject/{id}
         CSR status updated to "rejected"
         Admin can provide rejection reason
         User notified
```

---

## 12. User Roles & Permissions

### Role Hierarchy

```
ADMIN
  └── Can do everything
  └── Manage all users
  └── Approve/reject CSRs
  └── View all audit logs
  └── Generate/revoke any certificate

CERTIFICATE_MANAGER
  └── Approve/reject CSRs
  └── Manage certificates
  └── Cannot manage users

REGULAR_USER
  └── Submit CSR requests
  └── View own certificates
  └── View own notifications
```

### How Permissions are Enforced

In Laravel routes (`routes/api.php`), middleware is applied:

```php
// Public routes - no auth needed
Route::post('/auth/login', ...);
Route::post('/auth/register', ...);

// Protected routes - must be logged in
Route::middleware('auth:api')->group(function () {
    Route::get('/certificates', ...);
    
    // Admin only routes
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', ...);
        Route::get('/audit-logs', ...);
    });
});
```

---

## 13. Monitoring & Logging

### Prometheus (Metrics Collection)

Prometheus is like a health monitor. It:
- Scrapes metrics from each service every few seconds
- Stores them as time-series data
- Example metrics: request count, response time, CPU usage

### Grafana (Visual Dashboard)

Grafana connects to Prometheus and shows the metrics as beautiful charts:
- Certificate issuance rate
- API response times
- Error rates
- System resource usage

### ELK Stack (Logging)

ELK = Elasticsearch + Logstash + Kibana

```
Application generates logs
       ↓
  Logstash collects and processes logs
       ↓
  Elasticsearch stores logs (searchable)
       ↓
  Kibana lets you search and visualize logs
```

**Example:** If something fails at 2 AM, you can search Kibana for error logs and find exactly what went wrong.

---

## 14. All Service Ports (Quick Reference)

| Service | URL | What to open |
|---------|-----|-------------|
| Frontend (React) | http://localhost:3000 | The main website |
| Backend (Laravel) | http://localhost:8000 | API (not opened in browser) |
| CA Server | https://localhost:8443 | Certificate server |
| MySQL | localhost:3307 | Database (use MySQL client) |
| Redis | localhost:6379 | Cache (use Redis CLI) |
| Nginx | http://localhost | Reverse proxy entry point |
| Prometheus | http://localhost:9090 | Metrics explorer |
| Grafana | http://localhost:3001 | Monitoring dashboards |
| Elasticsearch | http://localhost:9200 | Log storage |
| Kibana | http://localhost:5601 | Log visualization |
| Vault | http://localhost:8200 | Secret management |

**Default Login:**
- Username: `admin`
- Password: `admin123`

---

## 15. How to Run the Project

### Option A: Run Everything with Docker (Recommended)

This starts all 12 services with a single command.

```bash
# Step 1: Make sure Docker Desktop is running on your Mac

# Step 2: Go to project folder
cd /Users/umair/Herd/vu-project

# Step 3: Start all services
docker-compose up

# OR run in background (detached mode)
docker-compose up -d

# Step 4: Check if all services are running
docker-compose ps

# Step 5: Open browser
# Frontend: http://localhost:3000
# OR: http://localhost (via Nginx)

# To stop all services
docker-compose down

# To stop AND delete all data (fresh start)
docker-compose down -v
```

### Option B: Run Frontend Separately (Development)

```bash
# Go to React folder
cd /Users/umair/Herd/vu-project/vu-react

# Install dependencies (first time only)
npm install

# Start development server
npm start

# Website opens at: http://localhost:3000

# Build for production
npm run build
```

### Option C: Run Backend Separately (Development)

```bash
# Go to Laravel folder
cd /Users/umair/Herd/vu-project/vu-laravel

# Install PHP dependencies (first time only)
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run database migrations (creates all tables)
php artisan migrate

# Seed database with sample data
php artisan db:seed

# Start development server
php artisan serve

# Laravel runs at: http://localhost:8000
```

### Option D: Run CA Server Separately

```bash
# Go to CA Server folder
cd /Users/umair/Herd/vu-project/real-ca-server

# No npm install needed (uses built-in Node.js modules)

# Start CA Server
node ca-server.js

# CA Server runs at: https://localhost:8443
```

### Useful Docker Commands

```bash
# View logs of a specific service
docker-compose logs laravel
docker-compose logs react
docker-compose logs mysql

# Follow logs in real-time
docker-compose logs -f laravel

# Restart a specific service
docker-compose restart laravel

# Enter a container's shell
docker-compose exec laravel bash
docker-compose exec mysql bash

# Run artisan commands inside Laravel container
docker-compose exec laravel php artisan migrate
docker-compose exec laravel php artisan db:seed
docker-compose exec laravel php artisan route:list

# Check container resource usage
docker stats
```

### Useful Laravel Artisan Commands

```bash
# List all API routes
php artisan route:list

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Run database migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Fresh migration (drops all tables and re-creates)
php artisan migrate:fresh --seed

# Open interactive PHP shell
php artisan tinker
```

---

## 16. API Endpoints Quick Reference

**Base URL:** `http://localhost:8000/api`

### Auth Endpoints (No login required)
```
POST /auth/register     Register new user
POST /auth/login        Login and get token
```

### Auth Endpoints (Login required)
```
POST /auth/logout       Logout
GET  /auth/me           Get current user info
```

### Certificate Endpoints (Login required)
```
GET    /certificates              List all certificates
POST   /certificates              Create certificate
GET    /certificates/{id}         Get one certificate
DELETE /certificates/{id}         Delete certificate
POST   /certificates/generate     Generate new certificate
POST   /certificates/renew        Renew certificate
POST   /certificates/revoke       Revoke certificate
```

### CSR Endpoints (Login required)
```
POST /csr/generate          Submit new CSR
GET  /csr/list              List all CSRs
POST /csr/approve/{id}      Approve a CSR (admin only)
POST /csr/reject/{id}       Reject a CSR (admin only)
```

### Health Check (No login required)
```
GET /health              Check if API is running
GET /ca-health           Check if CA Server is running
GET /live-certificates   Get certificates from CA Server
```

---

## 17. Viva Questions & Answers

**Q: What is this project about?**

A: It is a Certificate Management System that allows organizations to issue, manage, renew, and revoke SSL/TLS certificates. It has a React frontend, Laravel backend API, a Node.js Certificate Authority server, and is fully containerized with Docker.

---

**Q: What is the frontend built with?**

A: The frontend is built with React 19 using TypeScript. It uses Material-UI (MUI) for styling, Axios for HTTP requests to the backend, and React Router for navigation between pages.

---

**Q: What is the backend built with?**

A: The backend is a Laravel 12 application written in PHP 8.3. It follows the MVC pattern, uses Eloquent ORM to interact with MySQL, and exposes a REST API that the React frontend consumes.

---

**Q: What is an SSL/TLS certificate?**

A: An SSL certificate is a digital document that proves a website's identity and enables encrypted communication. It contains the domain name, organization details, validity period, public key, and a digital signature from a Certificate Authority.

---

**Q: What is a Certificate Authority (CA)?**

A: A CA is a trusted entity that issues and signs digital certificates. In this project, we built our own CA server using Node.js. When someone requests a certificate, our CA server generates it and signs it, similar to how a government office issues a passport.

---

**Q: What is a CSR?**

A: CSR stands for Certificate Signing Request. It is a document that contains information about the entity requesting a certificate (domain name, organization, email) and a public key. The CA uses the CSR to issue the certificate.

---

**Q: How does the frontend communicate with the backend?**

A: The React frontend uses the Axios library to send HTTP requests to the Laravel REST API. The API runs on port 8000. When a user logs in, the backend returns a token which is stored in localStorage. Every subsequent request includes this token in the Authorization header.

---

**Q: What is Docker and why do we use it?**

A: Docker is a platform that packages applications into containers – isolated environments that have everything the application needs. We use Docker Compose to run all 12 services (React, Laravel, MySQL, Redis, CA Server, Nginx, Prometheus, Grafana, Elasticsearch, Kibana, Logstash, Vault) with a single command: `docker-compose up`.

---

**Q: What is a REST API?**

A: REST API (Representational State Transfer API) is a way for the frontend to communicate with the backend using standard HTTP methods:
- GET → Retrieve data
- POST → Create new data
- PUT/PATCH → Update data
- DELETE → Remove data

---

**Q: What is the role of Nginx in this project?**

A: Nginx acts as a reverse proxy. Users access the application at port 80 or 443, and Nginx routes their requests to the correct service – API requests to Laravel (port 8000) and website requests to React (port 3000). It also handles SSL termination.

---

**Q: What is the MVC pattern?**

A: MVC stands for Model-View-Controller:
- **Model** – Represents data and database tables (e.g., `Certificate.php` maps to the certificates table)
- **View** – The user interface (in our case, React)
- **Controller** – Handles requests, applies business logic, and returns responses

---

**Q: How does user authentication work?**

A: When a user logs in, the frontend sends their credentials to POST /api/auth/login. Laravel verifies the password against the bcrypt hash stored in the database. If correct, it returns an authentication token. React stores this token in localStorage and sends it with every request in the Authorization header.

---

**Q: What are user roles in this project?**

A: There are three roles:
1. **Admin** – Full access (manage users, approve CSRs, view logs, manage all certificates)
2. **Certificate Manager** – Can approve/reject CSRs and manage certificates
3. **Regular User** – Can only submit CSR requests and view their own certificates

---

**Q: What is Prometheus and Grafana?**

A: Prometheus is a monitoring tool that collects metrics (numbers) from all services – like how many requests were made, response times, error rates. Grafana is a visualization tool that displays those metrics as charts and dashboards. Together they help monitor the health of the system.

---

**Q: What is the ELK stack?**

A: ELK stands for Elasticsearch, Logstash, and Kibana. It's a logging solution:
- **Logstash** collects and processes log files
- **Elasticsearch** stores logs and makes them searchable
- **Kibana** provides a web interface to search and visualize logs

---

**Q: What is Redis used for?**

A: Redis is an in-memory cache. It stores frequently accessed data temporarily in memory so that the database doesn't need to be queried every time, making the application faster.

---

**Q: What is HashiCorp Vault?**

A: Vault is a tool for securely storing and managing secrets like passwords, API keys, and certificates. Instead of storing sensitive values in .env files, they can be stored in Vault and retrieved by applications securely.

---

**Q: What is the ACME protocol?**

A: ACME (Automated Certificate Management Environment) is a standard protocol (RFC 8555) used by Let's Encrypt. It allows certificates to be issued and renewed automatically without manual intervention. Our backend implements ACME endpoints so it can integrate with standard ACME clients.

---

**Q: What is the difference between a Dockerfile and docker-compose.yml?**

A: 
- **Dockerfile** – Instructions to build a single container image (like a recipe)
- **docker-compose.yml** – Defines and runs multiple containers together (like a meal plan that uses multiple recipes)

---

**Q: What is an audit log?**

A: An audit log is a complete record of all actions taken in the system – who did what, when, and from which IP address. It's important for security and compliance. In our project, every login, certificate action, and approval is recorded in the audit_logs table.

---

**Q: How does certificate renewal work?**

A: The notification system checks for certificates that are close to their expiry date. It sends a notification to the user and admin. The user or admin then initiates a renewal which generates a new certificate for the same domain, replacing the old one.

---

**Q: What is TypeScript and why use it over JavaScript?**

A: TypeScript is a superset of JavaScript that adds static typing. This means you define what type of data each variable holds (string, number, boolean, etc.). TypeScript catches type errors during development, before the code runs, which prevents many bugs. It compiles down to regular JavaScript for the browser.

---

*This guide covers all key concepts in the VuProject Certificate Management System. Good luck with your viva!*
