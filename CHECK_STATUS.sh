#!/bin/bash

# VuProject - Check Service Status Script
# ========================================

echo "🔍 VuProject Service Status Check"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to check port
check_port() {
    lsof -i :$1 > /dev/null 2>&1
    return $?
}

# Function to check URL
check_url() {
    curl -s -o /dev/null -w "%{http_code}" "$1" 2>/dev/null
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 Service Status"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check React Frontend
echo -n "React Frontend (Port 3000):     "
if check_port 3000; then
    echo -e "${GREEN}✅ RUNNING${NC}"
    echo "   URL: http://localhost:3000"
else
    echo -e "${RED}❌ STOPPED${NC}"
fi
echo ""

# Check Laravel API
echo -n "Laravel API (Port 8000):        "
if check_port 8000; then
    echo -e "${GREEN}✅ RUNNING${NC}"
    echo "   URL: http://localhost:8000"
    
    # Check API health
    HEALTH_CODE=$(check_url "http://localhost:8000/api/health")
    if [ "$HEALTH_CODE" = "200" ]; then
        echo -e "   Health: ${GREEN}✅ OK${NC}"
    else
        echo -e "   Health: ${RED}❌ Error (HTTP $HEALTH_CODE)${NC}"
    fi
else
    echo -e "${RED}❌ STOPPED${NC}"
fi
echo ""

# Check Real CA Server
echo -n "Real CA Server (Port 8443):     "
if check_port 8443; then
    echo -e "${GREEN}✅ RUNNING${NC}"
    echo "   URL: https://localhost:8443"
    
    # Check CA health
    CA_HEALTH=$(curl -s -k https://localhost:8443/health 2>/dev/null | grep -o '"status":"ok"')
    if [ -n "$CA_HEALTH" ]; then
        echo -e "   Health: ${GREEN}✅ OK${NC}"
    else
        echo -e "   Health: ${RED}❌ Error${NC}"
    fi
else
    echo -e "${RED}❌ STOPPED${NC}"
fi
echo ""

# Check CRON Scheduler
echo -n "CRON Scheduler:                 "
if pgrep -f "php artisan schedule:work" > /dev/null; then
    echo -e "${GREEN}✅ RUNNING${NC}"
else
    echo -e "${RED}❌ STOPPED${NC}"
fi
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 System Status Summary"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check CA Server via Laravel proxy
echo "Testing CA Server Integration..."
CA_STATUS=$(curl -s http://localhost:8000/api/ca-health 2>/dev/null | grep -o '"status":"[^"]*"' | cut -d'"' -f4)

if [ "$CA_STATUS" = "online" ]; then
    echo -e "${GREEN}✅ CA Server Integration: ONLINE${NC}"
    echo "   Mode: Real CA (Live certificate generation)"
else
    echo -e "${YELLOW}⚠️  CA Server Integration: OFFLINE${NC}"
    echo "   Mode: Database Fallback"
fi
echo ""

# Count running services
RUNNING_COUNT=0
check_port 3000 && RUNNING_COUNT=$((RUNNING_COUNT + 1))
check_port 8000 && RUNNING_COUNT=$((RUNNING_COUNT + 1))
check_port 8443 && RUNNING_COUNT=$((RUNNING_COUNT + 1))
pgrep -f "php artisan schedule:work" > /dev/null && RUNNING_COUNT=$((RUNNING_COUNT + 1))

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

if [ $RUNNING_COUNT -eq 4 ]; then
    echo -e "${GREEN}🎉 All services are running! ($RUNNING_COUNT/4)${NC}"
    echo ""
    echo "🌐 Open Dashboard: http://localhost:3000"
    echo "🔐 Login: admin / admin123"
elif [ $RUNNING_COUNT -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Some services are running ($RUNNING_COUNT/4)${NC}"
    echo ""
    echo "Start all services with: ./START_SERVICES.sh"
else
    echo -e "${RED}❌ No services are running (0/4)${NC}"
    echo ""
    echo "Start services with: ./START_SERVICES.sh"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📝 Quick Commands:"
echo "   ./START_SERVICES.sh  - Start all services"
echo "   ./STOP_SERVICES.sh   - Stop all services"
echo "   ./CHECK_STATUS.sh    - Check status (this script)"
echo ""

