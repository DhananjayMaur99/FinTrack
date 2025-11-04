#!/bin/bash

# FinTrack Test Suite Runner
# Runs all test agents in sequence with detailed reporting

echo "╔═══════════════════════════════════════════════════════╗"
echo "║      FinTrack Comprehensive Test Suite Runner        ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Track test results
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to run a test agent
run_test_agent() {
    local agent_name=$1
    local agent_class=$2
    
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}Running: $agent_name${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    
    # Run the test
    php artisan test "tests/Agents/$agent_class.php"
    
    local exit_code=$?
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ $exit_code -eq 0 ]; then
        echo ""
        echo -e "${GREEN}✓ $agent_name PASSED${NC}"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo ""
        echo -e "${RED}✗ $agent_name FAILED${NC}"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
    
    echo ""
}

# Main execution
echo -e "${YELLOW}Preparing test environment...${NC}"
echo ""

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo -e "${RED}Error: vendor directory not found. Please run 'composer install' first.${NC}"
    exit 1
fi

# Check if .env.testing exists
if [ ! -f ".env.testing" ]; then
    echo -e "${YELLOW}Warning: .env.testing not found. Creating from .env.example...${NC}"
    cp .env.example .env.testing
fi

# Check database connection
echo -e "${YELLOW}Checking database connection...${NC}"
php artisan db:show --database=mysql > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo -e "${RED}Error: Cannot connect to database. Please check your .env.testing file.${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Database connection successful${NC}"
echo ""

# Start timer
START_TIME=$(date +%s)

# Run all test agents
run_test_agent "API Test Agent" "ApiTestAgent"
run_test_agent "Business Logic Test Agent" "BusinessLogicTestAgent"
run_test_agent "Security Test Agent" "SecurityTestAgent"
run_test_agent "Performance Test Agent" "PerformanceTestAgent"

# Calculate execution time
END_TIME=$(date +%s)
EXECUTION_TIME=$((END_TIME - START_TIME))

# Print final report
echo ""
echo "╔═══════════════════════════════════════════════════════╗"
echo "║                    FINAL REPORT                       ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""
echo "Test Agents Run:     $TOTAL_TESTS"
echo -e "${GREEN}Passed:             $PASSED_TESTS${NC}"

if [ $FAILED_TESTS -gt 0 ]; then
    echo -e "${RED}Failed:             $FAILED_TESTS${NC}"
else
    echo "Failed:             $FAILED_TESTS"
fi

echo "Execution Time:      ${EXECUTION_TIME}s"
echo ""

# Exit with appropriate code
if [ $FAILED_TESTS -gt 0 ]; then
    echo -e "${RED}╔═══════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║  TESTS FAILED - Please review the errors above       ║${NC}"
    echo -e "${RED}╚═══════════════════════════════════════════════════════╝${NC}"
    exit 1
else
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ALL TESTS PASSED - System is ready for production!  ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════╝${NC}"
    exit 0
fi
