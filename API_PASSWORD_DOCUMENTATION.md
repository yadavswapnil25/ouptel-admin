# Change Password API Documentation

This API mimics the old WoWonder API structure for changing user passwords.

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

## 1. Change Password

Changes the authenticated user's password and logs out all other sessions for security.

### Endpoint
```http
POST /api/v1/password/change
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body

```json
{
    "current_password": "oldpassword123",
    "new_password": "newpassword123",
    "repeat_new_password": "newpassword123"
}
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `current_password` | string | Yes | User's current password |
| `new_password` | string | Yes | New password (minimum 6 characters) |
| `repeat_new_password` | string | Yes | Confirmation of new password (must match) |

### Validation Rules

1. **Current Password**: Must match the user's existing password
2. **New Password**: Must be at least 6 characters long
3. **Password Confirmation**: `new_password` and `repeat_new_password` must match
4. **Not Same**: New password cannot be the same as the current password

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Password changed successfully. All other sessions have been logged out."
}
```

### Security Features

When password is changed successfully:
1. ✅ New password is securely hashed using `bcrypt`
2. ✅ All other active sessions are immediately terminated
3. ✅ Current session remains active (no need to re-login)
4. ✅ User can continue using the current session

---

## 2. Verify Current Password

Helper endpoint to verify if a password is correct (useful for UI validation).

### Endpoint
```http
POST /api/v1/password/verify
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body

```json
{
    "password": "currentpassword123"
}
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `password` | string | Yes | Password to verify |

### Success Response (200 OK)

**When password is correct:**
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "is_valid": true,
    "message": "Password is correct"
}
```

**When password is incorrect:**
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "is_valid": false,
    "message": "Password is incorrect"
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

### 422 Validation Error - Current Password Incorrect
```json
{
    "api_status": "500",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "Current password is incorrect"
    ]
}
```

### 422 Validation Error - Passwords Don't Match
```json
{
    "api_status": "500",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "New passwords do not match"
    ]
}
```

### 422 Validation Error - Password Too Short
```json
{
    "api_status": "500",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "Password must be at least 6 characters long"
    ]
}
```

### 422 Validation Error - Same as Current Password
```json
{
    "api_status": "500",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "New password cannot be the same as current password"
    ]
}
```

### 422 Validation Error - Multiple Errors
```json
{
    "api_status": "500",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "Current password is incorrect",
        "Password must be at least 6 characters long",
        "New passwords do not match"
    ]
}
```

### 422 Validation Error - Missing Fields
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "The current_password field is required.",
        "The new_password field is required.",
        "The repeat_new_password field is required."
    ]
}
```

---

## Example Usage

### cURL Examples

#### Change Password
```bash
curl -X POST "http://localhost/api/v1/password/change" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "current_password": "oldpassword123",
    "new_password": "newpassword123",
    "repeat_new_password": "newpassword123"
  }'
```

#### Verify Current Password
```bash
curl -X POST "http://localhost/api/v1/password/verify" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "password": "currentpassword123"
  }'
```

---

## JavaScript/TypeScript Examples

### Using Fetch API

```javascript
// Change Password
async function changePassword(token, currentPassword, newPassword, repeatPassword) {
    const response = await fetch('http://localhost/api/v1/password/change', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            current_password: currentPassword,
            new_password: newPassword,
            repeat_new_password: repeatPassword
        })
    });
    
    const data = await response.json();
    
    if (data.api_status === '200') {
        console.log('Password changed successfully!');
        return { success: true, message: data.message };
    } else {
        console.error('Failed to change password:', data.errors);
        return { success: false, errors: data.errors };
    }
}

// Verify Current Password
async function verifyPassword(token, password) {
    const response = await fetch('http://localhost/api/v1/password/verify', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ password })
    });
    
    const data = await response.json();
    return data.is_valid;
}

// Usage
const token = 'abc123session456';

// Verify current password first (optional)
const isValid = await verifyPassword(token, 'oldpassword123');
if (!isValid) {
    alert('Current password is incorrect');
    return;
}

// Change password
const result = await changePassword(
    token,
    'oldpassword123',
    'newpassword123',
    'newpassword123'
);

if (result.success) {
    alert('Password changed successfully! You will remain logged in.');
} else {
    alert('Failed to change password: ' + result.errors.join(', '));
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

// Change Password
async function changePassword(currentPassword, newPassword, repeatPassword) {
    try {
        const response = await api.post('/password/change', {
            current_password: currentPassword,
            new_password: newPassword,
            repeat_new_password: repeatPassword
        });
        
        return {
            success: true,
            message: response.data.message
        };
    } catch (error) {
        return {
            success: false,
            errors: error.response?.data?.errors || ['An error occurred']
        };
    }
}

// Verify Password
async function verifyPassword(password) {
    try {
        const response = await api.post('/password/verify', { password });
        return response.data.is_valid;
    } catch (error) {
        console.error('Failed to verify password:', error);
        return false;
    }
}

// Usage Example
async function handlePasswordChange(formData) {
    // Verify current password first
    const isValid = await verifyPassword(formData.currentPassword);
    
    if (!isValid) {
        showError('Current password is incorrect');
        return;
    }
    
    // Check if new passwords match
    if (formData.newPassword !== formData.confirmPassword) {
        showError('New passwords do not match');
        return;
    }
    
    // Change password
    const result = await changePassword(
        formData.currentPassword,
        formData.newPassword,
        formData.confirmPassword
    );
    
    if (result.success) {
        showSuccess(result.message);
        // Optionally redirect to dashboard
        window.location.href = '/dashboard';
    } else {
        showError(result.errors.join('\n'));
    }
}
```

---

## React Example with Form Validation

```jsx
import { useState } from 'react';
import axios from 'axios';

function ChangePasswordForm() {
    const [formData, setFormData] = useState({
        currentPassword: '',
        newPassword: '',
        repeatNewPassword: ''
    });
    const [errors, setErrors] = useState([]);
    const [loading, setLoading] = useState(false);
    const [success, setSuccess] = useState(false);

    const handleChange = (e) => {
        setFormData({
            ...formData,
            [e.target.name]: e.target.value
        });
        setErrors([]); // Clear errors on input change
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setErrors([]);
        setSuccess(false);

        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.post(
                'http://localhost/api/v1/password/change',
                {
                    current_password: formData.currentPassword,
                    new_password: formData.newPassword,
                    repeat_new_password: formData.repeatNewPassword
                },
                {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    }
                }
            );

            if (response.data.api_status === '200') {
                setSuccess(true);
                setFormData({
                    currentPassword: '',
                    newPassword: '',
                    repeatNewPassword: ''
                });
                
                // Show success message for 3 seconds
                setTimeout(() => {
                    setSuccess(false);
                }, 3000);
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(Array.isArray(error.response.data.errors) 
                    ? error.response.data.errors 
                    : [error.response.data.errors.error_text]);
            } else {
                setErrors(['Failed to change password. Please try again.']);
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="change-password-form">
            <h2>Change Password</h2>
            
            {success && (
                <div className="alert alert-success">
                    Password changed successfully! All other sessions have been logged out.
                </div>
            )}
            
            {errors.length > 0 && (
                <div className="alert alert-error">
                    <ul>
                        {errors.map((error, index) => (
                            <li key={index}>{error}</li>
                        ))}
                    </ul>
                </div>
            )}

            <form onSubmit={handleSubmit}>
                <div className="form-group">
                    <label>Current Password</label>
                    <input
                        type="password"
                        name="currentPassword"
                        value={formData.currentPassword}
                        onChange={handleChange}
                        required
                        minLength="1"
                    />
                </div>

                <div className="form-group">
                    <label>New Password</label>
                    <input
                        type="password"
                        name="newPassword"
                        value={formData.newPassword}
                        onChange={handleChange}
                        required
                        minLength="6"
                    />
                    <small>Minimum 6 characters</small>
                </div>

                <div className="form-group">
                    <label>Confirm New Password</label>
                    <input
                        type="password"
                        name="repeatNewPassword"
                        value={formData.repeatNewPassword}
                        onChange={handleChange}
                        required
                        minLength="6"
                    />
                </div>

                <button type="submit" disabled={loading}>
                    {loading ? 'Changing Password...' : 'Change Password'}
                </button>
            </form>
        </div>
    );
}

export default ChangePasswordForm;
```

---

## Migration from Old API

### Old API → New API Mapping

| Old Endpoint | New Endpoint |
|--------------|--------------|
| `POST /phone/update_user_data.php?type=update_user_data` with `type=password_settings` | `POST /api/v1/password/change` |

### Parameter Changes

**Old API (update_user_data.php):**
```json
{
    "user_id": "123",
    "s": "session_token",
    "type": "password_settings",
    "user_data": "{\"current_password\":\"old\",\"new_password\":\"new\",\"repeat_new_password\":\"new\"}"
}
```

**New API:**
```json
// Header: Authorization: Bearer session_token
{
    "current_password": "old",
    "new_password": "new",
    "repeat_new_password": "new"
}
```

### Key Differences

1. **Authentication**: Moved from POST parameters to Authorization header
2. **Data Format**: Direct JSON instead of JSON-encoded string in `user_data`
3. **Type Parameter**: Removed (now part of the endpoint URL)
4. **User ID**: Automatically extracted from session token
5. **Response Format**: Maintains backward compatibility with old API

---

## Best Practices

### 1. Client-Side Validation

Always validate input on the client side before sending to the API:

```javascript
function validatePasswordChange(current, newPass, repeat) {
    const errors = [];
    
    if (!current) {
        errors.push('Current password is required');
    }
    
    if (!newPass) {
        errors.push('New password is required');
    } else if (newPass.length < 6) {
        errors.push('New password must be at least 6 characters');
    }
    
    if (newPass !== repeat) {
        errors.push('New passwords do not match');
    }
    
    if (current === newPass) {
        errors.push('New password must be different from current password');
    }
    
    return errors;
}
```

### 2. Show Password Strength

Provide visual feedback on password strength:

```javascript
function calculatePasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 6) strength++;
    if (password.length >= 10) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z\d]/.test(password)) strength++;
    
    return strength; // 0-5
}

const strengthLabels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
```

### 3. Debounce Password Verification

If using the verify endpoint for real-time validation:

```javascript
import { debounce } from 'lodash';

const verifyPasswordDebounced = debounce(async (password) => {
    const isValid = await verifyPassword(token, password);
    updateUI(isValid);
}, 500);
```

### 4. Handle Session Logout

Inform users that other sessions will be logged out:

```javascript
const confirmPasswordChange = () => {
    return confirm(
        'Changing your password will log you out from all other devices. Continue?'
    );
};
```

### 5. Secure Password Input

Never log or store passwords in plain text:

```javascript
// DON'T DO THIS
console.log('Password:', password); // Never log passwords
localStorage.setItem('password', password); // Never store passwords

// DO THIS
// Only send passwords via secure HTTPS connections
// Never expose passwords in URLs or logs
```

---

## Security Considerations

1. **Always Use HTTPS**: Passwords must be transmitted over secure connections
2. **Session Termination**: All other sessions are automatically terminated for security
3. **Password Hashing**: Passwords are hashed using bcrypt (cost factor 10)
4. **No Password Recovery**: This API changes passwords, not recovers them
5. **Rate Limiting**: Consider implementing rate limiting to prevent brute force attacks
6. **Two-Factor Authentication**: Consider adding 2FA for additional security

---

## Common Issues & Solutions

### Issue: "Current password is incorrect"
**Solution**: User may have forgotten their password. Implement a "Forgot Password" flow.

### Issue: "New passwords do not match"
**Solution**: Ensure the confirmation field value exactly matches the new password field.

### Issue: "Password must be at least 6 characters long"
**Solution**: Enforce minimum length validation on the client side.

### Issue: "Session id is wrong"
**Solution**: Session may have expired. Redirect user to login page.

### Issue: User gets logged out after password change
**Solution**: This is intentional for security. Only the current session remains active; all other sessions are terminated.

---

## Testing

### Test Cases

```javascript
// Test 1: Successful password change
await changePassword('correctCurrent', 'newPass123', 'newPass123');
// Expected: Success

// Test 2: Wrong current password
await changePassword('wrongPassword', 'newPass123', 'newPass123');
// Expected: "Current password is incorrect"

// Test 3: Passwords don't match
await changePassword('correctCurrent', 'newPass123', 'differentPass');
// Expected: "New passwords do not match"

// Test 4: Password too short
await changePassword('correctCurrent', '123', '123');
// Expected: "Password must be at least 6 characters long"

// Test 5: Same as current password
await changePassword('samePass123', 'samePass123', 'samePass123');
// Expected: "New password cannot be the same as current password"
```

---

## Related Endpoints

- **General Settings**: `GET /api/v1/settings`
- **Update User Data**: `POST /api/v1/settings/update-user-data`
- **Privacy Settings**: `GET /api/v1/privacy/settings`
- **Profile Data**: `GET /api/v1/profile/user-data`

