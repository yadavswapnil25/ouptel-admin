# Blocked Users API Documentation

This API mimics the old WoWonder API structure for managing blocked users.

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

The Blocked Users API allows users to:
- View list of all blocked users
- Block users to prevent interactions
- Unblock users to restore interactions
- Check block status between users

**When a user is blocked:**
- ❌ Cannot send messages
- ❌ Cannot see posts
- ❌ Cannot follow/unfollow
- ❌ Cannot comment on posts
- ❌ Automatically unfollowed (both directions)
- ❌ Friend relationship removed (if exists)

---

## 1. Get Blocked Users List

Retrieves all users blocked by the authenticated user.

### Endpoint
```http
GET /api/v1/blocked-users
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
    "total_blocked": 3,
    "blocked_users": [
        {
            "user_id": "456",
            "username": "blocked_user1",
            "name": "John Blocked",
            "first_name": "John",
            "last_name": "Blocked",
            "email": "blocked1@example.com",
            "profile_picture": "upload/photos/2024/01/avatar_456.jpg",
            "cover_picture": "upload/photos/2024/01/cover_456.jpg",
            "avatar_url": "http://localhost/storage/upload/photos/2024/01/avatar_456.jpg",
            "cover_url": "http://localhost/storage/upload/photos/2024/01/cover_456.jpg",
            "gender": "male",
            "gender_text": "Male",
            "verified": "0",
            "lastseen": "off",
            "lastseen_unix_time": 1704355200,
            "lastseen_time_text": "1 day ago",
            "url": "http://localhost/blocked_user1"
        },
        {
            "user_id": "789",
            "username": "blocked_user2",
            "name": "Jane Blocked",
            "first_name": "Jane",
            "last_name": "Blocked",
            "email": "blocked2@example.com",
            "profile_picture": "upload/photos/2024/01/avatar_789.jpg",
            "cover_picture": "upload/photos/2024/01/cover_789.jpg",
            "avatar_url": "http://localhost/storage/upload/photos/2024/01/avatar_789.jpg",
            "cover_url": "http://localhost/storage/upload/photos/2024/01/cover_789.jpg",
            "gender": "female",
            "gender_text": "Female",
            "verified": "1",
            "lastseen": "on",
            "lastseen_unix_time": 1704441600,
            "lastseen_time_text": "5 minutes ago",
            "url": "http://localhost/blocked_user2"
        }
    ]
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `total_blocked` | integer | Total number of blocked users |
| `blocked_users` | array | Array of blocked user objects |

### Blocked User Object

| Field | Type | Description |
|-------|------|-------------|
| `user_id` | string | User's unique ID |
| `username` | string | Username |
| `name` | string | Full name |
| `first_name` | string | First name |
| `last_name` | string | Last name |
| `email` | string | Email address |
| `profile_picture` | string | Relative path to avatar |
| `cover_picture` | string | Relative path to cover |
| `avatar_url` | string | Full URL to avatar |
| `cover_url` | string | Full URL to cover |
| `gender` | string | "male" or "female" |
| `gender_text` | string | "Male" or "Female" |
| `verified` | string | "0" or "1" |
| `lastseen` | string | "on" or "off" (online status) |
| `lastseen_unix_time` | integer | Unix timestamp of last seen |
| `lastseen_time_text` | string | Human-readable last seen |
| `url` | string | Profile URL |

---

## 2. Block or Unblock User

Blocks or unblocks a specific user.

### Endpoint
```http
POST /api/v1/block-user
POST /api/v1/users/{userId}/block
```

Both endpoints are supported.

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body

```json
{
    "recipient_id": 456,
    "block_type": "block"
}
```

### Request Parameters

| Parameter | Type | Required | Values | Description |
|-----------|------|----------|--------|-------------|
| `recipient_id` | integer | Yes | - | User ID to block/unblock |
| `block_type` | string | Yes | "block", "un-block" | Action to perform |

### Block Type Values
- `"block"` - Block the user
- `"un-block"` - Unblock the user

### Success Response (200 OK)

**When blocking:**
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "blocked": "blocked",
    "message": "User blocked successfully"
}
```

**When unblocking:**
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "blocked": "unblocked",
    "message": "User unblocked successfully"
}
```

**When already in desired state:**
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "blocked": "already_blocked",
    "message": "No action taken"
}
```

### Side Effects of Blocking

When you block a user:
1. ✅ User is added to your blocked list
2. ✅ You are automatically unfollowed from them
3. ✅ They are automatically unfollowed from you
4. ✅ Friend relationship is removed (if exists)
5. ✅ They cannot see your posts
6. ✅ They cannot message you
7. ✅ They cannot find you in search

---

## 3. Check Block Status

Checks if a user is blocked (bidirectional check).

### Endpoint
```http
GET /api/v1/users/{userId}/block-status
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `user_id` | integer | Yes | User ID to check block status for |

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "is_blocked": 1,
    "i_blocked_them": 1,
    "they_blocked_me": 0
}
```

### Response Fields

| Field | Type | Values | Description |
|-------|------|--------|-------------|
| `is_blocked` | integer | 0, 1 | Whether any block exists (either direction) |
| `i_blocked_them` | integer | 0, 1 | Whether you blocked them |
| `they_blocked_me` | integer | 0, 1 | Whether they blocked you |

**Usage:**
- `is_blocked: 1` - At least one person blocked the other
- `i_blocked_them: 1` - You blocked this user
- `they_blocked_me: 1` - This user blocked you

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
        "error_text": "User Profile is not exists."
    }
}
```

### 422 Validation Error - Cannot Block Yourself
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "8",
        "error_text": "Cannot block yourself."
    }
}
```

### 403 Forbidden - Cannot Block Admin
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "9",
        "error_text": "Cannot block admin users."
    }
}
```

### 422 Validation Error - Missing Parameters
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "The recipient_id field is required.",
        "The block_type field is required."
    ]
}
```

---

## Example Usage

### cURL Examples

#### Get Blocked Users List
```bash
curl -X GET "http://localhost/api/v1/blocked-users" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

#### Block a User
```bash
curl -X POST "http://localhost/api/v1/block-user" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "recipient_id": 456,
    "block_type": "block"
  }'
```

#### Unblock a User
```bash
curl -X POST "http://localhost/api/v1/block-user" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "recipient_id": 456,
    "block_type": "un-block"
  }'
```

#### Check Block Status
```bash
curl -X GET "http://localhost/api/v1/users/456/block-status?user_id=456" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

---

## JavaScript/TypeScript Examples

### Using Fetch API

```javascript
// Get Blocked Users
async function getBlockedUsers(token) {
    const response = await fetch('http://localhost/api/v1/blocked-users', {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        }
    });
    
    const data = await response.json();
    return data.blocked_users;
}

// Block User
async function blockUser(token, userId) {
    const response = await fetch('http://localhost/api/v1/block-user', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            recipient_id: userId,
            block_type: 'block'
        })
    });
    
    const data = await response.json();
    return data;
}

// Unblock User
async function unblockUser(token, userId) {
    const response = await fetch('http://localhost/api/v1/block-user', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            recipient_id: userId,
            block_type: 'un-block'
        })
    });
    
    const data = await response.json();
    return data;
}

// Check Block Status
async function checkBlockStatus(token, userId) {
    const response = await fetch(
        `http://localhost/api/v1/users/${userId}/block-status?user_id=${userId}`,
        {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        }
    );
    
    const data = await response.json();
    return data;
}

// Usage
const token = 'abc123session456';

// Get all blocked users
const blockedUsers = await getBlockedUsers(token);
console.log(`You have ${blockedUsers.length} blocked users`);

// Block a user
await blockUser(token, 456);
console.log('User blocked successfully');

// Unblock a user
await unblockUser(token, 456);
console.log('User unblocked successfully');

// Check if user is blocked
const status = await checkBlockStatus(token, 456);
if (status.i_blocked_them) {
    console.log('You blocked this user');
} else if (status.they_blocked_me) {
    console.log('This user blocked you');
} else {
    console.log('No blocks between you');
}
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

// Get Blocked Users
async function getBlockedUsers() {
    try {
        const response = await api.get('/blocked-users');
        return response.data.blocked_users;
    } catch (error) {
        console.error('Error fetching blocked users:', error.response?.data);
        throw error;
    }
}

// Block User
async function blockUser(userId) {
    try {
        const response = await api.post('/block-user', {
            recipient_id: userId,
            block_type: 'block'
        });
        return response.data;
    } catch (error) {
        console.error('Error blocking user:', error.response?.data);
        throw error;
    }
}

// Unblock User
async function unblockUser(userId) {
    try {
        const response = await api.post('/block-user', {
            recipient_id: userId,
            block_type: 'un-block'
        });
        return response.data;
    } catch (error) {
        console.error('Error unblocking user:', error.response?.data);
        throw error;
    }
}

// Check Block Status
async function checkBlockStatus(userId) {
    try {
        const response = await api.get(`/users/${userId}/block-status`, {
            params: { user_id: userId }
        });
        return response.data;
    } catch (error) {
        console.error('Error checking block status:', error.response?.data);
        throw error;
    }
}
```

---

## React Example with UI

```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function BlockedUsersManagement() {
    const [blockedUsers, setBlockedUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        loadBlockedUsers();
    }, []);

    const loadBlockedUsers = async () => {
        setLoading(true);
        setError('');
        
        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.get('http://localhost/api/v1/blocked-users', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            
            setBlockedUsers(response.data.blocked_users);
        } catch (err) {
            setError('Failed to load blocked users');
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const handleUnblock = async (userId, username) => {
        if (!confirm(`Unblock ${username}?`)) {
            return;
        }

        try {
            const token = localStorage.getItem('session_token');
            await axios.post(
                'http://localhost/api/v1/block-user',
                {
                    recipient_id: userId,
                    block_type: 'un-block'
                },
                { headers: { 'Authorization': `Bearer ${token}` } }
            );
            
            alert(`${username} unblocked successfully`);
            await loadBlockedUsers();
        } catch (err) {
            alert('Failed to unblock user');
            console.error(err);
        }
    };

    if (loading) return <div>Loading blocked users...</div>;
    if (error) return <div className="error">{error}</div>;

    return (
        <div className="blocked-users-management">
            <h2>Blocked Users</h2>
            <p>Manage users you've blocked</p>

            {blockedUsers.length === 0 ? (
                <div className="empty-state">
                    <p>You haven't blocked anyone yet.</p>
                </div>
            ) : (
                <div className="blocked-users-list">
                    <p>Total blocked: {blockedUsers.length}</p>
                    {blockedUsers.map(user => (
                        <div key={user.user_id} className="blocked-user-item">
                            <img 
                                src={user.avatar_url} 
                                alt={user.name} 
                                className="user-avatar"
                            />
                            
                            <div className="user-info">
                                <h3>{user.name}</h3>
                                <p>@{user.username}</p>
                                {user.verified === '1' && (
                                    <span className="badge-verified">✓ Verified</span>
                                )}
                                <p className="lastseen">{user.lastseen_time_text}</p>
                            </div>
                            
                            <div className="user-actions">
                                <button
                                    onClick={() => handleUnblock(user.user_id, user.username)}
                                    className="btn-unblock"
                                >
                                    Unblock
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export default BlockedUsersManagement;
```

---

## Migration from Old API

### Old API → New API Mapping

| Old Endpoint | New Endpoint |
|--------------|--------------|
| `GET /phone/get_blocked_users.php?type=get_blocked_users` | `GET /api/v1/blocked-users` |
| `POST /phone/block_user.php?type=block_user` | `POST /api/v1/block-user` |
| `GET /v2/endpoints/get-blocked-users.php` | `GET /api/v1/blocked-users` |

### Parameter Changes

**Old API (block_user.php):**
```json
{
    "user_id": "123",
    "s": "session_token",
    "recipient_id": 456,
    "block_type": "block"
}
```

**New API:**
```json
// Header: Authorization: Bearer session_token
{
    "recipient_id": 456,
    "block_type": "block"
}
```

### Key Differences

1. **Authentication**: Moved from POST parameters to Authorization header
2. **User ID**: Automatically extracted from session token
3. **Endpoint Structure**: RESTful structure with clear paths
4. **Response Format**: Maintains backward compatibility

---

## Best Practices

### 1. Confirm Before Blocking

Always ask for user confirmation before blocking:

```javascript
async function confirmAndBlock(userId, username) {
    const confirmed = confirm(
        `Are you sure you want to block ${username}?\n\n` +
        `When you block someone:\n` +
        `• They won't be able to see your posts\n` +
        `• They won't be able to message you\n` +
        `• You'll both be unfollowed from each other`
    );
    
    if (confirmed) {
        await blockUser(token, userId);
    }
}
```

### 2. Update UI Immediately

Provide instant feedback when blocking/unblocking:

```javascript
async function blockUserWithFeedback(userId) {
    // Show loading state
    showLoading();
    
    try {
        await blockUser(token, userId);
        
        // Update UI immediately
        removeUserFromFeed(userId);
        showSuccess('User blocked');
    } catch (error) {
        showError('Failed to block user');
    } finally {
        hideLoading();
    }
}
```

### 3. Handle Block Status in UI

Use block status to control UI elements:

```javascript
async function renderUserActions(user) {
    const status = await checkBlockStatus(token, user.user_id);
    
    if (status.i_blocked_them) {
        return <button onClick={() => unblock()}>Unblock</button>;
    } else if (status.they_blocked_me) {
        return <div>This user blocked you</div>;
    } else {
        return (
            <>
                <button onClick={() => follow()}>Follow</button>
                <button onClick={() => block()}>Block</button>
            </>
        );
    }
}
```

### 4. Refresh After Actions

Reload blocked users list after blocking/unblocking:

```javascript
async function unblockWithRefresh(userId) {
    await unblockUser(token, userId);
    await loadBlockedUsers(); // Refresh the list
}
```

---

## Common Use Cases

### 1. Block from Profile
```javascript
async function blockFromProfile(userId, username) {
    if (!confirm(`Block ${username}?`)) return;
    
    await blockUser(token, userId);
    alert('User blocked. You will no longer see their content.');
    window.location.href = '/dashboard';
}
```

### 2. Bulk Unblock
```javascript
async function unblockAll(userIds) {
    if (!confirm(`Unblock ${userIds.length} users?`)) return;
    
    for (const userId of userIds) {
        await unblockUser(token, userId);
    }
    
    alert('All users unblocked');
    await loadBlockedUsers();
}
```

### 3. Report and Block
```javascript
async function reportAndBlock(userId, reason) {
    // Report user first
    await reportUser(userId, reason);
    
    // Then block them
    await blockUser(token, userId);
    
    alert('User reported and blocked');
}
```

### 4. Search Blocked Users
```javascript
function searchBlockedUsers(query) {
    return blockedUsers.filter(user => 
        user.name.toLowerCase().includes(query.toLowerCase()) ||
        user.username.toLowerCase().includes(query.toLowerCase())
    );
}
```

---

## Security Considerations

1. **Admin Protection**: Cannot block admin users
2. **Self-Protection**: Cannot block yourself
3. **Bidirectional**: Blocking automatically unfollows both ways
4. **Privacy**: Blocked users cannot see your content
5. **Cleanup**: Friend and follow relationships are removed
6. **Permanent Until Unblocked**: Block persists until manually removed

---

## Testing

### Test Cases

```javascript
// Test 1: Get blocked users list
const blockedUsers = await getBlockedUsers(token);
console.assert(Array.isArray(blockedUsers), 'Should return array');

// Test 2: Block a user
const blockResult = await blockUser(token, 456);
console.assert(blockResult.blocked === 'blocked', 'Should block user');

// Test 3: Verify user is in blocked list
const updated = await getBlockedUsers(token);
const isInList = updated.some(u => u.user_id === '456');
console.assert(isInList, 'User should be in blocked list');

// Test 4: Check block status
const status = await checkBlockStatus(token, 456);
console.assert(status.i_blocked_them === 1, 'Should show as blocked');

// Test 5: Unblock user
const unblockResult = await unblockUser(token, 456);
console.assert(unblockResult.blocked === 'unblocked', 'Should unblock user');

// Test 6: Verify user removed from blocked list
const final = await getBlockedUsers(token);
const stillInList = final.some(u => u.user_id === '456');
console.assert(!stillInList, 'User should not be in blocked list');

// Test 7: Cannot block yourself
try {
    await blockUser(token, ownUserId);
    console.error('Should not allow blocking yourself');
} catch (error) {
    console.assert(error.response.data.errors.error_id === '8');
}

// Test 8: Cannot block admin
try {
    await blockUser(token, adminUserId);
    console.error('Should not allow blocking admin');
} catch (error) {
    console.assert(error.response.data.errors.error_id === '9');
}
```

---

## Database Schema

Blocked users are stored in the `Wo_Blocks` table:

```sql
-- Wo_Blocks table structure
CREATE TABLE Wo_Blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    time INT NOT NULL,
    INDEX (blocker_id),
    INDEX (blocked_id)
);

-- Get blocked users
SELECT u.* 
FROM Wo_Blocks b
JOIN Wo_Users u ON b.blocked_id = u.user_id
WHERE b.blocker_id = ?;

-- Check if blocked
SELECT COUNT(*) 
FROM Wo_Blocks 
WHERE blocker_id = ? AND blocked_id = ?;
```

---

## Related Endpoints

- **Friends**: `GET /api/v1/friends` (excludes blocked users)
- **Followers**: `GET /api/v1/users/{userId}/followers` (excludes blocked users)
- **Profile Data**: `GET /api/v1/profile/user-data` (includes block status)
- **Privacy Settings**: `GET /api/v1/privacy/settings`

