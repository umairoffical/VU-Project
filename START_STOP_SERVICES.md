# 🚀 VuProject - Start & Stop Services Guide

This guide contains all commands needed to start, stop, and manage VuProject services.

---

## 📋 **Services Overview**

Your VuProject has **4 main services**:

1. **React Frontend** (Port 3000)
2. **Laravel API** (Port 8000)
3. **Real CA Server** (Port 8443)
4. **CRON Scheduler** (Background tasks)

---

## ▶️ **Starting All Services**

### **Option 1: Start All at Once (Separate Terminals)**

Open **4 terminal windows/tabs** and run one command in each:

#### Terminal 1: React Frontend
```bash
cd /Users/umair/Herd/vu-project/vu-react
npm start
```

#### Terminal 2: Laravel API
```bash
cd /Users/umair/Herd/vu-project/vu-laravel
php artisan serve
```

#### Terminal 3: Real CA Server
```bash
cd /Users/umair/Herd/vu-project/real-ca-server
node ca-server.js
```

#### Terminal 4: CRON Scheduler (Optional)
```bash
cd /Users/umair/Herd/vu-project/vu-laravel
php artisan schedule:work
```

---

### **Option 2: Start All in Background (Single Terminal)**

Run these commands one by one in a single terminal:

```bash
# Navigate to project root
cd /Users/umair/Herd/vu-project

# Start React Frontend (background)
cd vu-react && npm start > react.log 2>&1 & echo $! > react.pid && cd ..

# Start Laravel API (background)
cd vu-laravel && php artisan serve > laravel-server.log 2>&1 & echo $! > laravel.pid && cd ..

# Start Real CA Server (background)
cd real-ca-server && node ca-server.js > ca-server.log 2>&1 & echo $! > ca-server.pid && cd ..

# Start CRON Scheduler (background - optional)
cd vu-laravel && php artisan schedule:work > cron.log 2>&1 & echo $! > cron.pid && cd ..

echo "✅ All services started!"
```

---

### **Option 3: Quick Start Script (Recommended)**

Create and use a start script:

```bash
cd /Users/umair/Herd/vu-project
chmod +x START_SERVICES.sh
./START_SERVICES.sh
```

---

## ⏹️ **Stopping All Services**

### **Option 1: If Running in Separate Terminals**

Press `Ctrl+C` in each terminal window.

---

### **Option 2: Stop All Background Processes**

```bash
cd /Users/umair/Herd/vu-project

# Stop React
if [ -f vu-react/react.pid ]; then
  kill $(cat vu-react/react.pid) 2>/dev/null
  rm vu-react/react.pid
  echo "✅ React stopped"
fi

# Stop Laravel
if [ -f vu-laravel/laravel.pid ]; then
  kill $(cat vu-laravel/laravel.pid) 2>/dev/null
  rm vu-laravel/laravel.pid
  echo "✅ Laravel stopped"
fi

# Stop Real CA Server
if [ -f real-ca-server/ca-server.pid ]; then
  kill $(cat real-ca-server/ca-server.pid) 2>/dev/null
  rm real-ca-server/ca-server.pid
  echo "✅ CA Server stopped"
fi

# Stop CRON
if [ -f vu-laravel/cron.pid ]; then
  kill $(cat vu-laravel/cron.pid) 2>/dev/null
  rm vu-laravel/cron.pid
  echo "✅ CRON stopped"
fi

echo "🛑 All services stopped!"
```

---

### **Option 3: Force Stop All (Nuclear Option)**

```bash
# Stop by process name
pkill -f "npm start"
pkill -f "php artisan serve"
pkill -f "ca-server.js"
pkill -f "php artisan schedule:work"

echo "🛑 All services force stopped!"
```

---

## 🔄 **Restarting Services**

### **Restart Individual Service**

#### Restart React:
```bash
cd /Users/umair/Herd/vu-project/vu-react
pkill -f "npm start"
npm start > react.log 2>&1 &
echo "🔄 React restarted"
```

#### Restart Laravel:
```bash
cd /Users/umair/Herd/vu-project/vu-laravel
pkill -f "php artisan serve"
php artisan serve > laravel-server.log 2>&1 &
echo "🔄 Laravel restarted"
```

#### Restart CA Server:
```bash
cd /Users/umair/Herd/vu-project/real-ca-server
pkill -f "ca-server.js"
node ca-server.js > ca-server.log 2>&1 &
echo "🔄 CA Server restarted"
```

#### Restart CRON:
```bash
cd /Users/umair/Herd/vu-project/vu-laravel
pkill -f "php artisan schedule:work"
php artisan schedule:work > cron.log 2>&1 &
echo "🔄 CRON restarted"
```

---

## 🔍 **Checking Service Status**

### **Check What's Running**

```bash
# Check all services
echo "📊 Service Status:"
echo ""

# React (port 3000)
if lsof -i :3000 > /dev/null 2>&1; then
  echo "✅ React Frontend: RUNNING (port 3000)"
else
  echo "❌ React Frontend: STOPPED"
fi

# Laravel (port 8000)
if lsof -i :8000 > /dev/null 2>&1; then
  echo "✅ Laravel API: RUNNING (port 8000)"
else
  echo "❌ Laravel API: STOPPED"
fi

# CA Server (port 8443)
if lsof -i :8443 > /dev/null 2>&1; then
  echo "✅ Real CA Server: RUNNING (port 8443)"
else
  echo "❌ Real CA Server: STOPPED"
fi

# CRON
if pgrep -f "php artisan schedule:work" > /dev/null 2>&1; then
  echo "✅ CRON Scheduler: RUNNING"
else
  echo "❌ CRON Scheduler: STOPPED"
fi
```

---

### **Check Individual Service**

```bash
# Check React
lsof -i :3000

# Check Laravel
lsof -i :8000

# Check CA Server
lsof -i :8443

# Check CRON
pgrep -f "php artisan schedule:work"
```

---

## 📝 **View Service Logs**

### **Real-time Log Monitoring**

```bash
# Watch React logs
tail -f /Users/umair/Herd/vu-project/vu-react/react.log

# Watch Laravel logs
tail -f /Users/umair/Herd/vu-project/vu-laravel/storage/logs/laravel.log

# Watch CA Server logs
tail -f /Users/umair/Herd/vu-project/real-ca-server/ca-server.log

# Watch CRON logs
tail -f /Users/umair/Herd/vu-project/vu-laravel/cron.log
```

---

## 🧪 **Testing Services**

### **Test Each Service is Working**

```bash
# Test React Frontend
curl http://localhost:3000

# Test Laravel API
curl http://localhost:8000/api/health

# Test Real CA Server
curl -k https://localhost:8443/health

# Test Complete System
curl http://localhost:8000/api/ca-health
```

---

## 🚨 **Troubleshooting**

### **Port Already in Use**

If you get "port already in use" error:

```bash
# Find and kill process on port 3000 (React)
lsof -ti:3000 | xargs kill -9

# Find and kill process on port 8000 (Laravel)
lsof -ti:8000 | xargs kill -9

# Find and kill process on port 8443 (CA Server)
lsof -ti:8443 | xargs kill -9
```

---

### **Clear Cache & Restart**

```bash
cd /Users/umair/Herd/vu-project/vu-laravel

# Clear Laravel cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart services
php artisan serve > laravel-server.log 2>&1 &
```

---

### **React Build Issues**

```bash
cd /Users/umair/Herd/vu-project/vu-react

# Clear cache
rm -rf node_modules/.cache

# Reinstall dependencies (if needed)
npm install

# Start
npm start
```

---

## 📍 **Service URLs**

After starting all services, access them at:

| Service | URL | Purpose |
|---------|-----|---------|
| **React Dashboard** | http://localhost:3000 | Main UI |
| **Laravel API** | http://localhost:8000 | Backend API |
| **API Health** | http://localhost:8000/api/health | Check API status |
| **CA Server** | https://localhost:8443 | Certificate Authority |
| **CA Health** | http://localhost:8000/api/ca-health | Check CA status |

---

## 🔐 **Default Credentials**

- **Username:** `admin`
- **Password:** `admin123`

---

## ⚡ **Quick Commands Cheat Sheet**

### Start Everything:
```bash
cd /Users/umair/Herd/vu-project
cd vu-react && npm start &
cd ../vu-laravel && php artisan serve &
cd ../real-ca-server && node ca-server.js &
```

### Stop Everything:
```bash
pkill -f "npm start"
pkill -f "php artisan serve"
pkill -f "ca-server.js"
```

### Check Status:
```bash
lsof -i :3000 :8000 :8443
```

### View Logs:
```bash
tail -f vu-react/react.log
tail -f vu-laravel/storage/logs/laravel.log
tail -f real-ca-server/ca-server.log
```

---

## 📦 **First Time Setup**

If this is your first time, run these once:

```bash
cd /Users/umair/Herd/vu-project

# Setup Laravel
cd vu-laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
cd ..

# Setup React
cd vu-react
npm install
cd ..

# Setup CA Server
cd real-ca-server
npm install
cd ..
```

---

## 💡 **Tips**

1. **Use separate terminals** for easier log monitoring
2. **Check logs** if something isn't working: `tail -f <service>.log`
3. **Test each service** individually before starting all
4. **Kill zombie processes** if ports are occupied
5. **Clear cache** if you see stale data

---

## 🎯 **Typical Startup Sequence**

1. Start Laravel API first (backend)
2. Start CA Server (certificate authority)
3. Start React Frontend (UI)
4. Start CRON (optional, for scheduled tasks)

---

## ✅ **Verify Everything is Running**

Run this one command to check all services:

```bash
echo "🔍 Checking Services..."
curl -s http://localhost:8000/api/health && echo " - Laravel: ✅"
curl -s -k https://localhost:8443/health && echo " - CA Server: ✅"
curl -s http://localhost:3000 > /dev/null && echo " - React: ✅"
```

---

**🎉 You're all set! Open http://localhost:3000 in your browser to use VuProject!**

