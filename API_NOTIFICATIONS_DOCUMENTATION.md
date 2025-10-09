# Notification Settings API Documentation

This API mimics the old WoWonder API structure for managing notification preferences.

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

## Overview

The Notification Settings API allows users to control which activities trigger notifications:
- 🔔 Enable/disable specific notification types
- 📢 Manage email and push notification preferences
- 🎚️ Fine-grained control over 12 notification types
- 🔕 Quick enable/disable all notifications

---

## Notification Types

| Setting | Description | Default |
|---------|-------------|---------|
| `e_liked` | Someone liked your post | Enabled |
| `e_shared` | Someone shared your post | Enabled |
| `e_wondered` | Someone reacted to your post | Enabled |
| `e_commented` | Someone commented on your post | Enabled |
| `e_followed` | Someone followed you | Enabled |
| `e_accepted` | Someone accepted your follow request | Enabled |
| `e_mentioned` | Someone mentioned you | Enabled |
| `e_joined_group` | Someone joined your group | Enabled |
| `e_liked_page` | Someone liked your page | Enabled |
| `e_visited` | Someone visited your profile | Enabled |
| `e_profile_wall_post` | Someone posted on your wall | Enabled |
| `e_memory` | Memory reminders | Enabled |

**Values:**
- `1` = Enabled (receive notifications)
- `0` = Disabled (no notifications)

---

## 1. Get Notification Settings

Retrieves the current user's notification preferences.

### Endpoint
```http
GET /api/v1/notifications/settings
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
    "notification_settings": {
        "e_liked": 1,
        "e_shared": 1,
        "e_wondered": 1,
        "e_commented": 1,
        "e_followed": 1,
        "e_accepted": 1,
        "e_mentioned": 1,
        "e_joined_group": 1,
        "e_liked_page": 1,
        "e_visited": 1,
        "e_profile_wall_post": 1,
        "e_memory": 1
    },
    "notification_settings_detailed": {
        "e_liked": {
            "value": 1,
            "enabled": true,
            "label": "Someone liked your post"
        },
        "e_shared": {
            "value": 1,
            "enabled": true,
            "label": "Someone shared your post"
        },
        "e_wondered": {
            "value": 1,
            "enabled": true,
            "label": "Someone reacted to your post"
        },
        "e_commented": {
            "value": 1,
            "enabled": true,
            "label": "Someone commented on your post"
        },
        "e_followed": {
            "value": 1,
            "enabled": true,
            "label": "Someone followed you"
        },
        "e_accepted": {
            "value": 1,
            "enabled": true,
            "label": "Someone accepted your follow request"
        },
        "e_mentioned": {
            "value": 1,
            "enabled": true,
            "label": "Someone mentioned you"
        },
        "e_joined_group": {
            "value": 1,
            "enabled": true,
            "label": "Someone joined your group"
        },
        "e_liked_page": {
            "value": 1,
            "enabled": true,
            "label": "Someone liked your page"
        },
        "e_visited": {
            "value": 1,
            "enabled": true,
            "label": "Someone visited your profile"
        },
        "e_profile_wall_post": {
            "value": 1,
            "enabled": true,
            "label": "Someone posted on your wall"
        },
        "e_memory": {
            "value": 1,
            "enabled": true,
            "label": "Memory reminders"
        }
    }
}
```

---

## 2. Update Notification Settings

Updates one or more notification preferences.

### Endpoint
```http
POST /api/v1/notifications/settings
PUT  /api/v1/notifications/settings
```

Both POST and PUT methods are supported.

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body

You can update one or multiple settings in a single request. Only include the settings you want to change.

```json
{
    "e_liked": 0,
    "e_commented": 1,
    "e_followed": 1
}
```

### Request Parameters

All parameters are optional. Only include the settings you want to update.

| Parameter | Type | Required | Values | Description |
|-----------|------|----------|--------|-------------|
| `e_liked` | integer | No | 0, 1 | Post likes |
| `e_shared` | integer | No | 0, 1 | Post shares |
| `e_wondered` | integer | No | 0, 1 | Post reactions |
| `e_commented` | integer | No | 0, 1 | Post comments |
| `e_followed` | integer | No | 0, 1 | New followers |
| `e_accepted` | integer | No | 0, 1 | Follow request accepted |
| `e_mentioned` | integer | No | 0, 1 | Mentions |
| `e_joined_group` | integer | No | 0, 1 | Group joins |
| `e_liked_page` | integer | No | 0, 1 | Page likes |
| `e_visited` | integer | No | 0, 1 | Profile visits |
| `e_profile_wall_post` | integer | No | 0, 1 | Wall posts |
| `e_memory` | integer | No | 0, 1 | Memory reminders |

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Notification settings updated successfully",
    "notification_settings": {
        "e_liked": 0,
        "e_shared": 1,
        "e_wondered": 1,
        "e_commented": 1,
        "e_followed": 1,
        "e_accepted": 1,
        "e_mentioned": 1,
        "e_joined_group": 1,
        "e_liked_page": 1,
        "e_visited": 1,
        "e_profile_wall_post": 1,
        "e_memory": 1
    }
}
```

---

## 3. Enable All Notifications

Enables all notification types at once.

### Endpoint
```http
POST /api/v1/notifications/settings/enable-all
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
    "message": "All notifications enabled",
    "notification_settings": {
        "e_liked": 1,
        "e_shared": 1,
        "e_wondered": 1,
        "e_commented": 1,
        "e_followed": 1,
        "e_accepted": 1,
        "e_mentioned": 1,
        "e_joined_group": 1,
        "e_liked_page": 1,
        "e_visited": 1,
        "e_profile_wall_post": 1,
        "e_memory": 1
    }
}
```

---

## 4. Disable All Notifications

Disables all notification types at once.

### Endpoint
```http
POST /api/v1/notifications/settings/disable-all
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
    "message": "All notifications disabled",
    "notification_settings": {
        "e_liked": 0,
        "e_shared": 0,
        "e_wondered": 0,
        "e_commented": 0,
        "e_followed": 0,
        "e_accepted": 0,
        "e_mentioned": 0,
        "e_joined_group": 0,
        "e_liked_page": 0,
        "e_visited": 0,
        "e_profile_wall_post": 0,
        "e_memory": 0
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
        "The e_liked field must be 0 or 1."
    ]
}
```

---

## Example Usage

### cURL Examples

#### Get Notification Settings
```bash
curl -X GET "http://localhost/api/v1/notifications/settings" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

#### Update Specific Settings
```bash
curl -X POST "http://localhost/api/v1/notifications/settings" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "e_liked": 0,
    "e_commented": 1,
    "e_followed": 1
  }'
```

#### Update All Settings
```bash
curl -X PUT "http://localhost/api/v1/notifications/settings" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "e_liked": 1,
    "e_shared": 1,
    "e_wondered": 1,
    "e_commented": 1,
    "e_followed": 1,
    "e_accepted": 1,
    "e_mentioned": 1,
    "e_joined_group": 1,
    "e_liked_page": 1,
    "e_visited": 1,
    "e_profile_wall_post": 1,
    "e_memory": 1
  }'
```

#### Enable All Notifications
```bash
curl -X POST "http://localhost/api/v1/notifications/settings/enable-all" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

#### Disable All Notifications
```bash
curl -X POST "http://localhost/api/v1/notifications/settings/disable-all" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

---

## JavaScript/TypeScript Examples

### Using Fetch API

```javascript
// Get Notification Settings
async function getNotificationSettings(token) {
    const response = await fetch('http://localhost/api/v1/notifications/settings', {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        }
    });
    
    const data = await response.json();
    return data.notification_settings;
}

// Update Notification Settings
async function updateNotificationSettings(token, settings) {
    const response = await fetch('http://localhost/api/v1/notifications/settings', {
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

// Enable All Notifications
async function enableAllNotifications(token) {
    const response = await fetch('http://localhost/api/v1/notifications/settings/enable-all', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        }
    });
    
    const data = await response.json();
    return data;
}

// Disable All Notifications
async function disableAllNotifications(token) {
    const response = await fetch('http://localhost/api/v1/notifications/settings/disable-all', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        }
    });
    
    const data = await response.json();
    return data;
}

// Usage
const token = 'abc123session456';

// Get current settings
const settings = await getNotificationSettings(token);
console.log('Likes notifications:', settings.e_liked ? 'Enabled' : 'Disabled');

// Update specific setting
await updateNotificationSettings(token, {
    e_liked: 0  // Disable like notifications
});

// Enable all
await enableAllNotifications(token);

// Disable all
await disableAllNotifications(token);
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

// Get Notification Settings
async function getNotificationSettings() {
    try {
        const response = await api.get('/notifications/settings');
        return response.data.notification_settings;
    } catch (error) {
        console.error('Error fetching notification settings:', error.response?.data);
        throw error;
    }
}

// Update Notification Settings
async function updateNotificationSettings(settings) {
    try {
        const response = await api.post('/notifications/settings', settings);
        return response.data;
    } catch (error) {
        console.error('Error updating notification settings:', error.response?.data);
        throw error;
    }
}

// Enable All
async function enableAllNotifications() {
    try {
        const response = await api.post('/notifications/settings/enable-all');
        return response.data;
    } catch (error) {
        console.error('Error enabling notifications:', error.response?.data);
        throw error;
    }
}

// Disable All
async function disableAllNotifications() {
    try {
        const response = await api.post('/notifications/settings/disable-all');
        return response.data;
    } catch (error) {
        console.error('Error disabling notifications:', error.response?.data);
        throw error;
    }
}
```

---

## React Example with Toggle Switches

```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function NotificationSettings() {
    const [settings, setSettings] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [success, setSuccess] = useState(false);

    const notificationTypes = [
        { key: 'e_liked', label: '❤️ Someone liked your post' },
        { key: 'e_shared', label: '🔄 Someone shared your post' },
        { key: 'e_wondered', label: '😮 Someone reacted to your post' },
        { key: 'e_commented', label: '💬 Someone commented on your post' },
        { key: 'e_followed', label: '👥 Someone followed you' },
        { key: 'e_accepted', label: '✅ Someone accepted your follow request' },
        { key: 'e_mentioned', label: '📣 Someone mentioned you' },
        { key: 'e_joined_group', label: '👫 Someone joined your group' },
        { key: 'e_liked_page', label: '📄 Someone liked your page' },
        { key: 'e_visited', label: '👁️ Someone visited your profile' },
        { key: 'e_profile_wall_post', label: '📝 Someone posted on your wall' },
        { key: 'e_memory', label: '🎂 Memory reminders' },
    ];

    useEffect(() => {
        loadSettings();
    }, []);

    const loadSettings = async () => {
        setLoading(true);
        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.get('http://localhost/api/v1/notifications/settings', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            
            setSettings(response.data.notification_settings);
        } catch (error) {
            console.error('Failed to load notification settings:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleToggle = async (key) => {
        const newValue = settings[key] === 1 ? 0 : 1;
        
        // Update UI immediately for better UX
        setSettings(prev => ({
            ...prev,
            [key]: newValue
        }));

        setSaving(true);
        setSuccess(false);

        try {
            const token = localStorage.getItem('session_token');
            await axios.post(
                'http://localhost/api/v1/notifications/settings',
                { [key]: newValue },
                { headers: { 'Authorization': `Bearer ${token}` } }
            );

            setSuccess(true);
            setTimeout(() => setSuccess(false), 2000);
        } catch (error) {
            // Revert on error
            setSettings(prev => ({
                ...prev,
                [key]: settings[key]
            }));
            alert('Failed to update notification setting');
        } finally {
            setSaving(false);
        }
    };

    const handleEnableAll = async () => {
        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.post(
                'http://localhost/api/v1/notifications/settings/enable-all',
                {},
                { headers: { 'Authorization': `Bearer ${token}` } }
            );
            
            setSettings(response.data.notification_settings);
            alert('All notifications enabled');
        } catch (error) {
            alert('Failed to enable all notifications');
        }
    };

    const handleDisableAll = async () => {
        if (!confirm('Disable all notifications?')) return;

        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.post(
                'http://localhost/api/v1/notifications/settings/disable-all',
                {},
                { headers: { 'Authorization': `Bearer ${token}` } }
            );
            
            setSettings(response.data.notification_settings);
            alert('All notifications disabled');
        } catch (error) {
            alert('Failed to disable all notifications');
        }
    };

    if (loading) return <div>Loading...</div>;

    return (
        <div className="notification-settings">
            <h2>Notification Settings</h2>
            <p>Choose what notifications you want to receive</p>

            {success && (
                <div className="alert alert-success">
                    Settings saved!
                </div>
            )}

            <div className="bulk-actions">
                <button onClick={handleEnableAll} className="btn btn-primary">
                    Enable All
                </button>
                <button onClick={handleDisableAll} className="btn btn-secondary">
                    Disable All
                </button>
            </div>

            <div className="settings-list">
                {notificationTypes.map(type => (
                    <div key={type.key} className="setting-item">
                        <label>
                            {type.label}
                        </label>
                        <div className="toggle-switch">
                            <input
                                type="checkbox"
                                checked={settings[type.key] === 1}
                                onChange={() => handleToggle(type.key)}
                                disabled={saving}
                            />
                            <span className="slider"></span>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default NotificationSettings;
```

---

## Migration from Old API

### Old API → New API Mapping

| Old Endpoint | New Endpoint |
|--------------|--------------|
| `POST /v2/endpoints/update-user-data.php` (included in user data) | `POST /api/v1/notifications/settings` |
| N/A | `GET /api/v1/notifications/settings` |

### Parameter Changes

**Old API (update-user-data.php):**
```json
{
    "access_token": "session_token",
    "e_liked": 0,
    "e_commented": 1,
    "e_followed": 1
}
```

**New API:**
```json
// Header: Authorization: Bearer session_token
{
    "e_liked": 0,
    "e_commented": 1,
    "e_followed": 1
}
```

### Key Differences

1. **Authentication**: Moved from POST parameters to Authorization header
2. **Dedicated Endpoint**: Separate endpoint specifically for notifications
3. **Response Format**: Returns complete settings object
4. **Detailed View**: Includes human-readable labels
5. **Bulk Actions**: Added enable-all and disable-all endpoints

---

## Best Practices

### 1. Provide Clear Labels

Always show clear descriptions for each notification type:

```javascript
const notificationLabels = {
    e_liked: 'Get notified when someone likes your post',
    e_commented: 'Get notified when someone comments on your post',
    e_followed: 'Get notified when someone follows you',
    // ... etc
};
```

### 2. Save Individual Changes

Update settings as soon as user toggles them:

```javascript
const handleToggle = async (key) => {
    const newValue = settings[key] === 1 ? 0 : 1;
    setSettings(prev => ({ ...prev, [key]: newValue }));
    
    // Save immediately
    await updateNotificationSettings(token, { [key]: newValue });
};
```

### 3. Provide Bulk Actions

Offer quick enable/disable all:

```jsx
<button onClick={() => enableAll()}>Enable All</button>
<button onClick={() => disableAll()}>Disable All</button>
```

### 4. Show Visual Feedback

Provide instant feedback when settings change:

```javascript
const saveWithFeedback = async (settings) => {
    showToast('Saving...', 'info');
    
    try {
        await updateNotificationSettings(token, settings);
        showToast('Settings saved!', 'success');
    } catch (error) {
        showToast('Failed to save', 'error');
    }
};
```

---

## Common Use Cases

### 1. Disable Noisy Notifications
```javascript
// Disable likes and shares, keep important ones
await updateNotificationSettings(token, {
    e_liked: 0,
    e_shared: 0,
    e_wondered: 0
});
```

### 2. Only Critical Notifications
```javascript
// Only mentions and comments
await disableAllNotifications(token);
await updateNotificationSettings(token, {
    e_mentioned: 1,
    e_commented: 1
});
```

### 3. Social Notifications Only
```javascript
await updateNotificationSettings(token, {
    e_followed: 1,
    e_accepted: 1,
    e_mentioned: 1,
    e_liked: 0,
    e_shared: 0
});
```

### 4. Content Creator Settings
```javascript
// All post-related notifications
await updateNotificationSettings(token, {
    e_liked: 1,
    e_shared: 1,
    e_wondered: 1,
    e_commented: 1
});
```

---

## Notification Categories

### Post Engagement
- `e_liked` - Post likes
- `e_shared` - Post shares
- `e_wondered` - Post reactions
- `e_commented` - Post comments
- `e_profile_wall_post` - Wall posts

### Social Interactions
- `e_followed` - New followers
- `e_accepted` - Follow request accepted
- `e_mentioned` - Mentions

### Pages & Groups
- `e_liked_page` - Page likes
- `e_joined_group` - Group joins

### Profile Activity
- `e_visited` - Profile visits

### Reminders
- `e_memory` - Memory reminders

---

## Testing

### Test Cases

```javascript
// Test 1: Get notification settings
const settings = await getNotificationSettings(token);
console.assert(typeof settings === 'object', 'Should return object');
console.assert('e_liked' in settings, 'Should have e_liked field');

// Test 2: Disable specific notification
await updateNotificationSettings(token, { e_liked: 0 });
const updated = await getNotificationSettings(token);
console.assert(updated.e_liked === 0, 'Should disable e_liked');

// Test 3: Enable specific notification
await updateNotificationSettings(token, { e_liked: 1 });
const enabled = await getNotificationSettings(token);
console.assert(enabled.e_liked === 1, 'Should enable e_liked');

// Test 4: Enable all
await enableAllNotifications(token);
const allEnabled = await getNotificationSettings(token);
const allAreOne = Object.values(allEnabled).every(v => v === 1);
console.assert(allAreOne, 'All should be enabled');

// Test 5: Disable all
await disableAllNotifications(token);
const allDisabled = await getNotificationSettings(token);
const allAreZero = Object.values(allDisabled).every(v => v === 0);
console.assert(allAreZero, 'All should be disabled');

// Test 6: Partial update (other settings unchanged)
await enableAllNotifications(token);
await updateNotificationSettings(token, { e_liked: 0 });
const partial = await getNotificationSettings(token);
console.assert(partial.e_liked === 0, 'e_liked should be disabled');
console.assert(partial.e_commented === 1, 'e_commented should still be enabled');
```

---

## Database Schema

Notification settings are stored as JSON in the `Wo_Users` table:

```sql
-- Wo_Users table
SELECT notification_settings 
FROM Wo_Users 
WHERE user_id = ?;

-- Example stored value:
-- {"e_liked":1,"e_shared":1,"e_wondered":1,"e_commented":1,"e_followed":1,"e_accepted":1,"e_mentioned":1,"e_joined_group":1,"e_liked_page":1,"e_visited":1,"e_profile_wall_post":1,"e_memory":1}
```

---

## User Experience Tips

### 1. Group Related Settings

Group notifications by category for better UX:

```jsx
const categories = {
    'Post Activity': ['e_liked', 'e_shared', 'e_wondered', 'e_commented'],
    'Social': ['e_followed', 'e_accepted', 'e_mentioned'],
    'Pages & Groups': ['e_liked_page', 'e_joined_group'],
    'Profile': ['e_visited', 'e_profile_wall_post'],
    'Reminders': ['e_memory']
};
```

### 2. Show Impact

Explain what disabling does:

```jsx
<Tooltip content="You won't receive notifications when someone likes your posts">
    <Toggle name="e_liked" />
</Tooltip>
```

### 3. Suggest Presets

Offer common notification presets:

```javascript
const presets = {
    all: { /* all enabled */ },
    important: { e_commented: 1, e_mentioned: 1, e_followed: 1 },
    minimal: { e_mentioned: 1 },
    none: { /* all disabled */ }
};
```

### 4. Debounce Updates

Prevent too many API calls:

```javascript
import { debounce } from 'lodash';

const debouncedUpdate = debounce(async (settings) => {
    await updateNotificationSettings(token, settings);
}, 500);
```

---

## Security Considerations

1. **User-Only Access**: Users can only update their own notification settings
2. **Valid Values**: Only 0 or 1 are accepted
3. **Partial Updates**: Unchanged settings are preserved
4. **JSON Storage**: Settings stored securely as JSON in database

---

## Related Endpoints

- **Get Notifications**: `GET /api/v1/notifications` (view actual notifications)
- **Mark as Read**: `POST /api/v1/notifications/mark-read`
- **Privacy Settings**: `GET /api/v1/privacy/settings`
- **Profile Settings**: `GET /api/v1/profile/user-data`

