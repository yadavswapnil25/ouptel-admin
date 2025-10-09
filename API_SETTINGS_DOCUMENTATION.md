# General Settings API Documentation

This API mimics the old WoWonder API structure for general settings and user data updates.

## Base URL
```
/api/v1
```

## Authentication
All endpoints require Bearer token authentication via the `Authorization` header:
```
Authorization: Bearer {session_id}
```

---

## 1. Get Settings

Retrieves all general application settings (mimics old `get_settings.php`).

### Endpoint
```http
GET /api/v1/settings
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Query Parameters (Optional)
| Parameter | Type | Description |
|-----------|------|-------------|
| `windows_app_version` | string | Client app version (e.g., "1.0") |
| `app_version` | string | Alternative parameter for app version |

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "config": {
        "siteName": "Ouptel",
        "siteDesc": "Social Network",
        "siteUrl": "https://ouptel.com",
        "siteTitle": "Ouptel",
        "theme": "wowonder",
        "logo_extension": "png",
        "logo_url": "http://localhost/images/logo.png",
        "windows_app_version": "1.0",
        "update_available": false,
        "page_categories": [...],
        "group_categories": [...],
        "blog_categories": [...],
        "product_categories": [...],
        "job_categories": [...],
        "user_messages": "RXJyb3Igd2hpbGUgY29ubmVjdGluZyB0byBvdXIgc2VydmVycy4=",
        "comments_default_num": "10",
        "video_upload": "1",
        "audio_upload": "1",
        "file_sharing": "1",
        ...
    }
}
```

### Error Responses

#### 401 Unauthorized - No Token
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "5",
        "error_text": "No session sent."
    }
}
```

#### 401 Unauthorized - Invalid Token
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "6",
        "error_text": "Session id is wrong."
    }
}
```

#### 404 Not Found - No Settings
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "6",
        "error_text": "No settings available."
    }
}
```

### Excluded Sensitive Fields
The following configuration keys are automatically filtered out for security:
- API keys (reCaptcha, Google Maps, etc.)
- Payment credentials (PayPal, Stripe)
- Email/SMS credentials
- Social login credentials
- Database credentials
- Admin-only settings

---

## 2. Update User Data

Updates user settings based on type (mimics old `update_user_data.php`).

### Endpoint
```http
POST /api/v1/settings/update-user-data
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body
```json
{
    "type": "general_settings",
    "user_data": "{\"username\":\"john_doe\",\"email\":\"john@example.com\",\"gender\":\"male\"}"
}
```

**Note**: The `user_data` parameter must be a **JSON-encoded string** (not a JSON object).

### Update Types

#### 2.1 General Settings (`general_settings`)

Updates basic user information.

**User Data Fields:**
```json
{
    "username": "john_doe",
    "email": "john@example.com",
    "phone_number": "+1234567890",
    "gender": "male"
}
```

**Validation:**
- Email must be unique and valid format
- Phone number must be unique
- Username must be 5-32 characters, alphanumeric only
- Gender must be "male" or "female"

---

#### 2.2 Password Settings (`password_settings`)

Updates user password and logs out all other sessions.

**User Data Fields:**
```json
{
    "current_password": "old_password",
    "new_password": "new_password",
    "repeat_new_password": "new_password"
}
```

**Validation:**
- Current password must match
- New passwords must match
- Password must be at least 6 characters

---

#### 2.3 Privacy Settings (`privacy_settings`)

Updates privacy preferences.

**User Data Fields:**
```json
{
    "message_privacy": "0",
    "follow_privacy": "1",
    "birth_privacy": "2",
    "status": "1"
}
```

**Valid Values:**
- `message_privacy`: "0" (Everyone), "1" (Friends Only)
- `follow_privacy`: "0" (Everyone), "1" (Nobody)
- `birth_privacy`: "0" (Everyone), "1" (Friends), "2" (Only Me)
- `status`: "0" (Offline), "1" (Online)

---

#### 2.4 Online Status (`online_status`)

Toggles online/offline status.

**User Data Fields:**
```json
{
    "status": "1"
}
```

**Valid Values:**
- "0" = Offline
- "1" = Online

---

#### 2.5 Profile Settings (`profile_settings`)

Updates profile information and social links.

**User Data Fields:**
```json
{
    "first_name": "John",
    "last_name": "Doe",
    "about": "Software developer",
    "facebook": "https://facebook.com/johndoe",
    "google": "https://plus.google.com/johndoe",
    "linkedin": "https://linkedin.com/in/johndoe",
    "vk": "https://vk.com/johndoe",
    "instagram": "https://instagram.com/johndoe",
    "twitter": "https://twitter.com/johndoe",
    "youtube": "https://youtube.com/johndoe"
}
```

---

#### 2.6 Custom Settings (`custom_settings`)

Updates additional profile fields.

**User Data Fields:**
```json
{
    "address": "123 Main St",
    "school": "University Name",
    "country_id": "840",
    "city": "New York",
    "zip": "10001",
    "website": "https://example.com",
    "working": "Company Name",
    "working_link": "https://company.com",
    "language": "english"
}
```

**Allowed Fields:**
- `first_name`, `last_name`, `about`
- `website`, `working`, `working_link`
- `address`, `school`, `country_id`, `city`, `zip`
- `language`

---

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0"
}
```

### Error Responses

#### 422 Validation Error
```json
{
    "api_status": "500",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "Email already exists",
        "Username must be between 5 and 32 characters"
    ]
}
```

#### 400 Bad Request
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "8",
        "error_text": "Invalid user data format."
    }
}
```

---

## Example Usage

### cURL Examples

#### Get Settings
```bash
curl -X GET "https://ouptel.com/api/v1/settings" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

#### Update General Settings
```bash
curl -X POST "https://ouptel.com/api/v1/settings/update-user-data" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "general_settings",
    "user_data": "{\"username\":\"john_doe\",\"email\":\"john@example.com\",\"gender\":\"male\"}"
  }'
```

#### Update Password
```bash
curl -X POST "https://ouptel.com/api/v1/settings/update-user-data" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "password_settings",
    "user_data": "{\"current_password\":\"oldpass\",\"new_password\":\"newpass\",\"repeat_new_password\":\"newpass\"}"
  }'
```

---

## Notes

1. **JSON Encoding**: The `user_data` parameter must be a JSON-encoded **string**, not a JSON object. This matches the old API structure.

2. **Session Management**: When updating password, all other active sessions are automatically logged out for security.

3. **Field Validation**: Most fields have validation rules matching the old WoWonder API for backward compatibility.

4. **Sensitive Data**: Sensitive configuration values (API keys, passwords, etc.) are automatically filtered from the settings response.

5. **Categories**: The API automatically includes page, group, blog, product, and job categories if they exist in the database.

---

## Migration from Old API

### Old API → New API Mapping

| Old Endpoint | New Endpoint |
|--------------|--------------|
| `GET /phone/get_settings.php?type=get_settings` | `GET /api/v1/settings` |
| `POST /phone/update_user_data.php?type=update_user_data` | `POST /api/v1/settings/update-user-data` |

### Query Parameter Changes
- `s` (session) → `Authorization: Bearer {token}` header
- `user_id` → Extracted from session token automatically
- `type` parameter moved to request body for POST requests

### Response Format
The response format remains identical to the old API for backward compatibility:
- `api_status`: "200" (success) or "400"/"500" (error)
- `api_text`: "success" or "failed"
- `api_version`: Version string
- `config` or `errors`: Data or error details

