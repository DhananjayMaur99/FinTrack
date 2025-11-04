# FinTrack Project Overview

## Project Description

FinTrack is a personal finance tracking REST API built with Laravel 11. It provides foundational data services for expenditure tracking and budget management.

## Technology Stack

-   **Framework**: Laravel 11.x
-   **PHP Version**: 8.x
-   **Database**: MySQL (with SQLite support)
-   **Authentication**: Laravel Sanctum (Token-based)
-   **Testing**: PHPUnit with Feature & Unit tests
-   **API Architecture**: RESTful JSON API

## Core Features

1. **User Authentication**: Register, Login, Logout with Sanctum tokens
2. **Transaction Management**: Track income/expenses with categories
3. **Category Management**: Organize transactions by custom categories
4. **Budget Management**: Set spending limits with progress tracking
5. **Data Analysis**: Aggregate spending by category/time period

## Project Structure

```
FinTrack/
├── app/
│   ├── Http/
│   │   ├── Controllers/     # API Controllers
│   │   ├── Requests/        # Form Request Validation
│   │   └── Resources/       # API Resource Transformers
│   ├── Models/              # Eloquent Models
│   ├── Policies/            # Authorization Policies
│   └── Services/            # Business Logic Layer
├── database/
│   ├── factories/           # Model Factories for testing
│   ├── migrations/          # Database schema
│   └── seeders/             # Database seeders
├── routes/
│   ├── api.php              # API routes (Sanctum protected)
│   └── web.php              # Web routes
├── tests/
│   ├── Feature/             # Feature tests (API endpoints)
│   └── Unit/                # Unit tests
└── docs/                    # Technical specifications
```

## Key Architectural Principles

1. **User-Scoped Data**: All resources belong to authenticated users
2. **Policy-Based Authorization**: Laravel Policies enforce ownership
3. **Service Layer Pattern**: Business logic isolated in Services
4. **Form Request Validation**: Centralized validation logic
5. **API Resources**: Consistent JSON response formatting
6. **Soft Deletes**: Non-destructive data removal
7. **Factory Pattern**: Realistic test data generation

## Database Schema

-   **users**: Authentication & user profiles
-   **categories**: User-defined transaction categories
-   **transactions**: Income/expense records
-   **budgets**: Spending limits with progress tracking
-   **personal_access_tokens**: Sanctum authentication tokens

## Security Features

-   Token-based authentication (Sanctum)
-   User-scoped queries (no cross-user access)
-   Policy-based authorization
-   Foreign key constraints
-   Validated user inputs
-   CORS configuration

## API Conventions

-   **Base URL**: `/api/*`
-   **Authentication**: Bearer token in Authorization header
-   **Response Format**: JSON with consistent structure
-   **Error Handling**: Global exception handler for API routes
-   **HTTP Status Codes**:
    -   200 OK (success)
    -   201 Created (resource created)
    -   204 No Content (deleted)
    -   401 Unauthorized
    -   403 Forbidden
    -   404 Not Found
    -   422 Validation Error
    -   500 Server Error

## Development Guidelines

-   Follow Laravel conventions
-   Write feature tests for all endpoints
-   Use factories for test data
-   Keep controllers thin (delegate to services)
-   Validate all inputs via Form Requests
-   Use API Resources for responses
-   Implement soft deletes where appropriate
-   Document API changes in technical spec
