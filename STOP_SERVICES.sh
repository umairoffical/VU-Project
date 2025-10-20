#!/bin/bash

# VuProject - Stop All Services Script
# =====================================

echo "🛑 Stopping VuProject Services..."
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Project root
PROJECT_ROOT="/Users/umair/Herd/vu-project"
cd "$PROJECT_ROOT"

# Function to stop service by PID file
stop_by_pid() {
    local pid_file=$1
    local service_name=$2
    
    if [ -f "$pid_file" ]; then
        local pid=$(cat "$pid_file")
        if ps -p $pid > /dev/null 2>&1; then
            kill $pid 2>/dev/null
            sleep 1
            
            # Force kill if still running
            if ps -p $pid > /dev/null 2>&1; then
                kill -9 $pid 2>/dev/null
            fi
            
            rm "$pid_file"
            echo -e "${GREEN}✅ $service_name stopped${NC}"
        else
            echo -e "${YELLOW}⚠️  $service_name was not running${NC}"
            rm "$pid_file"
        fi
    else
        echo -e "${YELLOW}⚠️  No PID file found for $service_name${NC}"
    fi
}

# Function to stop by port
stop_by_port() {
    local port=$1
    local service_name=$2
    
    if lsof -i :$port > /dev/null 2>&1; then
        lsof -ti:$port | xargs kill -9 2>/dev/null
        echo -e "${GREEN}✅ $service_name stopped (port $port)${NC}"
    else
        echo -e "${YELLOW}⚠️  $service_name was not running on port $port${NC}"
    fi
}

# Stop services by PID files
echo "Stopping services by PID files..."
echo ""

stop_by_pid "$PROJECT_ROOT/vu-react/react.pid" "React Frontend"
stop_by_pid "$PROJECT_ROOT/vu-laravel/laravel.pid" "Laravel API"
stop_by_pid "$PROJECT_ROOT/real-ca-server/ca-server.pid" "Real CA Server"
stop_by_pid "$PROJECT_ROOT/vu-laravel/cron.pid" "CRON Scheduler"

echo ""
echo "Ensuring all processes are stopped..."
echo ""

# Stop by port (backup method)
stop_by_port 3000 "React Frontend"
stop_by_port 8000 "Laravel API"
stop_by_port 8443 "Real CA Server"

echo ""
echo "Stopping any remaining processes by name..."
echo ""

# Stop by process name (final cleanup)
if pgrep -f "npm start" > /dev/null; then
    pkill -f "npm start"
    echo -e "${GREEN}✅ Stopped npm processes${NC}"
fi

if pgrep -f "php artisan serve" > /dev/null; then
    pkill -f "php artisan serve"
    echo -e "${GREEN}✅ Stopped Laravel processes${NC}"
fi

if pgrep -f "ca-server.js" > /dev/null; then
    pkill -f "ca-server.js"
    echo -e "${GREEN}✅ Stopped CA Server processes${NC}"
fi

if pgrep -f "php artisan schedule:work" > /dev/null; then
    pkill -f "php artisan schedule:work"
    echo -e "${GREEN}✅ Stopped CRON processes${NC}"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "🎉 All VuProject services stopped successfully!"
echo ""
echo "📊 Verify services are stopped:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   lsof -i :3000    # React (should be empty)"
echo "   lsof -i :8000    # Laravel (should be empty)"
echo "   lsof -i :8443    # CA Server (should be empty)"
echo ""
echo "🚀 Start services again with:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   ./START_SERVICES.sh"
echo ""

