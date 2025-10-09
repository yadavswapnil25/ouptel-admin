# Privacy Settings API Documentation

This API mimics the old WoWonder API structure for managing user privacy settings.

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

## 1. Get Privacy Settings

Retrieves the current user's privacy settings.

### Endpoint
```http
GET /api/v1/privacy/settings
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "privacy_settings": {
        "message_privacy": "0",
        "message_privacy_text": "Everyone",
        "follow_privacy": "0",
        "follow_privacy_text": "Everyone can follow",
        "birth_privacy": "0",
        "birth_privacy_text": "Everyone",
        "status": "1",
        "status_text": "Online",
        "visit_privacy": "0",
        "visit_privacy_text": "Visible",
        "post_privacy": "0",
        "confirm_followers": "0",
        "show_activities_privacy": "0",
        "share_my_location": "0",
        "share_my_data": "0"
    }
}
```

### Privacy Settings Fields

| Field | Type | Values | Description |
|-------|------|--------|-------------|
| `message_privacy` | string | "0", "1" | Who can message you |
| `follow_privacy` | string | "0", "1" | Who can follow you |
| `birth_privacy` | string | "0", "1", "2" | Who can see your birthday |
| `status` | string | "0", "1" | Online/Offline status |
| `visit_privacy` | string | "0", "1" | Profile visit visibility |
| `post_privacy` | string | "0", "1", "2", "3" | Default post privacy |
| `confirm_followers` | string | "0", "1" | Require follow approval |
| `show_activities_privacy` | string | "0", "1" | Show/hide activities |
| `share_my_location` | string | "0", "1" | Share location data |
| `share_my_data` | string | "0", "1" | Share personal data |

### Privacy Values Explained

#### Message Privacy (`message_privacy`)
- `"0"` - Everyone can message me
- `"1"` - Only friends can message me

#### Follow Privacy (`follow_privacy`)
- `"0"` - Everyone can follow me
- `"1"` - No one can follow me

#### Birth Privacy (`birth_privacy`)
- `"0"` - Everyone can see my birthday
- `"1"` - Only friends can see my birthday
- `"2"` - Only I can see my birthday

#### Status (`status`)
- `"0"` - Appear offline
- `"1"` - Appear online

#### Visit Privacy (`visit_privacy`)
- `"0"` - My profile visits are visible to others
- `"1"` - My profile visits are hidden

#### Post Privacy (`post_privacy`)
- `"0"` - Public (everyone)
- `"1"` - Friends only
- `"2"` - Only me
- `"3"` - Custom

#### Confirm Followers (`confirm_followers`)
- `"0"` - Auto-approve follow requests
- `"1"` - Manually approve follow requests

#### Show Activities Privacy (`show_activities_privacy`)
- `"0"` - Show my activities
- `"1"` - Hide my activities

#### Share Location (`share_my_location`)
- `"0"` - Don't share location
- `"1"` - Share location

#### Share Data (`share_my_data`)
- `"0"` - Don't share data with third parties
- `"1"` - Share data with third parties

---

## 2. Update Privacy Settings

Updates one or more privacy settings for the authenticated user.

### Endpoint
```http
POST /api/v1/privacy/settings
PUT  /api/v1/privacy/settings
```

Both POST and PUT methods are supported for backward compatibility.

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body

You can update one or multiple privacy settings in a single request. Only include the fields you want to update.

```json
{
    "message_privacy": "1",
    "follow_privacy": "0",
    "birth_privacy": "2",
    "status": "1",
    "visit_privacy": "0",
    "confirm_followers": "1"
}
```

### Request Parameters

All parameters are optional. Only include the settings you want to update.

| Parameter | Type | Required | Values | Description |
|-----------|------|----------|--------|-------------|
| `message_privacy` | string | No | "0", "1" | Message privacy setting |
| `follow_privacy` | string | No | "0", "1" | Follow privacy setting |
| `birth_privacy` | string | No | "0", "1", "2" | Birthday privacy setting |
| `status` | string | No | "0", "1" | Online status |
| `visit_privacy` | string | No | "0", "1" | Profile visit privacy |
| `post_privacy` | string | No | "0", "1", "2", "3" | Default post privacy |
| `confirm_followers` | string | No | "0", "1" | Require follow approval |
| `show_activities_privacy` | string | No | "0", "1" | Activities visibility |
| `share_my_location` | string | No | "0", "1" | Location sharing |
| `share_my_data` | string | No | "0", "1" | Data sharing |

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Privacy settings updated successfully",
    "privacy_settings": {
        "message_privacy": "1",
        "follow_privacy": "0",
        "birth_privacy": "2",
        "status": "1",
        "visit_privacy": "0",
        "post_privacy": "0",
        "confirm_followers": "1",
        "show_activities_privacy": "0",
        "share_my_location": "0",
        "share_my_data": "0"
    }
}
```

---

## Error Responses

### 401 Unauthorized - No Token
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

### 401 Unauthorized - Invalid Token
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

### 404 Not Found - User Not Found
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "6",
        "error_text": "User not found."
    }
}
```

### 422 Validation Error
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "The message_privacy field must be 0 or 1.",
        "The birth_privacy field must be 0, 1, or 2."
    ]
}
```

### 422 No Data to Update
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "8",
        "error_text": "No privacy settings to update."
    }
}
```

---

## Example Usage

### cURL Examples

#### Get Current Privacy Settings
```bash
curl -X GET "http://localhost/api/v1/privacy/settings" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

#### Update Message Privacy Only
```bash
curl -X POST "http://localhost/api/v1/privacy/settings" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "message_privacy": "1"
  }'
```

#### Update Multiple Privacy Settings
```bash
curl -X POST "http://localhost/api/v1/privacy/settings" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "message_privacy": "1",
    "follow_privacy": "0",
    "birth_privacy": "2",
    "status": "1",
    "confirm_followers": "1"
  }'
```

#### Update All Privacy Settings
```bash
curl -X PUT "http://localhost/api/v1/privacy/settings" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "message_privacy": "1",
    "follow_privacy": "0",
    "birth_privacy": "2",
    "status": "1",
    "visit_privacy": "0",
    "post_privacy": "0",
    "confirm_followers": "1",
    "show_activities_privacy": "0",
    "share_my_location": "0",
    "share_my_data": "0"
  }'
```

---

## JavaScript/TypeScript Examples

### Using Fetch API

```javascript
// Get Privacy Settings
async function getPrivacySettings(token) {
    const response = await fetch('http://localhost/api/v1/privacy/settings', {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        }
    });
    
    const data = await response.json();
    return data;
}

// Update Privacy Settings
async function updatePrivacySettings(token, settings) {
    const response = await fetch('http://localhost/api/v1/privacy/settings', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(settings)
    });
    
    const data = await response.json();
    return data;
}

// Usage
const token = 'abc123session456';

// Get current settings
const currentSettings = await getPrivacySettings(token);
console.log(currentSettings.privacy_settings);

// Update settings
const newSettings = {
    message_privacy: '1',
    follow_privacy: '0',
    birth_privacy: '2'
};
const result = await updatePrivacySettings(token, newSettings);
console.log(result.message); // "Privacy settings updated successfully"
```

### Using Axios

```javascript
import axios from 'axios';

const api = axios.create({
    baseURL: 'http://localhost/api/v1',
    headers: {
        'Content-Type': 'application/json'
    }
});

// Add auth token to all requests
api.interceptors.request.use(config => {
    const token = localStorage.getItem('session_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Get Privacy Settings
async function getPrivacySettings() {
    try {
        const response = await api.get('/privacy/settings');
        return response.data.privacy_settings;
    } catch (error) {
        console.error('Error fetching privacy settings:', error.response?.data);
        throw error;
    }
}

// Update Privacy Settings
async function updatePrivacySettings(settings) {
    try {
        const response = await api.post('/privacy/settings', settings);
        return response.data;
    } catch (error) {
        console.error('Error updating privacy settings:', error.response?.data);
        throw error;
    }
}

// Usage
const settings = await getPrivacySettings();
console.log('Message Privacy:', settings.message_privacy_text);

// Update specific setting
await updatePrivacySettings({ message_privacy: '1' });
```

---

## Migration from Old API

### Old API → New API Mapping

| Old Endpoint | New Endpoint |
|--------------|--------------|
| N/A (settings retrieved via get_user_data) | `GET /api/v1/privacy/settings` |
| `POST /phone/update_user_data.php?type=update_user_data` with `type=privacy_settings` | `POST /api/v1/privacy/settings` |

### Parameter Changes

**Old API (update_user_data.php):**
```json
{
    "user_id": "123",
    "s": "session_token",
    "type": "privacy_settings",
    "user_data": "{\"message_privacy\":\"1\",\"follow_privacy\":\"0\"}"
}
```

**New API:**
```json
// Header: Authorization: Bearer session_token
{
    "message_privacy": "1",
    "follow_privacy": "0"
}
```

### Key Differences

1. **Authentication**: Moved from POST parameters to Authorization header
2. **Data Format**: Direct JSON instead of JSON-encoded string
3. **Type Parameter**: Removed (now part of the endpoint URL)
4. **User ID**: Automatically extracted from session token

---

## Common Use Cases

### 1. Make Profile Private
```json
{
    "message_privacy": "1",
    "follow_privacy": "1",
    "birth_privacy": "2",
    "visit_privacy": "1",
    "post_privacy": "2",
    "confirm_followers": "1",
    "show_activities_privacy": "1"
}
```

### 2. Make Profile Public
```json
{
    "message_privacy": "0",
    "follow_privacy": "0",
    "birth_privacy": "0",
    "visit_privacy": "0",
    "post_privacy": "0",
    "confirm_followers": "0",
    "show_activities_privacy": "0"
}
```

### 3. Hide Birthday from Everyone
```json
{
    "birth_privacy": "2"
}
```

### 4. Only Allow Friends to Message
```json
{
    "message_privacy": "1"
}
```

### 5. Require Follow Approval
```json
{
    "confirm_followers": "1"
}
```

### 6. Appear Offline
```json
{
    "status": "0"
}
```

---

## Best Practices

### 1. Fetch Before Update
Always fetch current settings before updating to avoid accidentally changing unintended settings:

```javascript
// Good
const current = await getPrivacySettings(token);
const updated = { ...current, message_privacy: '1' };
await updatePrivacySettings(token, updated);

// Avoid - may reset other settings unintentionally
await updatePrivacySettings(token, { message_privacy: '1' });
```

### 2. Validate Values Client-Side
Validate privacy values before sending to reduce API errors:

```javascript
function isValidMessagePrivacy(value) {
    return ['0', '1'].includes(value);
}

function isValidBirthPrivacy(value) {
    return ['0', '1', '2'].includes(value);
}
```

### 3. Handle Errors Gracefully
```javascript
try {
    await updatePrivacySettings(token, settings);
    showSuccessMessage('Privacy settings updated!');
} catch (error) {
    if (error.response?.status === 401) {
        // Redirect to login
        redirectToLogin();
    } else if (error.response?.status === 422) {
        // Show validation errors
        showErrors(error.response.data.errors);
    } else {
        showErrorMessage('Failed to update settings');
    }
}
```

### 4. Debounce Updates
If updating settings in real-time (e.g., toggle switches), debounce the API calls:

```javascript
import { debounce } from 'lodash';

const debouncedUpdate = debounce(async (settings) => {
    await updatePrivacySettings(token, settings);
}, 500);

// Usage in React
const handlePrivacyChange = (key, value) => {
    setSettings(prev => ({ ...prev, [key]: value }));
    debouncedUpdate({ [key]: value });
};
```

---

## Security Considerations

1. **Always use HTTPS** in production to protect session tokens
2. **Privacy settings are user-specific** - cannot update other users' settings
3. **Settings are applied immediately** - no confirmation required
4. **Invalid values are rejected** - strict validation on all fields
5. **Session tokens must be kept secure** - store in secure storage only

---

## Database Table

Privacy settings are stored in the `Wo_Users` table:

```sql
SELECT 
    message_privacy,
    follow_privacy,
    birth_privacy,
    status,
    visit_privacy,
    post_privacy,
    confirm_followers,
    show_activities_privacy,
    share_my_location,
    share_my_data
FROM Wo_Users
WHERE user_id = ?
```

---

## Related Endpoints

- **General Settings**: `GET /api/v1/settings`
- **Update User Data**: `POST /api/v1/settings/update-user-data`
- **Profile Data**: `GET /api/v1/profile/user-data`

