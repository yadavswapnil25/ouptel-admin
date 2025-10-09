# Delete Account API Documentation

This API mimics the old WoWonder API structure for account deletion and termination.

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

## ⚠️ IMPORTANT WARNING

**Account deletion is PERMANENT and IRREVERSIBLE!**

When an account is deleted:
- ❌ All posts and content are deleted
- ❌ All comments and reactions are deleted
- ❌ All followers and following relationships are removed
- ❌ All friend connections are removed
- ❌ All pages and groups owned are deleted
- ❌ All saved posts and bookmarks are deleted
- ❌ All notifications are deleted
- ❌ All sessions are terminated
- ❌ All uploaded files (avatar, cover) are deleted
- ❌ The account cannot be recovered

**Please download your data before deletion!**

---

## Overview

The Delete Account API provides two approaches:

1. **Immediate Deletion** - Deletes account immediately (matches old API)
2. **Scheduled Deletion** - Schedules deletion with 30-day grace period (recommended)

---

## 1. Immediate Account Deletion

Permanently deletes the user account immediately (mimics WoWonder delete-user.php).

### Endpoint
```http
POST /api/v1/account/delete
DELETE /api/v1/account
```

Both POST and DELETE methods are supported.

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body

```json
{
    "password": "user_password",
    "confirmation": "DELETE"
}
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `password` | string | Yes | User's current password for verification |
| `confirmation` | string | No | Must be "DELETE" (case-insensitive) for extra safety |

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "User account successfully deleted."
}
```

**After successful deletion:**
- ✅ Account is permanently deleted
- ✅ All data is removed from database
- ✅ All files are deleted from storage
- ✅ Session is terminated
- ✅ User is logged out

---

## 2. Request Account Deletion (with Grace Period)

Schedules account deletion with a 30-day grace period (recommended approach).

### Endpoint
```http
POST /api/v1/account/delete-request
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body

```json
{
    "password": "user_password",
    "reason": "Optional reason for leaving"
}
```

### Request Parameters

| Parameter | Type | Required | Max Length | Description |
|-----------|------|----------|------------|-------------|
| `password` | string | Yes | - | User's current password |
| `reason` | string | No | 1000 | Optional reason for account deletion |

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Account deletion requested. Your account will be deleted in 30 days. You can cancel this request by logging in again.",
    "deletion_date": "2024-02-05 10:30:00"
}
```

### Grace Period Features

- ⏰ **30-Day Wait**: Account deletion is scheduled 30 days from request
- 🔄 **Reversible**: User can cancel by logging in again
- 🔒 **Deactivated**: Account is marked inactive during grace period
- 🚪 **Logged Out**: All sessions are terminated immediately
- 📧 **Notification**: User should receive confirmation email (if implemented)

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

### 422 Validation Error - Incorrect Password
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "8",
        "error_text": "Password is incorrect. Account deletion cancelled."
    }
}
```

### 403 Forbidden - Cannot Delete Admin
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "9",
        "error_text": "Cannot delete admin accounts."
    }
}
```

### 422 Validation Error - Missing Password
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "The password field is required."
    ]
}
```

---

## Example Usage

### cURL Examples

#### Immediate Deletion
```bash
curl -X POST "http://localhost/api/v1/account/delete" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "password": "user_password",
    "confirmation": "DELETE"
  }'
```

#### Request Deletion (30-day grace period)
```bash
curl -X POST "http://localhost/api/v1/account/delete-request" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "password": "user_password",
    "reason": "No longer need the account"
  }'
```

#### Alternative DELETE method
```bash
curl -X DELETE "http://localhost/api/v1/account" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "password": "user_password",
    "confirmation": "DELETE"
  }'
```

---

## JavaScript/TypeScript Examples

### Using Fetch API

```javascript
// Immediate Account Deletion
async function deleteAccount(token, password, confirmation = 'DELETE') {
    const response = await fetch('http://localhost/api/v1/account/delete', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            password: password,
            confirmation: confirmation
        })
    });
    
    const data = await response.json();
    return data;
}

// Request Account Deletion (30-day grace period)
async function requestAccountDeletion(token, password, reason = '') {
    const response = await fetch('http://localhost/api/v1/account/delete-request', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            password: password,
            reason: reason
        })
    });
    
    const data = await response.json();
    return data;
}

// Usage with confirmation flow
async function deleteAccountFlow() {
    const token = localStorage.getItem('session_token');
    
    // Step 1: Show warning
    const confirmed = confirm(
        '⚠️ WARNING: This action is PERMANENT and IRREVERSIBLE!\n\n' +
        'All your data will be deleted:\n' +
        '• Posts and content\n' +
        '• Photos and videos\n' +
        '• Friends and followers\n' +
        '• Messages and notifications\n\n' +
        'Are you sure you want to continue?'
    );
    
    if (!confirmed) return;
    
    // Step 2: Get password
    const password = prompt('Enter your password to confirm deletion:');
    if (!password) return;
    
    // Step 3: Final confirmation
    const finalConfirm = prompt(
        'Type "DELETE" to permanently delete your account:'
    );
    
    if (finalConfirm !== 'DELETE') {
        alert('Account deletion cancelled');
        return;
    }
    
    // Step 4: Delete account
    try {
        const result = await deleteAccount(token, password, 'DELETE');
        
        if (result.api_status === '200') {
            alert('Your account has been deleted. Goodbye!');
            
            // Clear local storage and redirect
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = '/';
        }
    } catch (error) {
        if (error.response?.data?.errors?.error_id === '8') {
            alert('Incorrect password. Account deletion cancelled.');
        } else {
            alert('Failed to delete account. Please try again or contact support.');
        }
    }
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

// Delete Account Immediately
async function deleteAccount(password, confirmation = 'DELETE') {
    try {
        const response = await api.post('/account/delete', {
            password,
            confirmation
        });
        return response.data;
    } catch (error) {
        console.error('Error deleting account:', error.response?.data);
        throw error;
    }
}

// Request Account Deletion (with grace period)
async function requestAccountDeletion(password, reason = '') {
    try {
        const response = await api.post('/account/delete-request', {
            password,
            reason
        });
        return response.data;
    } catch (error) {
        console.error('Error requesting deletion:', error.response?.data);
        throw error;
    }
}
```

---

## React Example with Confirmation Flow

```jsx
import { useState } from 'react';
import axios from 'axios';

function DeleteAccount() {
    const [step, setStep] = useState(1);
    const [password, setPassword] = useState('');
    const [reason, setReason] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState([]);

    const handleRequestDeletion = async () => {
        if (!password) {
            alert('Please enter your password');
            return;
        }

        setLoading(true);
        setErrors([]);

        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.post(
                'http://localhost/api/v1/account/delete-request',
                { password, reason },
                { headers: { 'Authorization': `Bearer ${token}` } }
            );

            if (response.data.api_status === '200') {
                alert(
                    'Account deletion requested.\n\n' +
                    `Your account will be deleted on ${response.data.deletion_date}\n\n` +
                    'You can cancel this by logging in again within 30 days.'
                );
                
                // Log out
                localStorage.clear();
                window.location.href = '/';
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(Array.isArray(error.response.data.errors) 
                    ? error.response.data.errors 
                    : [error.response.data.errors.error_text]);
            } else {
                setErrors(['Failed to request account deletion']);
            }
        } finally {
            setLoading(false);
        }
    };

    const handleImmediateDeletion = async () => {
        if (confirmation !== 'DELETE') {
            alert('Please type "DELETE" to confirm');
            return;
        }

        if (!password) {
            alert('Please enter your password');
            return;
        }

        setLoading(true);
        setErrors([]);

        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.post(
                'http://localhost/api/v1/account/delete',
                { password, confirmation: 'DELETE' },
                { headers: { 'Authorization': `Bearer ${token}` } }
            );

            if (response.data.api_status === '200') {
                alert('Your account has been permanently deleted. Goodbye!');
                
                // Clear everything and redirect
                localStorage.clear();
                sessionStorage.clear();
                window.location.href = '/';
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(Array.isArray(error.response.data.errors) 
                    ? error.response.data.errors 
                    : [error.response.data.errors.error_text]);
            } else {
                setErrors(['Failed to delete account']);
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="delete-account">
            <h2>⚠️ Delete Account</h2>
            
            <div className="danger-zone">
                <div className="warning-box">
                    <h3>🚨 Warning: This Action is Permanent!</h3>
                    <p>Deleting your account will:</p>
                    <ul>
                        <li>❌ Permanently delete all your posts and content</li>
                        <li>❌ Remove all your photos and videos</li>
                        <li>❌ Delete all your comments and reactions</li>
                        <li>❌ Remove all follower/friend connections</li>
                        <li>❌ Delete all your pages and groups</li>
                        <li>❌ Remove all saved posts and bookmarks</li>
                        <li>❌ Delete all messages and conversations</li>
                    </ul>
                    <p><strong>This cannot be undone!</strong></p>
                </div>

                {errors.length > 0 && (
                    <div className="alert alert-error">
                        <ul>
                            {errors.map((error, index) => (
                                <li key={index}>{error}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {step === 1 && (
                    <div className="step-1">
                        <h3>Step 1: Choose Deletion Type</h3>
                        
                        <div className="deletion-options">
                            <div className="option recommended">
                                <h4>✅ Scheduled Deletion (Recommended)</h4>
                                <p>Schedule deletion with a 30-day grace period. You can cancel by logging in again.</p>
                                <button 
                                    onClick={() => setStep(2)}
                                    className="btn btn-warning"
                                >
                                    Schedule Deletion
                                </button>
                            </div>

                            <div className="option dangerous">
                                <h4>❌ Immediate Deletion</h4>
                                <p>Delete account immediately. This is permanent and cannot be undone!</p>
                                <button 
                                    onClick={() => setStep(3)}
                                    className="btn btn-danger"
                                >
                                    Delete Immediately
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {step === 2 && (
                    <div className="step-2">
                        <h3>Step 2: Schedule Account Deletion</h3>
                        
                        <div className="form-group">
                            <label>Password *</label>
                            <input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder="Enter your password"
                                required
                            />
                        </div>

                        <div className="form-group">
                            <label>Reason for Leaving (Optional)</label>
                            <textarea
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                placeholder="Help us improve by telling us why you're leaving"
                                rows="3"
                            />
                        </div>

                        <div className="form-actions">
                            <button 
                                onClick={handleRequestDeletion}
                                disabled={loading}
                                className="btn btn-warning"
                            >
                                {loading ? 'Processing...' : 'Request Deletion'}
                            </button>
                            <button 
                                onClick={() => setStep(1)}
                                className="btn btn-secondary"
                            >
                                Back
                            </button>
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div className="step-3">
                        <h3>Step 3: Immediate Account Deletion</h3>
                        
                        <div className="final-warning">
                            <p><strong>⚠️ FINAL WARNING</strong></p>
                            <p>This will IMMEDIATELY and PERMANENTLY delete your account!</p>
                            <p>All your data will be lost forever!</p>
                        </div>

                        <div className="form-group">
                            <label>Password *</label>
                            <input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder="Enter your password"
                                required
                            />
                        </div>

                        <div className="form-group">
                            <label>Type "DELETE" to confirm *</label>
                            <input
                                type="text"
                                value={confirmation}
                                onChange={(e) => setConfirmation(e.target.value)}
                                placeholder="DELETE"
                                required
                            />
                        </div>

                        <div className="form-actions">
                            <button 
                                onClick={handleImmediateDeletion}
                                disabled={loading || confirmation !== 'DELETE'}
                                className="btn btn-danger"
                            >
                                {loading ? 'Deleting...' : '🗑️ Delete Account Permanently'}
                            </button>
                            <button 
                                onClick={() => setStep(1)}
                                className="btn btn-secondary"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                )}

                <div className="alternatives">
                    <h3>Alternatives to Deleting</h3>
                    <p>Instead of deleting, you can:</p>
                    <ul>
                        <li>🔒 Make your profile private</li>
                        <li>🔕 Disable all notifications</li>
                        <li>🚪 Just log out and take a break</li>
                        <li>📥 Download your data first</li>
                    </ul>
                </div>
            </div>
        </div>
    );
}

export default DeleteAccount;
```

---

## Migration from Old API

### Old API → New API Mapping

| Old Endpoint | New Endpoint |
|--------------|--------------|
| `POST /v2/endpoints/delete-user.php` | `POST /api/v1/account/delete` |

### Parameter Changes

**Old API (delete-user.php):**
```json
{
    "access_token": "session_token",
    "password": "user_password"
}
```

**New API:**
```json
// Header: Authorization: Bearer session_token
{
    "password": "user_password",
    "confirmation": "DELETE"
}
```

---

## Best Practices

### 1. Multi-Step Confirmation

Always use multi-step confirmation for account deletion:

```javascript
async function safeDeleteAccount() {
    // Step 1: Initial warning
    if (!confirm('Are you sure you want to delete your account?')) return;
    
    // Step 2: Explain consequences
    if (!confirm('All your data will be permanently lost. Continue?')) return;
    
    // Step 3: Get password
    const password = prompt('Enter your password to confirm:');
    if (!password) return;
    
    // Step 4: Type "DELETE"
    const typed = prompt('Type "DELETE" to confirm (case-sensitive):');
    if (typed !== 'DELETE') {
        alert('Deletion cancelled');
        return;
    }
    
    // Step 5: Execute deletion
    await deleteAccount(token, password, 'DELETE');
}
```

### 2. Offer Data Download

Always offer to download data first:

```javascript
async function deleteWithBackup() {
    // Offer data download
    const wantBackup = confirm(
        'Do you want to download your data before deleting your account?'
    );
    
    if (wantBackup) {
        await downloadMyInformation(token, [
            'my_information', 'posts', 'followers', 'following'
        ]);
        
        alert('Please download your data, then return to delete your account.');
        return;
    }
    
    // Proceed with deletion
    await safeDeleteAccount();
}
```

### 3. Suggest Alternatives

Provide alternatives before deletion:

```javascript
function suggestAlternatives() {
    const alternatives = [
        '1. Make your profile private',
        '2. Disable all notifications',
        '3. Log out and take a break',
        '4. Deactivate temporarily instead'
    ];
    
    const message = 
        'Before deleting your account, have you considered these alternatives?\n\n' +
        alternatives.join('\n');
    
    return confirm(message + '\n\nStill want to delete?');
}
```

### 4. Grace Period (Recommended)

Use scheduled deletion with grace period:

```javascript
async function deleteAccountSafely() {
    const useGracePeriod = confirm(
        'We recommend scheduling deletion with a 30-day grace period.\n\n' +
        'This gives you time to change your mind.\n\n' +
        'Use grace period?'
    );
    
    const password = prompt('Enter your password:');
    if (!password) return;
    
    if (useGracePeriod) {
        const result = await requestAccountDeletion(token, password);
        alert(
            'Account deletion scheduled for ' + result.deletion_date + '\n\n' +
            'You can cancel by logging in again within 30 days.'
        );
    } else {
        await deleteAccount(token, password, 'DELETE');
    }
}
```

---

## Data Cleanup

### What Gets Deleted

When an account is deleted, the following data is removed:

**User Data:**
- ✅ User profile and account
- ✅ Avatar and cover photos
- ✅ User information files

**Content:**
- ✅ All posts
- ✅ All comments
- ✅ All reactions

**Relationships:**
- ✅ Followers and following
- ✅ Friends
- ✅ Blocks

**Activity:**
- ✅ Sessions
- ✅ Notifications
- ✅ Saved posts

**Pages & Groups:**
- ✅ Owned pages
- ✅ Group memberships

**Shopping:**
- ✅ Saved addresses
- ✅ Shopping cart (if any)

**Messages:**
- ✅ Sent messages
- ✅ Received messages

---

## Security Considerations

1. **Password Verification**: Must provide correct password
2. **Confirmation Required**: Extra confirmation step recommended
3. **Admin Protection**: Cannot delete admin accounts
4. **No Recovery**: Deleted accounts cannot be restored
5. **Immediate Logout**: All sessions terminated
6. **Data Removal**: All personal data permanently deleted

---

## Testing

### Test Cases

```javascript
// Test 1: Cannot delete without password
try {
    await deleteAccount(token, '');
    console.error('Should require password');
} catch (error) {
    console.assert(error.response.status === 422);
}

// Test 2: Cannot delete with wrong password
try {
    await deleteAccount(token, 'wrong_password');
    console.error('Should reject wrong password');
} catch (error) {
    console.assert(error.response.data.errors.error_id === '8');
}

// Test 3: Scheduled deletion creates grace period
const result = await requestAccountDeletion(token, 'correct_password', 'reason');
console.assert(result.api_status === '200');
console.assert(result.deletion_date, 'Should have deletion date');

// Test 4: After deletion, session is invalid
await deleteAccount(token, 'correct_password', 'DELETE');
try {
    await getProfile(token); // Should fail
    console.error('Session should be invalid after deletion');
} catch (error) {
    console.assert(error.response.status === 401);
}
```

---

## GDPR Compliance

### Right to Erasure (Article 17)

This API supports GDPR's "right to be forgotten":

- ✅ Users can request deletion of all personal data
- ✅ Deletion includes all associated data
- ✅ Grace period allows users to reconsider
- ✅ Confirmation prevents accidental deletion
- ✅ Process is documented and transparent

---

## Database Operations

Account deletion involves these operations:

```sql
-- Delete in proper order to avoid foreign key issues

DELETE FROM Wo_AppsSessions WHERE user_id = ?;
DELETE FROM Wo_Posts WHERE user_id = ?;
DELETE FROM Wo_Comments WHERE user_id = ?;
DELETE FROM Wo_PostReactions WHERE user_id = ?;
DELETE FROM Wo_Followers WHERE follower_id = ? OR following_id = ?;
DELETE FROM Wo_Friends WHERE user_id = ? OR friend_id = ?;
DELETE FROM Wo_Blocks WHERE blocker_id = ? OR blocked_id = ?;
DELETE FROM Wo_Notifications WHERE notifier_id = ? OR recipient_id = ?;
DELETE FROM Wo_UserAddress WHERE user_id = ?;
DELETE FROM Wo_Pages WHERE user_id = ?;
DELETE FROM Wo_GroupMembers WHERE user_id = ?;
DELETE FROM Wo_PageLikes WHERE user_id = ?;
DELETE FROM Wo_SavedPosts WHERE user_id = ?;
DELETE FROM Wo_Messages WHERE from_id = ? OR to_id = ?;
DELETE FROM Wo_Users WHERE user_id = ?;
```

---

## Related Endpoints

- **Download Data**: `POST /api/v1/my-information/download` (download before deleting!)
- **Logout**: `POST /api/v1/logout`
- **Deactivate Account**: Consider adding this as alternative to deletion

