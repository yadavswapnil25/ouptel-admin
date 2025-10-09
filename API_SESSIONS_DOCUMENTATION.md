# Session Management API Documentation

This API mimics the old WoWonder API structure for managing user sessions and active devices.

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

The Session Management API allows users to:
- View all active sessions across all devices
- Identify which device/browser each session belongs to
- Log out from specific devices
- Log out from all other devices (security feature)

This is useful for:
- Security management
- Device tracking
- Remote logout from compromised devices
- Managing account access

---

## 1. Get All Sessions

Retrieves all active sessions for the authenticated user across all devices.

### Endpoint
```http
GET /api/v1/sessions
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
    "total_sessions": 3,
    "data": [
        {
            "id": "1234",
            "user_id": "123",
            "session_id": "abc123xyz456",
            "platform": "Android",
            "platform_details": "Mozilla/5.0 (Linux; Android 11) Mobile",
            "time": 1704441600,
            "time_text": "5 minutes ago",
            "created_at": "2024-01-05 10:30:00",
            "is_current": true,
            "browser": "Google Chrome",
            "device": "Android Phone",
            "ip_address": "192.168.1.100"
        },
        {
            "id": "1235",
            "user_id": "123",
            "session_id": "def789uvw012",
            "platform": "Web",
            "platform_details": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0",
            "time": 1704355200,
            "time_text": "1 day ago",
            "created_at": "2024-01-04 10:30:00",
            "is_current": false,
            "browser": "Google Chrome",
            "device": "Windows PC",
            "ip_address": "192.168.1.101"
        },
        {
            "id": "1236",
            "user_id": "123",
            "session_id": "ghi345rst678",
            "platform": "iOS",
            "platform_details": "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari",
            "time": 1704268800,
            "time_text": "2 days ago",
            "created_at": "2024-01-03 10:30:00",
            "is_current": false,
            "browser": "Safari",
            "device": "iPhone",
            "ip_address": "192.168.1.102"
        }
    ]
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Unique session ID (database primary key) |
| `user_id` | string | User's ID |
| `session_id` | string | Session token (the actual auth token) |
| `platform` | string | Platform type (Android, iOS, Windows, Mac, Linux, Web) |
| `platform_details` | string | Full user agent string |
| `time` | integer | Unix timestamp when session was created |
| `time_text` | string | Human-readable time (e.g., "5 minutes ago") |
| `created_at` | string | Formatted date-time (Y-m-d H:i:s) |
| `is_current` | boolean | Whether this is the current session |
| `browser` | string | Browser name (Chrome, Firefox, Safari, Edge, etc.) |
| `device` | string | Device type (Android Phone, iPhone, Windows PC, etc.) |
| `ip_address` | string | IP address (if available) |
| `device_id` | string | Device ID (if available) |

### Platform Values
- `Android` - Android devices
- `iOS` - iPhone/iPad devices
- `Windows` - Windows devices
- `Mac` - Mac devices
- `Linux` - Linux devices
- `Web` - Web browsers
- `Unknown` - Platform could not be detected

---

## 2. Delete Specific Session

Logs out from a specific device by deleting its session.

### Endpoint
```http
POST /api/v1/sessions/delete
DELETE /api/v1/sessions/{id}
```

Both methods are supported for flexibility.

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body (POST method)
```json
{
    "id": 1235
}
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Session ID to delete (from session list) |

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Session deleted successfully"
}
```

### Important Notes
- ❌ **Cannot delete current session** - Use logout API instead
- ✅ Can only delete sessions belonging to the authenticated user
- ✅ Session is immediately terminated
- ✅ User on that device will be logged out

---

## 3. Delete All Other Sessions

Logs out from all devices except the current one (useful for security).

### Endpoint
```http
POST /api/v1/sessions/delete-all
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
    "message": "All other sessions deleted successfully",
    "deleted_count": 2
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `message` | string | Success message |
| `deleted_count` | integer | Number of sessions deleted |

### Use Cases
- 🔒 Security: Someone unauthorized accessed your account
- 📱 Lost device: Lost phone or tablet
- 🔐 Password changed: After changing password
- 🚨 Suspicious activity: Detected unauthorized access

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

### 404 Not Found - Session Not Found
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "8",
        "error_text": "Session not found or access denied."
    }
}
```

### 422 Validation Error - Cannot Delete Current Session
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "9",
        "error_text": "Cannot delete current session. Please use logout instead."
    }
}
```

### 422 Validation Error - Missing ID
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "The id field is required.",
        "The id must be an integer."
    ]
}
```

---

## Example Usage

### cURL Examples

#### Get All Sessions
```bash
curl -X GET "http://localhost/api/v1/sessions" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

#### Delete Specific Session (POST method)
```bash
curl -X POST "http://localhost/api/v1/sessions/delete" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "id": 1235
  }'
```

#### Delete Specific Session (DELETE method)
```bash
curl -X DELETE "http://localhost/api/v1/sessions/1235" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

#### Delete All Other Sessions
```bash
curl -X POST "http://localhost/api/v1/sessions/delete-all" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

---

## JavaScript/TypeScript Examples

### Using Fetch API

```javascript
// Get All Sessions
async function getAllSessions(token) {
    const response = await fetch('http://localhost/api/v1/sessions', {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        }
    });
    
    const data = await response.json();
    return data.data; // Returns array of sessions
}

// Delete Specific Session
async function deleteSession(token, sessionId) {
    const response = await fetch('http://localhost/api/v1/sessions/delete', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: sessionId })
    });
    
    const data = await response.json();
    return data;
}

// Delete All Other Sessions
async function deleteAllOtherSessions(token) {
    const response = await fetch('http://localhost/api/v1/sessions/delete-all', {
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

// Get and display all sessions
const sessions = await getAllSessions(token);
console.log('Total sessions:', sessions.length);
sessions.forEach(session => {
    console.log(`${session.device} - ${session.browser} - ${session.time_text}`);
    if (session.is_current) {
        console.log('  ^ This is your current session');
    }
});

// Delete a specific session
await deleteSession(token, 1235);
console.log('Session deleted!');

// Log out from all other devices
const result = await deleteAllOtherSessions(token);
console.log(`Logged out from ${result.deleted_count} devices`);
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

// Get All Sessions
async function getAllSessions() {
    try {
        const response = await api.get('/sessions');
        return response.data.data;
    } catch (error) {
        console.error('Error fetching sessions:', error.response?.data);
        throw error;
    }
}

// Delete Specific Session
async function deleteSession(sessionId) {
    try {
        const response = await api.post('/sessions/delete', { id: sessionId });
        return response.data;
    } catch (error) {
        console.error('Error deleting session:', error.response?.data);
        throw error;
    }
}

// Delete All Other Sessions
async function deleteAllOtherSessions() {
    try {
        const response = await api.post('/sessions/delete-all');
        return response.data;
    } catch (error) {
        console.error('Error deleting sessions:', error.response?.data);
        throw error;
    }
}

// Usage Example
async function displaySessions() {
    const sessions = await getAllSessions();
    
    sessions.forEach(session => {
        console.log({
            device: session.device,
            browser: session.browser,
            location: session.ip_address,
            lastActive: session.time_text,
            isCurrent: session.is_current
        });
    });
}
```

---

## React Example with UI

```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function SessionManagement() {
    const [sessions, setSessions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        loadSessions();
    }, []);

    const loadSessions = async () => {
        setLoading(true);
        setError('');
        
        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.get('http://localhost/api/v1/sessions', {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            
            setSessions(response.data.data);
        } catch (err) {
            setError('Failed to load sessions');
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const handleDeleteSession = async (sessionId) => {
        if (!confirm('Are you sure you want to log out from this device?')) {
            return;
        }

        try {
            const token = localStorage.getItem('session_token');
            await axios.post(
                'http://localhost/api/v1/sessions/delete',
                { id: sessionId },
                { headers: { 'Authorization': `Bearer ${token}` } }
            );
            
            // Reload sessions
            await loadSessions();
            alert('Session deleted successfully');
        } catch (err) {
            alert('Failed to delete session');
            console.error(err);
        }
    };

    const handleDeleteAllOther = async () => {
        if (!confirm('Log out from all other devices? This cannot be undone.')) {
            return;
        }

        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.post(
                'http://localhost/api/v1/sessions/delete-all',
                {},
                { headers: { 'Authorization': `Bearer ${token}` } }
            );
            
            alert(`Logged out from ${response.data.deleted_count} device(s)`);
            await loadSessions();
        } catch (err) {
            alert('Failed to delete sessions');
            console.error(err);
        }
    };

    if (loading) return <div>Loading sessions...</div>;
    if (error) return <div className="error">{error}</div>;

    return (
        <div className="session-management">
            <h2>Active Sessions</h2>
            <p>Manage your active sessions across all devices</p>

            <button onClick={handleDeleteAllOther} className="btn-danger">
                Log Out All Other Devices
            </button>

            <div className="sessions-list">
                {sessions.map(session => (
                    <div key={session.id} className={`session-item ${session.is_current ? 'current' : ''}`}>
                        <div className="session-icon">
                            {session.platform === 'Android' && '📱'}
                            {session.platform === 'iOS' && ''}
                            {session.platform === 'Windows' && '💻'}
                            {session.platform === 'Mac' && ''}
                            {session.platform === 'Web' && '🌐'}
                        </div>
                        
                        <div className="session-info">
                            <h3>{session.device}</h3>
                            <p>{session.browser}</p>
                            <p className="time">{session.time_text}</p>
                            {session.ip_address && (
                                <p className="ip">IP: {session.ip_address}</p>
                            )}
                            {session.is_current && (
                                <span className="badge">Current Session</span>
                            )}
                        </div>
                        
                        <div className="session-actions">
                            {!session.is_current && (
                                <button
                                    onClick={() => handleDeleteSession(session.id)}
                                    className="btn-delete"
                                >
                                    Log Out
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default SessionManagement;
```

---

## Migration from Old API

### Old API → New API Mapping

| Old Endpoint | New Endpoint |
|--------------|--------------|
| `POST /v2/endpoints/sessions.php` with `type=get` | `GET /api/v1/sessions` |
| `POST /v2/endpoints/sessions.php` with `type=delete` | `POST /api/v1/sessions/delete` |
| N/A | `POST /api/v1/sessions/delete-all` |

### Parameter Changes

**Old API (sessions.php):**
```json
{
    "access_token": "session_token",
    "type": "get"
}
```

**New API:**
```
GET /api/v1/sessions
Header: Authorization: Bearer session_token
```

**Old API (delete session):**
```json
{
    "access_token": "session_token",
    "type": "delete",
    "id": 1235
}
```

**New API:**
```json
POST /api/v1/sessions/delete
Header: Authorization: Bearer session_token
Body: { "id": 1235 }
```

---

## Security Best Practices

### 1. Regular Session Reviews
Encourage users to regularly review their active sessions:
```javascript
// Show session count badge
const sessionCount = await getAllSessions(token);
if (sessionCount.length > 3) {
    showWarning(`You have ${sessionCount.length} active sessions`);
}
```

### 2. Automatic Session Cleanup
Implement automatic cleanup of old sessions:
```javascript
// Delete sessions older than 30 days
const oldSessions = sessions.filter(s => {
    const daysSince = (Date.now() / 1000 - s.time) / 86400;
    return daysSince > 30 && !s.is_current;
});

for (const session of oldSessions) {
    await deleteSession(token, session.id);
}
```

### 3. Suspicious Activity Detection
Alert users of suspicious activity:
```javascript
// Detect sessions from unusual locations
const knownIPs = ['192.168.1.100', '10.0.0.5'];
const suspiciousSessions = sessions.filter(s => 
    s.ip_address && !knownIPs.includes(s.ip_address)
);

if (suspiciousSessions.length > 0) {
    alert('Suspicious activity detected! Review your sessions.');
}
```

### 4. Post-Password-Change Cleanup
After password change, recommend logging out other sessions:
```javascript
async function afterPasswordChange() {
    if (confirm('Log out from all other devices for security?')) {
        await deleteAllOtherSessions(token);
    }
}
```

---

## Common Use Cases

### 1. Security Dashboard
```javascript
async function showSecurityDashboard() {
    const sessions = await getAllSessions(token);
    
    return {
        totalDevices: sessions.length,
        currentDevice: sessions.find(s => s.is_current),
        otherDevices: sessions.filter(s => !s.is_current),
        lastActivity: sessions[0].time_text,
        platforms: [...new Set(sessions.map(s => s.platform))]
    };
}
```

### 2. Lost Device Management
```javascript
async function handleLostDevice(deviceType) {
    const sessions = await getAllSessions(token);
    const lostDeviceSessions = sessions.filter(s => 
        s.device.includes(deviceType) && !s.is_current
    );
    
    for (const session of lostDeviceSessions) {
        await deleteSession(token, session.id);
    }
    
    return `Logged out from ${lostDeviceSessions.length} ${deviceType}(s)`;
}

// Usage
await handleLostDevice('iPhone');
```

### 3. Session Notification System
```javascript
async function notifyNewSession(newSession) {
    // Send notification when new session is detected
    const notification = {
        title: 'New Login Detected',
        body: `${newSession.device} - ${newSession.browser}`,
        action: 'Review Sessions',
        timestamp: newSession.time
    };
    
    // Show notification to user
    showPushNotification(notification);
}
```

---

## Testing

### Test Cases

```javascript
// Test 1: Get all sessions
const sessions = await getAllSessions(token);
console.assert(sessions.length > 0, 'Should have at least one session');
console.assert(sessions.some(s => s.is_current), 'Should have current session');

// Test 2: Current session is marked correctly
const currentSession = sessions.find(s => s.is_current);
console.assert(currentSession.session_id === token, 'Current session should match token');

// Test 3: Cannot delete current session
try {
    await deleteSession(token, currentSession.id);
    console.error('Should not allow deleting current session');
} catch (error) {
    console.assert(error.response.data.errors.error_id === '9', 'Should return error 9');
}

// Test 4: Can delete other sessions
const otherSession = sessions.find(s => !s.is_current);
if (otherSession) {
    await deleteSession(token, otherSession.id);
    const newSessions = await getAllSessions(token);
    console.assert(newSessions.length === sessions.length - 1, 'Should have one less session');
}

// Test 5: Delete all other sessions
const result = await deleteAllOtherSessions(token);
const finalSessions = await getAllSessions(token);
console.assert(finalSessions.length === 1, 'Should only have current session left');
console.assert(finalSessions[0].is_current === true, 'Remaining session should be current');
```

---

## Database Schema

Sessions are stored in the `Wo_AppsSessions` table:

```sql
SELECT 
    id,
    user_id,
    session_id,
    platform_type,
    platform_details,
    device_id,
    ip_address,
    time
FROM Wo_AppsSessions
WHERE user_id = ?
ORDER BY time DESC;
```

---

## Related Endpoints

- **Login**: `POST /api/v1/login` (creates new session)
- **Logout**: `POST /api/v1/logout` (deletes current session)
- **Change Password**: `POST /api/v1/password/change` (auto-deletes other sessions)
- **Profile Data**: `GET /api/v1/profile/user-data`
- **Privacy Settings**: `GET /api/v1/privacy/settings`

