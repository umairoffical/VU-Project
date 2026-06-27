# 🔐 VuProject - Certificate Management System

A comprehensive, enterprise-grade SSL/TLS Certificate Management System built with React and Laravel.

---

## 📋 Table of Contents

- [Features](#-features)
- [Project Structure](#-project-structure)
- [Getting Started](#-getting-started)
- [Services](#-services)
- [Service Management](#-service-management)
- [Access Information](#-access-information)
- [Troubleshooting](#-troubleshooting)

---

## ✨ Features

### Core Features
- ✅ **Certificate Generation** - Generate SSL/TLS certificates via Real CA Server
- ✅ **CSR Management** - Complete Certificate Signing Request workflow with approval
- ✅ **Certificate Renewal** - Automated renewal with notifications
- ✅ **Certificate Revocation** - One-click revocation with audit trail
- ✅ **Role-Based Access Control** - Admin, Certificate Manager, and User roles
- ✅ **Beautiful Dashboard** - Material-UI design with charts and statistics
- ✅ **Real-Time Monitoring** - Live certificate status updates
- ✅ **Email Notifications** - Automated alerts for expiry, revocation, and issuance
- ✅ **Audit Logging** - Complete activity tracking
- ✅ **Database Backups** - Automated daily backups

### 📝 CSR Workflow Explained

**When you generate a Certificate Signing Request (CSR), here's what happens:**

1. **CSR Submission** → Your CSR is saved to the **`certificate_requests`** database table
2. **Status: Pending** → The CSR gets status `'pending'` and waits for admin approval
3. **Admin Review** → Admins can view all CSRs in the **CSR Management** section (Menu → CSR Management)
4. **Admin Actions:**
   - **Approve** → Automatically generates a certificate and saves it to the **`certificates`** table
   - **Reject** → CSR status changes to `'rejected'` with a rejection reason
5. **Result:**
   - **If Approved** → Certificate is created and appears in the main certificates dashboard
   - **If Rejected** → CSR remains in the rejected list with the reason

**Where to find your CSR:**
- **Database Table:** `certificate_requests` (MySQL/SQLite)
- **Frontend:** Dashboard → Menu (⋮) → "CSR Management"
- **API Endpoint:** `GET /api/csr/list`
- **Status:** Can be `pending`, `approved`, `rejected`, or `issued`

### Security Features
- 🔒 TLS 1.3 / HTTPS support
- 🔒 OAuth 2.0 / OpenID Connect ready
- 🔒 Secure private key storage
- 🔒 CSRF Protection
- 🔒 Rate Limiting
- 🔒 Complete audit trail

---

## 📁 Project Structure

```
vu-project/
├── vu-laravel/              # Laravel Backend API
│   ├── app/
│   │   ├── Http/Controllers/    # API Controllers
│   │   ├── Models/              # Database Models
│   │   └── Services/            # Business Logic Services
│   ├── database/
│   │   ├── migrations/          # Database Migrations
│   │   └── seeders/             # Database Seeders
│   ├── routes/
│   │   └── api.php              # API Routes
│   └── .env                     # Environment Configuration
│
├── vu-react/                # React Frontend
│   ├── src/
│   │   ├── components/          # React Components
│   │   │   ├── Dashboard.tsx     # Main Dashboard
│   │   │   ├── Login.tsx         # Login Page
│   │   │   ├── CSRGenerator.tsx  # CSR Generator
│   │   │   ├── CSRManagement.tsx # CSR Management
│   │   │   └── CertificateCharts.tsx # Charts
│   │   └── services/
│   │       └── api.ts           # API Service
│   └── package.json
│
├── real-ca-server/          # Node.js CA Server
│   └── ca-server.js         # Certificate Authority Server
│
└── docker-compose.yml       # Docker Configuration (Optional)
```

---

## 🚀 Getting Started

### Prerequisites

- **PHP** 8.2+ with Composer
- **Node.js** 18+ with npm
- **MySQL** 8.0+ (or SQLite for development)
- **Git**

### Installation

1. **Install Laravel Dependencies**
   ```bash
   cd vu-laravel
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. **Configure Database**
   Edit `vu-laravel/.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=vu_laravel
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. **Run Migrations**
   ```bash
   cd vu-laravel
   php artisan migrate --seed
   ```

4. **Install React Dependencies**
   ```bash
   cd vu-react
   npm install
   ```

5. **Install CA Server Dependencies** (Optional - uses built-in Node.js modules)
   ```bash
   cd real-ca-server
   npm install
   ```

---

## 🎯 Services

The project consists of **3 main services**:

| Service | Technology | Port | Purpose |
|---------|------------|------|---------|
| **React Frontend** | React 19 + TypeScript | 3000 | User Interface Dashboard |
| **Laravel API** | PHP 8.3 + Laravel 12 | 8000 | Backend REST API |
| **Real CA Server** | Node.js | 8443 | Certificate Authority |

---

## 🎮 Service Management

### Starting Services

#### Option 1: Start All Services (Recommended)

Open **3 separate terminal windows**:

**Terminal 1 - Laravel API:**
```bash
cd /Users/umair/Herd/vu-project/vu-laravel
php artisan serve
```

**Terminal 2 - CA Server:**
```bash
cd /Users/umair/Herd/vu-project/real-ca-server
node ca-server.js
```

**Terminal 3 - React Frontend:**
```bash
cd /Users/umair/Herd/vu-project/vu-react
npm start
```

#### Option 2: Start in Background

**Laravel API:**
```bash
cd /Users/umair/Herd/vu-project/vu-laravel
php artisan serve > laravel-server.log 2>&1 &
```

**CA Server:**
```bash
cd /Users/umair/Herd/vu-project/real-ca-server
node ca-server.js > ca-server.log 2>&1 &
```

**React Frontend:**
```bash
cd /Users/umair/Herd/vu-project/vu-react
npm start > react.log 2>&1 &
```

### Stopping Services

#### Stop All Services
```bash
# Stop by process name
pkill -f "npm start"
pkill -f "php artisan serve"
pkill -f "ca-server.js"
```

#### Stop Individual Services

**Stop Laravel:**
```bash
pkill -f "php artisan serve"
# Or find and kill by port
lsof -ti:8000 | xargs kill -9
```

**Stop CA Server:**
```bash
pkill -f "ca-server.js"
# Or find and kill by port
lsof -ti:8443 | xargs kill -9
```

**Stop React:**
```bash
pkill -f "npm start"
# Or find and kill by port
lsof -ti:3000 | xargs kill -9
```

### Checking Service Status

**Check if services are running:**
```bash
# Check Laravel (port 8000)
lsof -i :8000

# Check CA Server (port 8443)
lsof -i :8443

# Check React (port 3000)
lsof -i :3000
```

**Test service endpoints:**
```bash
# Test Laravel API
curl http://localhost:8000/api/health

# Test CA Server
curl -k https://localhost:8443/health

# Test React Frontend
curl http://localhost:3000
```

### Viewing Logs

**Laravel Logs:**
```bash
tail -f /Users/umair/Herd/vu-project/vu-laravel/storage/logs/laravel.log
```

**CA Server Logs:**
```bash
tail -f /Users/umair/Herd/vu-project/real-ca-server/ca-server.log
```

**React Logs:**
```bash
tail -f /Users/umair/Herd/vu-project/vu-react/react.log
```

---

## 🌐 Access Information

### Application URLs

| Service | URL | Description |
|---------|-----|-------------|
| **Frontend Dashboard** | http://localhost:3000 | Main User Interface |
| **Laravel API** | http://localhost:8000 | Backend API |
| **API Health Check** | http://localhost:8000/api/health | API Status |
| **CA Server** | https://localhost:8443 | Certificate Authority |
| **CA Health Check** | http://localhost:8000/api/ca-health | CA Integration Status |

### Default Login Credentials

- **Username:** `admin`
- **Password:** `admin123`

---

## 🔧 Service Details

### React Frontend Service

**Location:** `vu-react/`

**Technology Stack:**
- React 19.1.1
- TypeScript 4.9.5
- Material-UI v7
- Axios for API calls
- Recharts for data visualization

**Start Command:**
```bash
cd vu-react
npm start
```

**Build Command:**
```bash
cd vu-react
npm run build
```

**Port:** 3000

**Features:**
- Certificate dashboard with real-time updates
- CSR generation and management
- Certificate charts and statistics
- Role-based UI components
- Responsive design

---

### Laravel API Service

**Location:** `vu-laravel/`

**Technology Stack:**
- PHP 8.3
- Laravel 12
- MySQL/SQLite database
- Guzzle HTTP client

**Start Command:**
```bash
cd vu-laravel
php artisan serve
```

**Additional Commands:**
```bash
# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Run CRON scheduler (for automated tasks)
php artisan schedule:work
```

**Port:** 8000

**API Endpoints:**
- `GET /api/health` - Health check
- `GET /api/certificates` - List certificates
- `POST /api/certificates/generate` - Generate certificate
- `POST /api/certificates/renew` - Renew certificate
- `POST /api/certificates/revoke` - Revoke certificate
- `GET /api/csr/list` - List CSRs
- `POST /api/csr/generate` - Generate CSR
- `POST /api/csr/approve/{id}` - Approve CSR
- `POST /api/csr/reject/{id}` - Reject CSR

---

### Real CA Server Service

**Location:** `real-ca-server/`

**Technology Stack:**
- Node.js
- Built-in modules (https, fs, crypto)
- No external dependencies required

**Start Command:**
```bash
cd real-ca-server
node ca-server.js
```

**Port:** 8443

**Features:**
- Real-time certificate generation
- Certificate storage and management
- Health check endpoint
- HTTPS server with self-signed certificate

**Endpoints:**
- `GET /health` - Health check
- `POST /certificates/generate` - Generate certificate
- `GET /certificates` - List certificates
- `POST /certificates/revoke` - Revoke certificate

---

## 🛠️ Troubleshooting

### Port Already in Use

If you get "port already in use" error:

```bash
# Kill process on port 3000 (React)
lsof -ti:3000 | xargs kill -9

# Kill process on port 8000 (Laravel)
lsof -ti:8000 | xargs kill -9

# Kill process on port 8443 (CA Server)
lsof -ti:8443 | xargs kill -9
```

### Services Won't Start

**Laravel Issues:**
```bash
cd vu-laravel
# Clear cache
php artisan config:clear
php artisan cache:clear
# Check .env file exists
ls -la .env
# Regenerate key if needed
php artisan key:generate
```

**React Issues:**
```bash
cd vu-react
# Clear cache and reinstall
rm -rf node_modules package-lock.json
npm install
# Try starting again
npm start
```

**CA Server Issues:**
```bash
cd real-ca-server
# Check if Node.js is installed
node --version
# Try running directly
node ca-server.js
```

### Database Connection Issues

**Check database configuration:**
```bash
cd vu-laravel
# Check .env file
cat .env | grep DB_
```

**Test database connection:**
```bash
cd vu-laravel
php artisan db:show
```

**If using SQLite:**
```bash
cd vu-laravel
touch database/database.sqlite
php artisan migrate
```

### Certificate Generation Fails

1. **Check CA Server is running:**
   ```bash
   curl -k https://localhost:8443/health
   ```

2. **Check CA integration:**
   ```bash
   curl http://localhost:8000/api/ca-health
   ```

3. **Restart CA Server:**
   ```bash
   pkill -f "ca-server.js"
   cd real-ca-server
   node ca-server.js &
   ```

---

## 📊 Project Statistics

- **Lines of Code:** ~15,000+
- **React Components:** 7 components
- **Laravel Services:** 8 services
- **API Endpoints:** 45+
- **Database Tables:** 5 tables
- **Features:** 15+ major features

---

## 📝 Notes

- The CA Server uses only built-in Node.js modules (no npm dependencies required)
- Default database is MySQL, but SQLite can be used for development
- All services can run independently
- The React frontend automatically connects to the Laravel API
- The Laravel API proxies requests to the CA Server to avoid CORS issues

---

## 🎉 Quick Start Summary

```bash
# 1. Start Laravel API
cd vu-laravel && php artisan serve

# 2. Start CA Server (in new terminal)
cd real-ca-server && node ca-server.js

# 3. Start React Frontend (in new terminal)
cd vu-react && npm start

# 4. Open browser
# http://localhost:3000
# Login: admin / admin123
```

---

**🎊 Your VuProject Certificate Management System is ready!**

For more information, check the service logs or review the code structure.
