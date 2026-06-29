# VuProject — Complete Viva Guide
### SSL/TLS Certificate Management System

---

## What Is This Project? (Simple Answer for Viva)

This is a web application that manages SSL/TLS certificates — the same type of certificates that make websites show the green padlock in your browser.

Think of it like a **certificate office**:
- Users come and **request** certificates
- Admins **approve or reject** those requests
- A **CA Server** (Certificate Authority) actually creates the certificate
- Everything is stored in a **database**
- You can see all certificates on a **dashboard**

---

## The 3 Parts of This Project

```
┌─────────────────┐       ┌──────────────────┐       ┌─────────────────────┐
│   React Frontend │──────▶│   Laravel (PHP)   │──────▶│  CA Server (Docker) │
│   localhost:3000 │       │   localhost:8000  │       │   localhost:8443    │
│                 │◀──────│                  │◀──────│                     │
└─────────────────┘       └──────────────────┘       └─────────────────────┘
     USER SEES THIS           BRAIN / API               MAKES CERTIFICATES
```

| Part | Technology | Port | What It Does |
|------|-----------|------|-------------|
| Frontend | React + TypeScript | 3000 | The website the user sees |
| Backend | Laravel (PHP) | 8000 | Handles all logic, talks to database |
| CA Server | Node.js in Docker | 8443 | Creates and manages certificates |

**Important:** React never talks to the CA Server directly. Laravel acts as the middleman.

---

## Part 1 — Frontend (React)

**Location:** `vu-react/`

React is a JavaScript framework that builds the user interface.

**Pages/Components:**
| File | What It Shows |
|------|--------------|
| `Login.tsx` | Login page |
| `Dashboard.tsx` | Main page — shows all certificates in a table and charts |
| `CSRGenerator.tsx` | Form to request a new certificate (CSR) |
| `CSRManagement.tsx` | Admin page to approve/reject certificate requests |
| `CertificateCharts.tsx` | Pie chart and bar chart showing certificate stats |
| `StatusPage.tsx` | Shows if all services are online |
| `Register.tsx` | Register new user page |

**How React talks to Laravel:**
```javascript
// Example — React fetches certificates from Laravel
fetch('http://localhost:8000/api/live-certificates')
  .then(res => res.json())
  .then(data => console.log(data.data)) // shows the list
```

**Start command:**
```bash
cd vu-react
npm start
```

---

## Part 2 — Backend (Laravel)

**Location:** `vu-laravel/`

Laravel is a PHP framework. It:
1. Receives requests from React
2. Talks to the MySQL database
3. Calls the CA server to generate certificates
4. Sends back responses

**Database Tables (5 tables in MySQL):**
| Table | Stores |
|-------|--------|
| `users` | Admin and regular user accounts |
| `certificates` | All issued certificates |
| `certificate_requests` | CSR requests waiting for approval |
| `audit_logs` | Every action ever taken (who did what, when) |
| `notifications` | System alerts (expiring soon, revoked, etc.) |

**The most important file:** `vu-laravel/routes/api.php`
This file defines every URL (API endpoint) the system has.

**Start command:**
```bash
cd vu-laravel
php artisan serve
```

---

## Part 3 — CA Server in Docker (The Requirement)

**Location:** `real-ca-server/`

This is your **private CA server running inside a Docker container** — which is exactly what the assignment requires.

### Why Docker?
Docker runs the CA server inside a **Linux container** on your Mac. The container is isolated, portable, and mimics a real production server environment.

### What the CA Server Does
It acts like a Certificate Authority — like Let's Encrypt but private:
- Starts with 4 pre-loaded certificates
- Can generate new certificates on demand
- Can revoke or renew existing certificates
- Stores everything in memory while running

### How Docker Is Set Up

**File: `real-ca-server/Dockerfile`**
```dockerfile
FROM node:18-alpine        # Use Linux with Node.js
RUN apk add --no-cache openssl  # Install openssl (needed to make HTTPS)
WORKDIR /app               # Work inside /app folder in the container
COPY package*.json ./      # Copy package files
RUN npm install            # Install dependencies
COPY ca-server.js ./       # Copy our CA server code
EXPOSE 8443                # Open port 8443
CMD ["node", "ca-server.js"]  # Run it
```

**File: `docker-compose.yml`** — tells Docker which containers to run:
```yaml
step-ca:
  build:
    context: ./real-ca-server   # Build from our CA server folder
  container_name: vuproject-step-ca
  ports:
    - "8443:8443"   # Mac port 8443 → Container port 8443
```

---

## How to Start Everything (The Right Order)

### Step 1 — Start CA Server (Docker)
```bash
cd /Users/umair/Herd/vu-project
docker compose up -d step-ca
```
This starts the CA server inside a Docker container.

### Step 2 — Start Laravel Backend
```bash
cd /Users/umair/Herd/vu-project/vu-laravel
php artisan serve
```

### Step 3 — Start React Frontend
```bash
cd /Users/umair/Herd/vu-project/vu-react
npm start
```

### Step 4 — Open Browser
```
http://localhost:3000
Login: admin / admin123
```

---

## How to Stop Everything

```bash
# Stop Docker CA server
docker compose down

# Stop Laravel (press Ctrl+C in its terminal, or:)
pkill -f "php artisan serve"

# Stop React (press Ctrl+C in its terminal, or:)
pkill -f "npm start"
```

---

## Docker Commands You Must Know

```bash
# Build the CA server Docker image
docker compose build step-ca

# Start CA server container (in background)
docker compose up -d step-ca

# See running containers
docker ps

# See logs from CA server
docker logs vuproject-step-ca

# Stop and remove containers
docker compose down

# Restart the CA server
docker compose restart step-ca
```

**What `docker ps` shows when running:**
```
CONTAINER ID   IMAGE                PORTS                    NAMES
521793b327ed   vu-project-step-ca   0.0.0.0:8443->8443/tcp   vuproject-step-ca
```
This confirms the CA server is running in Docker on port 8443.

---

## All Important API URLs

You can test all of these in your browser or with curl.

### Health Checks
| URL | What It Returns |
|-----|----------------|
| `http://localhost:8000/api/health` | Is Laravel running? |
| `http://localhost:8000/api/ca-health` | Is CA Server (Docker) online? |

**Example response from `/api/ca-health`:**
```json
{
  "status": "online",
  "message": "✅ ACTIVE - Generating real certificates",
  "ca_name": "VuProject CA",
  "port": "8443"
}
```

### Certificates
| URL | Method | What It Does |
|-----|--------|-------------|
| `http://localhost:8000/api/live-certificates` | GET | Get all certificates (from CA if online, DB if offline) |
| `http://localhost:8000/api/certificates/generate` | POST | Generate a new certificate |
| `http://localhost:8000/api/certificates/revoke` | POST | Revoke a certificate |
| `http://localhost:8000/api/certificates/renew` | POST | Renew a certificate |

**Example — get all certificates:**
```bash
curl http://localhost:8000/api/live-certificates
```
Response tells you:
- `ca_server_active: true` = showing real CA data (Docker is running)
- `ca_server_active: false` = showing database data (Docker is off)

**Example — generate a certificate:**
```bash
curl -X POST http://localhost:8000/api/certificates/generate \
  -H "Content-Type: application/json" \
  -d '{"commonName": "mywebsite.com", "validityDays": 365}'
```
Response:
```json
{
  "success": true,
  "message": "Certificate generated via Real CA Server and saved to database",
  "certificate_id": "cert_123_abc",
  "source": "real-ca-server",
  "real_ca_used": true
}
```

### CSR (Certificate Signing Requests)
| URL | Method | What It Does |
|-----|--------|-------------|
| `http://localhost:8000/api/csr/list` | GET | List all CSR requests |
| `http://localhost:8000/api/csr/generate` | POST | Submit a new CSR request |
| `http://localhost:8000/api/csr/approve/{id}` | POST | Admin approves a CSR |
| `http://localhost:8000/api/csr/reject/{id}` | POST | Admin rejects a CSR |

### Authentication
| URL | Method | What It Does |
|-----|--------|-------------|
| `http://localhost:8000/api/auth/login` | POST | Login — returns a token |
| `http://localhost:8000/api/auth/register` | POST | Register new user |

**Example — login:**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}'
```

### CA Server Direct (inside Docker)
These go directly to the CA container (bypass Laravel):
| URL | What It Returns |
|-----|----------------|
| `https://localhost:8443/health` | CA Server health |
| `https://localhost:8443/certificates` | All CA certificates |

```bash
# Test CA server directly (use -k to skip SSL warning)
curl -k https://localhost:8443/health
```

---

## The Smart Dashboard Logic

The dashboard has a chip in the top-right corner:

```
If Docker CA is RUNNING:
  Chip shows → "🔴 LIVE – Real CA Server"
  Certificates come from → Docker container (real-time)

If Docker CA is STOPPED:
  Chip shows → "💾 Database"
  Certificates come from → MySQL database (saved copies)
```

This means the system **never goes down** — even if Docker stops, users still see their certificates from the database.

---

## The CSR Workflow (Step by Step)

```
1. User fills form → Clicks "Generate CSR"
        ↓
2. Laravel saves it to certificate_requests table (status = pending)
        ↓
3. Admin sees it in CSR Management page
        ↓
4. Admin clicks "Approve"
        ↓
5. Laravel calls CA Server → CA generates certificate
   Laravel also saves certificate to certificates table
        ↓
6. Certificate appears in the main dashboard
```

If admin clicks "Reject" instead, the CSR gets status = rejected with a reason.

---

## Viva Q&A

**Q: What is a CA Server?**
A: A Certificate Authority (CA) is a trusted entity that signs and issues SSL/TLS certificates. Our CA server is a private CA we built ourselves, running in Docker.

**Q: Why did you use Docker for the CA server?**
A: The assignment requires a private CA server in a preferably Linux environment using Docker containers. Docker runs our CA server inside a Linux container on the local machine, satisfying this requirement.

**Q: What is the difference between a CSR and a Certificate?**
A: A CSR (Certificate Signing Request) is just a request — it says "I want a certificate for mywebsite.com." The certificate is what gets issued after an admin approves the request. The CA server signs it.

**Q: What happens if the Docker container stops?**
A: The dashboard automatically switches to database mode and shows saved certificates. No data is lost because every certificate is also saved to MySQL.

**Q: What technology is the frontend built in?**
A: React 19 with TypeScript and Material-UI for the design components.

**Q: What technology is the backend built in?**
A: Laravel 12 (PHP 8.3). It provides a REST API that the React frontend calls.

**Q: What database are you using?**
A: MySQL 8.0 with 5 tables: users, certificates, certificate_requests, audit_logs, notifications.

**Q: Why does React not call the CA server directly?**
A: Because of CORS (Cross-Origin Resource Sharing) restrictions. Browsers block direct requests from one origin to another unless allowed. Laravel acts as a proxy — React calls Laravel, Laravel calls the CA server.

**Q: What port does each service run on?**
A: React → 3000, Laravel → 8000, CA Server (Docker) → 8443.

**Q: How do you generate a certificate through the API?**
A: `POST http://localhost:8000/api/certificates/generate` with JSON body `{"commonName": "example.com", "validityDays": 365}`

---

## Project File Structure (Simple View)

```
vu-project/
│
├── docker-compose.yml          ← Defines the Docker CA container
│
├── real-ca-server/             ← The CA Server (runs in Docker)
│   ├── Dockerfile              ← How to build the Docker image
│   ├── ca-server.js            ← The actual CA server code (Node.js)
│   └── package.json
│
├── vu-laravel/                 ← Backend API (PHP Laravel)
│   ├── routes/api.php          ← All API URLs defined here
│   ├── app/Models/             ← Database models
│   │   ├── Certificate.php
│   │   ├── CertificateRequest.php
│   │   └── User.php
│   └── database/migrations/    ← Creates database tables
│
└── vu-react/                   ← Frontend (React)
    └── src/components/
        ├── Dashboard.tsx        ← Main certificate table
        ├── Login.tsx            ← Login page
        ├── CSRGenerator.tsx     ← Request a certificate
        └── CSRManagement.tsx    ← Admin approve/reject
```

---

## Quick Verification Commands (Show These in Viva)

```bash
# 1. Show Docker container is running
docker ps

# 2. Test CA server is alive inside Docker
curl -k https://localhost:8443/health

# 3. Test Laravel is connected to CA server
curl http://localhost:8000/api/ca-health

# 4. Get all live certificates
curl http://localhost:8000/api/live-certificates

# 5. Generate a certificate via API
curl -X POST http://localhost:8000/api/certificates/generate \
  -H "Content-Type: application/json" \
  -d '{"commonName": "viva-demo.com", "validityDays": 365}'
```

Run these one by one in the viva to demonstrate the system is fully working.
