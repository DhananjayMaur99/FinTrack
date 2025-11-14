# FinTrack API Error Codes Reference

This document lists all error codes used in the FinTrack API.

## Error Response Format

All API errors return a consistent JSON structure:

```json
{
  "message": "Human-readable error description",
  "error_code": "MACHINE_READABLE_CODE",
  "status": 404
}
```

Some responses may include additional fields (e.g., `retry_after` for rate limiting).

---

## Standard HTTP Error Codes

### 4xx Client Errors

#### 400 Bad Request
**HTTP Status**: 400  
**When**: Malformed request, invalid JSON

```json
{
  "message": "The given data was invalid.",
  "status": 400
}
```

---

#### 401 Unauthorized
**HTTP Status**: 401  
**Error Code**: `UNAUTHENTICATED`  
**When**: No valid authentication token provided

```json
{
  "message": "Unauthenticated.",
  "error_code": "UNAUTHENTICATED",
  "status": 401
}
```

**Common Causes**:
- Missing `Authorization` header
- Invalid or expired token
- Token not prefixed with `Bearer`

---

#### 403 Forbidden
**HTTP Status**: 403  
**Error Code**: `UNAUTHORIZED` or `UNAUTHORIZED_ACCESS`  
**When**: User lacks permission to access the resource

```json
{
  "message": "You do not have permission to perform this action",
  "error_code": "UNAUTHORIZED",
  "status": 403
}
```

**Custom Unauthorized Access**:
```json
{
  "message": "You do not have permission to access Budget 123",
  "error_code": "UNAUTHORIZED_ACCESS",
  "status": 403
}
```

**Common Causes**:
- Trying to access another user's resources
- Attempting operations on owned resources that aren't yours

---

#### 404 Not Found
**HTTP Status**: 404  
**Error Code**: `RESOURCE_NOT_FOUND`  
**When**: The requested resource doesn't exist

```json
{
  "message": "Resource not found",
  "error_code": "RESOURCE_NOT_FOUND",
  "status": 404
}
```

**Custom Resource Not Found**:
```json
{
  "message": "Transaction not found",
  "error_code": "RESOURCE_NOT_FOUND",
  "status": 404
}
```

**Common Causes**:
- Invalid resource ID
- Resource was deleted (soft-deleted)
- Resource belongs to different user

---

#### 422 Unprocessable Entity
**HTTP Status**: 422  
**Error Code**: `VALIDATION_ERROR` or `BUSINESS_RULE_VIOLATION`

**Validation Errors**:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": [
      "The name field is required."
    ],
    "amount": [
      "The amount must be greater than 0."
    ]
  }
}
```

**Business Rule Violations**:
```json
{
  "message": "Transaction amount exceeds available budget",
  "error_code": "BUSINESS_RULE_VIOLATION",
  "status": 422
}
```

**Common Causes**:
- Missing required fields
- Invalid data types
- Values outside allowed ranges
- Business logic constraints violated

---

#### 429 Too Many Requests
**HTTP Status**: 429  
**Error Code**: `RATE_LIMIT_EXCEEDED`  
**When**: User has exceeded API rate limits

```json
{
  "message": "Too many requests. Please slow down.",
  "error_code": "RATE_LIMIT_EXCEEDED",
  "retry_after": 59,
  "status": 429
}
```

**Rate Limits**:
- **Authentication endpoints** (`/login`, `/register`): 5 requests/minute
- **General API endpoints**: 60 requests/minute

**Retry Strategy**: Wait for `retry_after` seconds before retrying

---

### 5xx Server Errors

#### 500 Internal Server Error
**HTTP Status**: 500  
**When**: Unexpected server error

```json
{
  "message": "Server Error",
  "status": 500
}
```

**Note**: These should be rare. Contact support if you encounter them frequently.

---

## Custom Error Codes

### FinTrack-Specific Errors

| Error Code | HTTP Status | Description | Example Cause |
|-----------|-------------|-------------|---------------|
| `RESOURCE_NOT_FOUND` | 404 | Requested resource doesn't exist | Invalid transaction ID |
| `UNAUTHORIZED_ACCESS` | 403 | User doesn't own the resource | Accessing another user's budget |
| `BUSINESS_RULE_VIOLATION` | 422 | Business logic constraint failed | Budget limit exceeded |
| `UNAUTHENTICATED` | 401 | No valid auth token | Missing/expired token |
| `UNAUTHORIZED` | 403 | Insufficient permissions | Generic authorization failure |
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests | Exceeded rate limit |

---

## Validation Error Fields

Common validation errors by endpoint:

### Categories
- `name`: Required, string, max:255
- `type`: Required, either 'income' or 'expense'
- `icon`: Optional, string, max:50

### Transactions
- `amount`: Required, numeric, greater than 0
- `category_id`: Required, integer, must exist
- `date`: Required, date format (YYYY-MM-DD)
- `description`: Optional, string, max:500

### Budgets
- `category_id`: Required, integer, must exist
- `amount`: Required, numeric, greater than 0
- `start_date`: Required, date format (YYYY-MM-DD)
- `end_date`: Required, date format (YYYY-MM-DD), after start_date
- `alert_threshold`: Optional, integer, 0-100

---

## Error Handling Best Practices

### Client-Side Handling

```javascript
try {
  const response = await fetch('/api/transactions', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  
  if (!response.ok) {
    const error = await response.json();
    
    switch (error.error_code) {
      case 'RESOURCE_NOT_FOUND':
        // Show "not found" message
        break;
      case 'UNAUTHORIZED_ACCESS':
        // Redirect to home or show permission denied
        break;
      case 'BUSINESS_RULE_VIOLATION':
        // Show specific business rule error
        break;
      case 'RATE_LIMIT_EXCEEDED':
        // Wait for retry_after seconds
        setTimeout(() => retry(), error.retry_after * 1000);
        break;
      default:
        // Generic error handling
        showError(error.message);
    }
  }
  
  return await response.json();
} catch (error) {
  // Network error or JSON parse error
  console.error('Request failed:', error);
}
```

### Retry Strategy

For `429 Rate Limit Exceeded`:
1. Extract `retry_after` from response
2. Wait for specified seconds
3. Retry the request
4. Implement exponential backoff for repeated failures

### Logging

All errors are logged server-side with:
- Request ID (for tracing)
- User ID (if authenticated)
- Request method and URL
- Error context and stack trace

Contact support with the timestamp and any error messages for assistance.

---

## Need Help?

If you encounter errors not listed here or need clarification:
1. Check the API logs (if you have access)
2. Look for Request ID in response headers
3. Contact support with full error details

**Note**: This list is maintained as new error codes are added to the system.
