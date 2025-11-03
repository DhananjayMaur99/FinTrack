# Project Technical Specification: FinTrack

## Project Overview
**Project:** FinTrack (Pure Expense Logger API)  
**Status:** Ready for Implementation  

---

## 1.0 Introduction

### 1.1 Project Purpose
**FinTrack** is a RESTful API backend for a personal expense logging application.  
The project's sole objective is to provide users with a **secure**, **simple**, and **detailed** mechanism to log, categorize, and analyze their spending habits.

The platform answers one key question:  
> “Where is my money going?”

It provides foundational data services for **expenditure tracking** and **budget management**.

---

### 1.2 Core Use Case

**User Journey:**

1. **Onboarding & Setup:**  
   - A new user registers for a secure account.  
   - The user creates personal spending categories (e.g., *Groceries, Coffee, Subscriptions, Rent*).

2. **Daily Operation (Logging):**  
   - The user logs a new transaction with amount, date, and category.

3. **Analysis & Control:**  
   - **Analysis:** Retrieve aggregated spending data grouped by category or time period.  
   - **Control:** Define budgets (e.g., monthly limits) and track spending performance against them.

---

## 2.0 System Architecture

### 2.1 Technology Stack
- **Framework:** Laravel (LTS)  
- **Database:** MySQL  
- **Authentication:** Laravel Sanctum (token-based API authentication)  
- **Audit Trail:** [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog)

---

### 2.2 Architectural Principles
The API follows a **modular, reusable, and decoupled N-tier architecture**, in alignment with Laravel best practices.

**Layers:**

#### Controller Layer (`app/Http/Controllers/Api/`)
- Handles HTTP requests and JSON responses.
- Delegates all business logic to the Service Layer.
- Stays “thin” and focused on request/response lifecycle.

#### Validation Layer (`app/Http/Requests/`)
- Handles all data validation using **Form Request classes**.
- Keeps validation reusable and separated from controllers.

#### Service Layer (`app/Services/`)
- Contains all **business logic** (budget calculations, analytics, etc.).
- Decoupled from HTTP — testable and reusable independently.

#### Data Access Layer (`app/Models/`)
- Handles only database-level concerns:
  - Relationships (`hasMany`, `belongsTo`)
  - Attribute casting
  - Query scopes

---

## 3.0 Database Schema & Design

The database follows **Third Normal Form (3NF)** for maximum integrity and minimal redundancy.  
Foreign key constraints ensure consistent linkage between entities.

---

### 3.1 Entity: `users`

Stores the master record for each user.

| Column | Type | Constraints | Description |
|--------|------|-------------|--------------|
| id | bigint | PK, Unsigned, Auto-Inc | Unique identifier for the user |
| name | string | Not Null | Full name |
| email | string | Not Null, Unique | Login email |
| password | string | Not Null | Hashed password |
| created_at | timestamp | Nullable | Record creation time |
| updated_at | timestamp | Nullable | Last record update |
| deleted_at | timestamp | Nullable | Soft-deletes flag |

---

### 3.2 Entity: `categories`

Stores user-defined labels for organizing expenditures.

| Column | Type | Constraints | Description |
|--------|------|-------------|--------------|
| id | bigint | PK, Unsigned, Auto-Inc | Unique category ID |
| user_id | bigint | FK → `users.id` | Owner of the category |
| name | string | Not Null | Display name (e.g., “Groceries”) |
| icon | string | Nullable | Optional icon reference |
| created_at | timestamp | Nullable | Record creation time |
| updated_at | timestamp | Nullable | Last record update |
| deleted_at | timestamp | Nullable | Soft-deletes flag |

---

### 3.3 Entity: `transactions`

The central log of every expense record.

| Column | Type | Constraints | Description |
|--------|------|-------------|--------------|
| id | bigint | PK, Unsigned, Auto-Inc | Unique transaction ID |
| user_id | bigint | FK → `users.id` | Owner of the transaction |
| category_id | bigint | FK → `categories.id`, Nullable | Category (optional) |
| amount | decimal(10,2) | Not Null | Expense value |
| description | string | Nullable | Optional note |
| date | date | Not Null | Transaction date |
| created_at | timestamp | Nullable | Record creation time |
| updated_at | timestamp | Nullable | Last record update |
| deleted_at | timestamp | Nullable | Soft-deletes flag |

---

### 3.4 Entity: `budgets`

Stores user-defined spending limits (goals).

| Column | Type | Constraints | Description |
|--------|------|-------------|--------------|
| id | bigint | PK, Unsigned, Auto-Inc | Unique budget ID |
| user_id | bigint | FK → `users.id` | Owner of the budget |
| category_id | bigint | FK → `categories.id`, Nullable | Target category (optional) |
| limit | decimal(10,2) | Not Null | Spending limit |
| period | enum('monthly', 'yearly') | Not Null | Frequency of the budget |
| start_date | date | Not Null | Budget start date |
| end_date | date | Nullable | Optional expiration date |
| created_at | timestamp | Nullable | Record creation time |
| updated_at | timestamp | Nullable | Last record update |

---

### 3.5 Entity-Relationship Model (Cardinality)

| Relationship | Type | Description |
|--------------|------|-------------|
| **User → Category** | 1:N | One user can own many categories |
| **User → Transaction** | 1:N | One user can own many transactions |
| **User → Budget** | 1:N | One user can own many budgets |
| **Category → Transaction** | 1:N | One category can apply to many transactions |
| **Category → Budget** | 1:N | One category can target many budgets |

---

## 4.0 Non-Functional Requirements

### Security
- All endpoints protected with **Laravel Sanctum** tokens.
- Queries are **user-scoped** — no cross-user access allowed.

### Data Integrity
- All relationships enforced via **foreign key constraints**.
- Monetary values use `DECIMAL(10,2)` to prevent float inaccuracies.

### Auditability
- All **Create, Update, Delete** actions on `Category`, `Transaction`, and `Budget` models are logged in `activity_log` using **spatie/laravel-activitylog**.

### Maintainability
- The codebase must strictly adhere to the **modular architecture** defined in Section 2.2.

---

**End of Document**
