# FinTrack Test Agents Documentation

## Overview

This document describes the automated test agents created to comprehensively test the FinTrack application from scratch. Each agent simulates a different type of tester with specific focus areas.

## Test Agents

### 1. 🤖 API Test Agent (`tests/Agents/ApiTestAgent.php`)

**Purpose**: Tests all API endpoints systematically, simulating a real API consumer.

**What it tests:**
- Complete user workflow from registration to logout
- All CRUD operations for Categories, Transactions, and Budgets
- User authentication and token management
- Data integrity across related entities
- Authorization and security boundaries
- Pagination functionality
- API response structure and status codes

**Test Flow:**
1. User Registration → Verifies account creation and token issuance
2. User Login → Verifies authentication and new token generation
3. Category CRUD → Creates 5 categories, lists, updates, retrieves
4. Transaction CRUD → Creates 5 transactions, lists with pagination, updates, soft deletes
5. Budget CRUD → Creates 2 budgets, retrieves with progress stats, updates
6. Data Integrity → Verifies relationships and soft delete behavior
7. Authorization → Tests cross-user access prevention
8. Pagination → Tests multi-page transaction listing
9. Logout → Verifies token revocation

**Key Validations:**
- ✓ JSON response structure matches expectations
- ✓ HTTP status codes are correct (201 for creates, 200 for updates, 403 for unauthorized)
- ✓ Auth tokens work correctly and are revoked on logout
- ✓ User-scoped data isolation is maintained
- ✓ Soft deletes exclude records from budget calculations

---

### 2. 🧠 Business Logic Test Agent (`tests/Agents/BusinessLogicTestAgent.php`)

**Purpose**: Tests business rules, calculations, and edge cases.

**What it tests:**
- Budget calculation accuracy
- Date range filtering for budgets
- Transaction amount validation (positive only)
- Category deletion with existing transactions
- Budget overspending detection
- Date range validation (end_date >= start_date)
- Decimal precision (2 places for amounts)
- Soft delete behavior and restoration
- User data isolation at database level
- Nullable category relationships

**Test Scenarios:**

#### Budget Calculations
- Creates budget with $1000 limit
- Adds transactions totaling $400.50
- Verifies: spent, remaining, progress_percent, is_over_budget
- Expected: 40.05% progress, $599.50 remaining, not over budget

#### Budget Period Validation
- Budget: Nov 1-30, 2025
- Transactions: Oct 30 (before), Nov 15 (within), Dec 1 (after)
- Verifies: Only Nov 15 transaction counted ($100)

#### Overspending Detection
- Budget limit: $500
- Transactions: $300 + $250 = $550
- Verifies: is_over_budget=true, remaining=-$50, progress=110%

#### Soft Delete Testing
- Deletes transaction via API
- Verifies soft delete timestamp set
- Tests withTrashed() retrieval
- Tests restore functionality

**Key Validations:**
- ✓ All calculations are mathematically correct
- ✓ Date filtering respects budget periods
- ✓ Zero and negative amounts rejected
- ✓ Decimal amounts rounded to 2 places
- ✓ Soft deletes work correctly
- ✓ Data isolation prevents cross-user data access

---

### 3. 🛡️ Security Test Agent (`tests/Agents/SecurityTestAgent.php`)

**Purpose**: Tests authentication, authorization, and security vulnerabilities.

**What it tests:**
- Unauthenticated access attempts
- Token validation and handling
- Cross-user data access prevention
- SQL injection protection
- Mass assignment vulnerabilities
- XSS (Cross-Site Scripting) protection
- Token revocation after logout
- Resource ownership enforcement
- Password security and hashing

**Attack Simulations:**

#### Unauthorized Access
- Tests all endpoints without authentication → Expect 401
- Tests with invalid tokens → Expect 401
- Tests with malformed Authorization headers → Expect 401

#### Cross-User Attacks
- User2 tries to view User1's category → Expect 403
- User2 tries to update User1's transaction → Expect 403
- User2 tries to delete User1's budget → Expect 403

#### SQL Injection Attempts
- Category name: `'; DROP TABLE categories; --`
- Verifies: Stored as plain text, database intact

#### Mass Assignment Protection
- Tries to set user_id directly in requests
- Tries to manipulate created_at/updated_at timestamps
- Verifies: Protected fields ignored, auth user always used

#### XSS Attacks
- Payloads: `<script>alert("XSS")</script>`, `<img src=x onerror=alert("XSS")>`
- Verifies: Stored as plain text, no script execution

#### Password Security
- Verifies passwords are hashed (bcrypt)
- Tests password confirmation requirement
- Tests weak password rejection

**Key Validations:**
- ✓ All protected endpoints require authentication
- ✓ Invalid/tampered tokens rejected
- ✓ Users cannot access other users' data
- ✓ SQL injection attempts safely stored as data
- ✓ Mass assignment vulnerabilities blocked
- ✓ XSS payloads stored as harmless text
- ✓ Passwords hashed, never stored in plain text

---

### 4. ⚡ Performance Test Agent (`tests/Agents/PerformanceTestAgent.php`)

**Purpose**: Tests performance, scalability, and query efficiency.

**What it tests:**
- Bulk data creation and retrieval
- Pagination performance with large datasets
- Complex query performance (budget calculations)
- Concurrent user simulation
- Large transaction set handling (1000+ records)
- Budget calculation performance under load
- Database indexing effectiveness

**Load Scenarios:**

#### Bulk Data Handling
- Creates 100 categories
- Lists all categories
- Measures: Creation time, retrieval time
- Expects: < 2 seconds for listing

#### Pagination Performance
- Creates 500 transactions
- Tests page 1 and page 10 retrieval
- Measures: Load time for each page
- Expects: < 1 second per page

#### Complex Query Performance
- Creates 10 categories × 50 transactions = 500 total
- Calculates budget progress with date filtering
- Measures: Query execution time
- Expects: < 1 second for calculation

#### Concurrent Users
- Creates 10 users with full data (categories, transactions, budgets)
- Simulates 30 concurrent API requests
- Verifies: Data isolation maintained, no conflicts

#### Large Transaction Sets
- Inserts 1000 transactions in batches
- Tests paginated retrieval
- Measures: Insertion time, retrieval time
- Expects: < 1 second for paginated retrieval

#### Budget Calculation Performance
- 5 categories × 200 transactions = 1000 total
- Calculates progress for 5 budgets
- Measures: Average time per calculation
- Expects: < 1 second per budget

#### Database Indexing
- Creates 1000 transactions
- Tests queries by user_id, category_id, date range
- Measures: Query execution time
- Expects: < 0.5 seconds for indexed queries

**Key Validations:**
- ✓ System handles hundreds of records efficiently
- ✓ Pagination works smoothly under load
- ✓ Complex calculations complete quickly
- ✓ Multiple concurrent users supported
- ✓ Database queries are optimized
- ✓ Bulk operations use batching

---

## Running the Tests

### Individual Agent

Run a specific test agent:

```bash
# API Test Agent
php artisan test --filter=ApiTestAgent

# Business Logic Test Agent
php artisan test --filter=BusinessLogicTestAgent

# Security Test Agent
php artisan test --filter=SecurityTestAgent

# Performance Test Agent
php artisan test --filter=PerformanceTestAgent
```

### All Agents (Recommended)

Run all test agents in sequence:

```bash
# Make the script executable (first time only)
chmod +x run-test-agents.sh

# Run all agents
./run-test-agents.sh
```

The script will:
1. Check prerequisites (vendor, database)
2. Run all 4 test agents in sequence
3. Display detailed progress for each agent
4. Generate a final report with pass/fail summary
5. Report total execution time

### Expected Output

```
╔═══════════════════════════════════════════════════════╗
║      FinTrack Comprehensive Test Suite Runner        ║
╚═══════════════════════════════════════════════════════╝

Preparing test environment...
✓ Database connection successful

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Running: API Test Agent
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🤖 API Test Agent Starting Complete Workflow Test
================================================

📝 Step 1: Testing User Registration...
   ✓ User registered successfully
   ✓ Auth token received
...

╔═══════════════════════════════════════════════════════╗
║                    FINAL REPORT                       ║
╚═══════════════════════════════════════════════════════╝

Test Agents Run:     4
Passed:             4
Failed:             0
Execution Time:      45s

╔═══════════════════════════════════════════════════════╗
║  ALL TESTS PASSED - System is ready for production!  ║
╚═══════════════════════════════════════════════════════╝
```

---

## Test Configuration

### Database Setup

Ensure `.env.testing` is configured:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=FinTrackTesting
DB_USERNAME=root
DB_PASSWORD=1234

APP_KEY=base64:your-app-key-here
```

### PHPUnit Configuration

The `phpunit.xml` file should have:

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="FinTrackTesting"/>
```

---

## Test Coverage

### Endpoints Tested

| Endpoint | Method | Agent |
|----------|--------|-------|
| `/api/register` | POST | API, Security |
| `/api/login` | POST | API, Security |
| `/api/logout` | POST | API |
| `/api/categories` | GET | API, Performance |
| `/api/categories` | POST | API, Business, Security |
| `/api/categories/{id}` | GET | API, Security |
| `/api/categories/{id}` | PUT | API, Security |
| `/api/categories/{id}` | DELETE | API, Business |
| `/api/transactions` | GET | API, Performance |
| `/api/transactions` | POST | API, Business, Security |
| `/api/transactions/{id}` | GET | API |
| `/api/transactions/{id}` | PUT | API |
| `/api/transactions/{id}` | DELETE | API |
| `/api/budgets` | GET | API, Performance |
| `/api/budgets` | POST | API, Business |
| `/api/budgets/{id}` | GET | API, Performance |
| `/api/budgets/{id}` | PUT | API |
| `/api/budgets/{id}` | DELETE | API |

### Features Tested

- ✅ User Authentication (Sanctum)
- ✅ Category CRUD with soft deletes
- ✅ Transaction CRUD with soft deletes
- ✅ Budget CRUD with progress calculation
- ✅ User-scoped data isolation
- ✅ Authorization policies
- ✅ Pagination
- ✅ Form validation
- ✅ Date range filtering
- ✅ Decimal precision
- ✅ SQL injection protection
- ✅ XSS protection
- ✅ Mass assignment protection
- ✅ Password hashing
- ✅ Token management
- ✅ Performance under load
- ✅ Database query optimization

---

## Maintenance

### Adding New Tests

When adding new features, extend the appropriate agent:

1. **New API endpoint** → Update `ApiTestAgent.php`
2. **New business rule** → Update `BusinessLogicTestAgent.php`
3. **Security concern** → Update `SecurityTestAgent.php`
4. **Performance critical** → Update `PerformanceTestAgent.php`

### Running Before Deployment

Always run the full test suite before deploying:

```bash
./run-test-agents.sh
```

All tests must pass before deploying to production.

---

## Troubleshooting

### Database Connection Error

```
Error: Cannot connect to database
```

**Solution:** Check `.env.testing` database credentials and ensure MySQL is running.

### Migration Errors

```
Error: Table already exists
```

**Solution:** Tests use `migrate:fresh` automatically. Check database permissions.

### Memory Limit

```
Fatal error: Allowed memory size exhausted
```

**Solution:** Increase PHP memory limit in `php.ini`:
```
memory_limit = 512M
```

### Timeout Errors

```
Maximum execution time exceeded
```

**Solution:** Increase timeout in `phpunit.xml`:
```xml
<phpunit processTimeout="300">
```

---

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Test Agents

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install Dependencies
        run: composer install

      - name: Setup Database
        run: |
          mysql -e 'CREATE DATABASE FinTrackTesting;'
          php artisan migrate --env=testing

      - name: Run Test Agents
        run: ./run-test-agents.sh
```

---

## Summary

The test agents provide **comprehensive coverage** of the FinTrack application:

- **API Test Agent**: Validates all endpoints work correctly
- **Business Logic Test Agent**: Ensures calculations and rules are accurate
- **Security Test Agent**: Protects against common vulnerabilities
- **Performance Test Agent**: Verifies system performs under load

Together, these agents test **every critical path** from registration to complex budget calculations, ensuring the application is **production-ready** and **secure**.
