#!/bin/bash

# VuProject - Start All Services Script
# ======================================

echo "🚀 Starting VuProject Services..."
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Project root
PROJECT_ROOT="/Users/umair/Herd/vu-project"
cd "$PROJECT_ROOT"

# Function to check if port is in use
check_port() {
    lsof -i :$1 > /dev/null 2>&1
    return $?
}

# Function to wait for service
wait_for_service() {
    local port=$1
    local name=$2
    local max_attempts=30
    local attempt=0
    
    while [ $attempt -lt $max_attempts ]; do
        if check_port $port; then
            echo -e "${GREEN}✅ $name started successfully (port $port)${NC}"
            return 0
        fi
        sleep 1
        attempt=$((attempt + 1))
    done
    
    echo -e "${RED}❌ $name failed to start${NC}"
    return 1
}

# Check if ports are already in use
echo "🔍 Checking if ports are available..."

if check_port 3000; then
    echo -e "${YELLOW}⚠️  Port 3000 already in use (React). Stopping existing process...${NC}"
    lsof -ti:3000 | xargs kill -9 2>/dev/null
    sleep 2
fi

if check_port 8000; then
    echo -e "${YELLOW}⚠️  Port 8000 already in use (Laravel). Stopping existing process...${NC}"
    lsof -ti:8000 | xargs kill -9 2>/dev/null
    sleep 2
fi

if check_port 8443; then
    echo -e "${YELLOW}⚠️  Port 8443 already in use (CA Server). Stopping existing process...${NC}"
    lsof -ti:8443 | xargs kill -9 2>/dev/null
    sleep 2
fi

echo ""
echo "▶️  Starting services..."
echo ""

# 1. Start Laravel API
echo "1️⃣  Starting Laravel API..."
cd "$PROJECT_ROOT/vu-laravel"
php artisan serve > laravel-server.log 2>&1 &
LARAVEL_PID=$!
echo $LARAVEL_PID > laravel.pid
sleep 2
wait_for_service 8000 "Laravel API"
echo ""

# 2. Start Real CA Server
echo "2️⃣  Starting Real CA Server..."
cd "$PROJECT_ROOT/real-ca-server"
node ca-server.js > ca-server.log 2>&1 &
CA_PID=$!
echo $CA_PID > ca-server.pid
sleep 2
wait_for_service 8443 "Real CA Server"
echo ""

# 3. Start React Frontend
echo "3️⃣  Starting React Frontend..."
cd "$PROJECT_ROOT/vu-react"
npm start > react.log 2>&1 &
REACT_PID=$!
echo $REACT_PID > react.pid
sleep 3
wait_for_service 3000 "React Frontend"
echo ""

# 4. Start CRON Scheduler (optional)
echo "4️⃣  Starting CRON Scheduler (optional)..."
cd "$PROJECT_ROOT/vu-laravel"
php artisan schedule:work > cron.log 2>&1 &
CRON_PID=$!
echo $CRON_PID > cron.pid
echo -e "${GREEN}✅ CRON Scheduler started${NC}"
echo ""

# Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "🎉 All VuProject services started successfully!"
echo ""
echo "📍 Service URLs:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   React Dashboard:  http://localhost:3000"
echo "   Laravel API:      http://localhost:8000"
echo "   CA Server:        https://localhost:8443"
echo "   API Health:       http://localhost:8000/api/health"
echo "   CA Health:        http://localhost:8000/api/ca-health"
echo ""
echo "🔐 Default Credentials:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   Username: admin"
echo "   Password: admin123"
echo ""
echo "📝 Process IDs saved in:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   Laravel:  $PROJECT_ROOT/vu-laravel/laravel.pid"
echo "   CA Server: $PROJECT_ROOT/real-ca-server/ca-server.pid"
echo "   React:     $PROJECT_ROOT/vu-react/react.pid"
echo "   CRON:      $PROJECT_ROOT/vu-laravel/cron.pid"
echo ""
echo "📊 View logs with:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   tail -f $PROJECT_ROOT/vu-laravel/storage/logs/laravel.log"
echo "   tail -f $PROJECT_ROOT/real-ca-server/ca-server.log"
echo "   tail -f $PROJECT_ROOT/vu-react/react.log"
echo ""
echo "🛑 Stop all services with:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   ./STOP_SERVICES.sh"
echo ""
echo "✨ Opening browser in 3 seconds..."
sleep 3

# Open browser (macOS)
open http://localhost:3000 2>/dev/null || echo "Please open http://localhost:3000 in your browser"

