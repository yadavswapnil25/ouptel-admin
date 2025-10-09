# Design Settings API Documentation

This API mimics the old WoWonder API structure for managing user profile design (avatar and cover photos).

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

The Design Settings API allows users to:
- Upload and update profile pictures (avatars)
- Upload and update cover photos
- Reset avatar to default based on gender
- Reset cover to default
- View current design settings

This is essential for:
- Profile customization
- Personal branding
- Visual identity
- User engagement

---

## 1. Get Design Settings

Retrieves the current user's avatar and cover photo information.

### Endpoint
```http
GET /api/v1/design/settings
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
    "design_settings": {
        "avatar": "http://localhost/storage/upload/photos/2024/01/avatar_123_1704441600.jpg",
        "avatar_path": "upload/photos/2024/01/avatar_123_1704441600.jpg",
        "cover": "http://localhost/storage/upload/photos/2024/01/cover_123_1704441600.jpg",
        "cover_path": "upload/photos/2024/01/cover_123_1704441600.jpg",
        "is_avatar_default": false,
        "is_cover_default": false
    }
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `avatar` | string | Full URL to avatar image |
| `avatar_path` | string | Relative path to avatar (for storage) |
| `cover` | string | Full URL to cover image |
| `cover_path` | string | Relative path to cover (for storage) |
| `is_avatar_default` | boolean | Whether avatar is the default image |
| `is_cover_default` | boolean | Whether cover is the default image |

---

## 2. Update Avatar (Profile Picture)

Uploads and updates the user's profile picture.

### Endpoint
```http
POST /api/v1/design/avatar
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: multipart/form-data
```

### Request Body (Form Data)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `image` | file | Yes | Image file (JPEG, PNG, JPG, GIF) |

### File Requirements
- **Format**: JPEG, PNG, JPG, GIF
- **Max Size**: 10 MB
- **Recommended**: Square images (e.g., 500x500, 800x800)
- **Min Recommended**: 200x200 pixels

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Avatar updated successfully",
    "avatar": "http://localhost/storage/upload/photos/2024/01/avatar_123_1704441600.jpg",
    "avatar_path": "upload/photos/2024/01/avatar_123_1704441600.jpg"
}
```

---

## 3. Update Cover Photo

Uploads and updates the user's cover photo.

### Endpoint
```http
POST /api/v1/design/cover
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: multipart/form-data
```

### Request Body (Form Data)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `image` | file | Yes | Image file (JPEG, PNG, JPG, GIF) |

### File Requirements
- **Format**: JPEG, PNG, JPG, GIF
- **Max Size**: 20 MB
- **Recommended**: Wide images (e.g., 1920x1080, 1500x500)
- **Aspect Ratio**: Typically 16:9 or 3:1

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Cover photo updated successfully",
    "cover": "http://localhost/storage/upload/photos/2024/01/cover_123_1704441600.jpg",
    "cover_path": "upload/photos/2024/01/cover_123_1704441600.jpg"
}
```

---

## 4. Reset Avatar to Default

Resets the user's avatar to the default image based on gender.

### Endpoint
```http
POST /api/v1/design/avatar/reset
DELETE /api/v1/design/avatar
```

Both methods are supported.

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Default Avatar Logic
- **Male**: `upload/photos/d-avatar.jpg` (default avatar)
- **Female**: `upload/photos/f-avatar.jpg` (female avatar)

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Avatar reset to default successfully",
    "avatar": "http://localhost/storage/upload/photos/d-avatar.jpg",
    "avatar_path": "upload/photos/d-avatar.jpg"
}
```

---

## 5. Reset Cover to Default

Resets the user's cover photo to the default image.

### Endpoint
```http
POST /api/v1/design/cover/reset
DELETE /api/v1/design/cover
```

Both methods are supported.

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
    "message": "Cover photo reset to default successfully",
    "cover": "http://localhost/storage/upload/photos/cover.jpg",
    "cover_path": "upload/photos/cover.jpg"
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

### 422 Validation Error - No Image
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "The image field is required."
    ]
}
```

### 422 Validation Error - Invalid File Type
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "The image must be an image.",
        "The image must be a file of type: jpeg, png, jpg, gif."
    ]
}
```

### 422 Validation Error - File Too Large
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "The image may not be greater than 10240 kilobytes."
    ]
}
```

---

## Example Usage

### cURL Examples

#### Get Design Settings
```bash
curl -X GET "http://localhost/api/v1/design/settings" \
  -H "Authorization: Bearer abc123session456"
```

#### Update Avatar
```bash
curl -X POST "http://localhost/api/v1/design/avatar" \
  -H "Authorization: Bearer abc123session456" \
  -F "image=@/path/to/avatar.jpg"
```

#### Update Cover
```bash
curl -X POST "http://localhost/api/v1/design/cover" \
  -H "Authorization: Bearer abc123session456" \
  -F "image=@/path/to/cover.jpg"
```

#### Reset Avatar
```bash
curl -X POST "http://localhost/api/v1/design/avatar/reset" \
  -H "Authorization: Bearer abc123session456"
```

#### Reset Cover
```bash
curl -X DELETE "http://localhost/api/v1/design/cover" \
  -H "Authorization: Bearer abc123session456"
```

---

## JavaScript/TypeScript Examples

### Using Fetch API

```javascript
// Get Design Settings
async function getDesignSettings(token) {
    const response = await fetch('http://localhost/api/v1/design/settings', {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });
    
    const data = await response.json();
    return data.design_settings;
}

// Update Avatar
async function updateAvatar(token, imageFile) {
    const formData = new FormData();
    formData.append('image', imageFile);
    
    const response = await fetch('http://localhost/api/v1/design/avatar', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`
        },
        body: formData
    });
    
    const data = await response.json();
    return data;
}

// Update Cover
async function updateCover(token, imageFile) {
    const formData = new FormData();
    formData.append('image', imageFile);
    
    const response = await fetch('http://localhost/api/v1/design/cover', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`
        },
        body: formData
    });
    
    const data = await response.json();
    return data;
}

// Reset Avatar
async function resetAvatar(token) {
    const response = await fetch('http://localhost/api/v1/design/avatar/reset', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        }
    });
    
    const data = await response.json();
    return data;
}

// Reset Cover
async function resetCover(token) {
    const response = await fetch('http://localhost/api/v1/design/cover/reset', {
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

// Get current design
const design = await getDesignSettings(token);
console.log('Current avatar:', design.avatar);
console.log('Current cover:', design.cover);

// Upload new avatar from file input
const avatarInput = document.getElementById('avatar-input');
const avatarFile = avatarInput.files[0];
const avatarResult = await updateAvatar(token, avatarFile);
console.log('New avatar:', avatarResult.avatar);

// Upload new cover
const coverInput = document.getElementById('cover-input');
const coverFile = coverInput.files[0];
const coverResult = await updateCover(token, coverFile);
console.log('New cover:', coverResult.cover);

// Reset to default
await resetAvatar(token);
await resetCover(token);
```

### Using Axios

```javascript
import axios from 'axios';

const api = axios.create({
    baseURL: 'http://localhost/api/v1'
});

// Add auth token to all requests
api.interceptors.request.use(config => {
    const token = localStorage.getItem('session_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Get Design Settings
async function getDesignSettings() {
    try {
        const response = await api.get('/design/settings');
        return response.data.design_settings;
    } catch (error) {
        console.error('Error fetching design settings:', error.response?.data);
        throw error;
    }
}

// Update Avatar
async function updateAvatar(imageFile) {
    try {
        const formData = new FormData();
        formData.append('image', imageFile);
        
        const response = await api.post('/design/avatar', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        
        return response.data;
    } catch (error) {
        console.error('Error updating avatar:', error.response?.data);
        throw error;
    }
}

// Update Cover
async function updateCover(imageFile) {
    try {
        const formData = new FormData();
        formData.append('image', imageFile);
        
        const response = await api.post('/design/cover', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        
        return response.data;
    } catch (error) {
        console.error('Error updating cover:', error.response?.data);
        throw error;
    }
}

// Reset Avatar
async function resetAvatar() {
    try {
        const response = await api.post('/design/avatar/reset');
        return response.data;
    } catch (error) {
        console.error('Error resetting avatar:', error.response?.data);
        throw error;
    }
}

// Reset Cover
async function resetCover() {
    try {
        const response = await api.post('/design/cover/reset');
        return response.data;
    } catch (error) {
        console.error('Error resetting cover:', error.response?.data);
        throw error;
    }
}
```

---

## React Example with Image Upload

```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function DesignSettings() {
    const [design, setDesign] = useState(null);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);
    const [errors, setErrors] = useState([]);

    useEffect(() => {
        loadDesignSettings();
    }, []);

    const loadDesignSettings = async () => {
        setLoading(true);
        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.get('http://localhost/api/v1/design/settings', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            
            setDesign(response.data.design_settings);
        } catch (error) {
            console.error('Failed to load design settings:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleAvatarUpload = async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        // Validate file size (10MB max)
        if (file.size > 10 * 1024 * 1024) {
            setErrors(['Avatar file size must be less than 10MB']);
            return;
        }

        // Validate file type
        if (!['image/jpeg', 'image/png', 'image/jpg', 'image/gif'].includes(file.type)) {
            setErrors(['Avatar must be a JPEG, PNG, JPG, or GIF image']);
            return;
        }

        setUploading(true);
        setErrors([]);

        try {
            const token = localStorage.getItem('session_token');
            const formData = new FormData();
            formData.append('image', file);

            const response = await axios.post(
                'http://localhost/api/v1/design/avatar',
                formData,
                {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'multipart/form-data'
                    }
                }
            );

            if (response.data.api_status === '200') {
                await loadDesignSettings();
                alert('Avatar updated successfully!');
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(Array.isArray(error.response.data.errors) 
                    ? error.response.data.errors 
                    : [error.response.data.errors.error_text]);
            } else {
                setErrors(['Failed to upload avatar']);
            }
        } finally {
            setUploading(false);
        }
    };

    const handleCoverUpload = async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        // Validate file size (20MB max)
        if (file.size > 20 * 1024 * 1024) {
            setErrors(['Cover file size must be less than 20MB']);
            return;
        }

        setUploading(true);
        setErrors([]);

        try {
            const token = localStorage.getItem('session_token');
            const formData = new FormData();
            formData.append('image', file);

            const response = await axios.post(
                'http://localhost/api/v1/design/cover',
                formData,
                {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'multipart/form-data'
                    }
                }
            );

            if (response.data.api_status === '200') {
                await loadDesignSettings();
                alert('Cover photo updated successfully!');
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(Array.isArray(error.response.data.errors) 
                    ? error.response.data.errors 
                    : [error.response.data.errors.error_text]);
            } else {
                setErrors(['Failed to upload cover']);
            }
        } finally {
            setUploading(false);
        }
    };

    const handleResetAvatar = async () => {
        if (!confirm('Reset avatar to default?')) return;

        try {
            const token = localStorage.getItem('session_token');
            await axios.post(
                'http://localhost/api/v1/design/avatar/reset',
                {},
                { headers: { 'Authorization': `Bearer ${token}` } }
            );

            await loadDesignSettings();
            alert('Avatar reset to default');
        } catch (error) {
            alert('Failed to reset avatar');
        }
    };

    const handleResetCover = async () => {
        if (!confirm('Reset cover photo to default?')) return;

        try {
            const token = localStorage.getItem('session_token');
            await axios.post(
                'http://localhost/api/v1/design/cover/reset',
                {},
                { headers: { 'Authorization': `Bearer ${token}` } }
            );

            await loadDesignSettings();
            alert('Cover photo reset to default');
        } catch (error) {
            alert('Failed to reset cover');
        }
    };

    if (loading) return <div>Loading...</div>;

    return (
        <div className="design-settings">
            <h2>Profile Design</h2>
            <p>Customize your profile appearance</p>

            {errors.length > 0 && (
                <div className="alert alert-error">
                    <ul>
                        {errors.map((error, index) => (
                            <li key={index}>{error}</li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="avatar-section">
                <h3>Profile Picture</h3>
                {design && (
                    <img 
                        src={design.avatar} 
                        alt="Avatar" 
                        className="avatar-preview"
                    />
                )}
                
                <div className="avatar-actions">
                    <label className="btn btn-primary">
                        {uploading ? 'Uploading...' : 'Upload New Avatar'}
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/jpg,image/gif"
                            onChange={handleAvatarUpload}
                            disabled={uploading}
                            style={{ display: 'none' }}
                        />
                    </label>
                    
                    {design && !design.is_avatar_default && (
                        <button
                            onClick={handleResetAvatar}
                            className="btn btn-secondary"
                        >
                            Reset to Default
                        </button>
                    )}
                </div>
                <small>Max size: 10MB | Format: JPEG, PNG, JPG, GIF</small>
            </div>

            <div className="cover-section">
                <h3>Cover Photo</h3>
                {design && (
                    <img 
                        src={design.cover} 
                        alt="Cover" 
                        className="cover-preview"
                    />
                )}
                
                <div className="cover-actions">
                    <label className="btn btn-primary">
                        {uploading ? 'Uploading...' : 'Upload New Cover'}
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/jpg,image/gif"
                            onChange={handleCoverUpload}
                            disabled={uploading}
                            style={{ display: 'none' }}
                        />
                    </label>
                    
                    {design && !design.is_cover_default && (
                        <button
                            onClick={handleResetCover}
                            className="btn btn-secondary"
                        >
                            Reset to Default
                        </button>
                    )}
                </div>
                <small>Max size: 20MB | Format: JPEG, PNG, JPG, GIF</small>
            </div>
        </div>
    );
}

export default DesignSettings;
```

---

## Migration from Old API

### Old API → New API Mapping

| Old Endpoint | New Endpoint |
|--------------|--------------|
| `POST /phone/update_profile_picture.php?type=update_profile_picture` | `POST /api/v1/design/avatar` |
| `POST /phone/update_profile_picture.php` with `image_type=cover` | `POST /api/v1/design/cover` |
| `POST /v2/endpoints/reset_avatar.php` with `type=user` | `POST /api/v1/design/avatar/reset` |

### Parameter Changes

**Old API (update_profile_picture.php):**
```
POST /phone/update_profile_picture.php?type=update_profile_picture
FormData:
  - user_id: 123
  - s: session_token
  - image: [file]
  - image_type: "avatar" or "cover"
```

**New API:**
```
POST /api/v1/design/avatar  (for avatar)
POST /api/v1/design/cover   (for cover)
Header: Authorization: Bearer session_token
FormData:
  - image: [file]
```

### Key Differences

1. **Authentication**: Moved from POST parameters to Authorization header
2. **Separate Endpoints**: Avatar and cover now have separate endpoints
3. **Image Type**: No longer needed as parameter (determined by endpoint)
4. **User ID**: Automatically extracted from session token
5. **Dedicated Reset**: Separate endpoints for resetting to default

---

## Image Best Practices

### Avatar (Profile Picture)
- **Recommended Size**: 500x500 to 800x800 pixels
- **Aspect Ratio**: 1:1 (square)
- **File Size**: Under 2MB for optimal loading
- **Content**: Clear face photo or logo
- **Format**: JPEG or PNG

### Cover Photo
- **Recommended Size**: 1920x1080 or 1500x500 pixels
- **Aspect Ratio**: 16:9 or 3:1 (wide)
- **File Size**: Under 5MB for optimal loading
- **Content**: Banner, landscape, or brand image
- **Format**: JPEG or PNG

### Image Optimization

```javascript
// Compress image before upload
async function compressImage(file, maxWidth, maxHeight, quality = 0.8) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;

                if (width > maxWidth) {
                    height = (height * maxWidth) / width;
                    width = maxWidth;
                }
                if (height > maxHeight) {
                    width = (width * maxHeight) / height;
                    height = maxHeight;
                }

                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(
                    (blob) => {
                        resolve(new File([blob], file.name, {
                            type: file.type,
                            lastModified: Date.now()
                        }));
                    },
                    file.type,
                    quality
                );
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

// Usage
const avatarFile = document.getElementById('avatar-input').files[0];
const compressed = await compressImage(avatarFile, 800, 800, 0.85);
await updateAvatar(token, compressed);
```

---

## Image Preview Before Upload

```javascript
function previewImage(file, previewElementId) {
    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById(previewElementId).src = e.target.result;
    };
    reader.readAsDataURL(file);
}

// Usage
const avatarInput = document.getElementById('avatar-input');
avatarInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        previewImage(file, 'avatar-preview');
    }
});
```

---

## Security Considerations

1. **File Type Validation**: Only images are accepted (JPEG, PNG, JPG, GIF)
2. **File Size Limits**: 10MB for avatars, 20MB for covers
3. **User-Only Access**: Users can only update their own images
4. **Automatic Cleanup**: Old images are deleted when new ones are uploaded
5. **Secure Storage**: Images stored in protected directories

---

## Testing

### Test Cases

```javascript
// Test 1: Get design settings
const design = await getDesignSettings(token);
console.assert(design.avatar, 'Should have avatar');
console.assert(design.cover, 'Should have cover');

// Test 2: Upload avatar
const avatarFile = new File(['test'], 'avatar.jpg', { type: 'image/jpeg' });
const avatarResult = await updateAvatar(token, avatarFile);
console.assert(avatarResult.api_status === '200', 'Should upload avatar');

// Test 3: Upload cover
const coverFile = new File(['test'], 'cover.jpg', { type: 'image/jpeg' });
const coverResult = await updateCover(token, coverFile);
console.assert(coverResult.api_status === '200', 'Should upload cover');

// Test 4: Reset avatar
const resetAvatarResult = await resetAvatar(token);
console.assert(resetAvatarResult.api_status === '200', 'Should reset avatar');
const updatedDesign = await getDesignSettings(token);
console.assert(updatedDesign.is_avatar_default === true, 'Avatar should be default');

// Test 5: Invalid file type
try {
    const textFile = new File(['test'], 'test.txt', { type: 'text/plain' });
    await updateAvatar(token, textFile);
    console.error('Should reject non-image files');
} catch (error) {
    console.assert(error.response.status === 422, 'Should return validation error');
}
```

---

## Common Use Cases

### 1. Complete Profile Setup
```javascript
async function completeProfile(avatarFile, coverFile) {
    await updateAvatar(token, avatarFile);
    await updateCover(token, coverFile);
    alert('Profile setup complete!');
}
```

### 2. Temporary Profile Change
```javascript
async function changeForEvent(eventAvatar) {
    // Save current avatar
    const current = await getDesignSettings(token);
    const originalAvatar = current.avatar;
    
    // Change to event avatar
    await updateAvatar(token, eventAvatar);
    
    // Return function to restore
    return async () => {
        await resetAvatar(token);
        // Or upload original back
    };
}
```

### 3. Bulk Image Management
```javascript
async function updateAllImages(avatar, cover) {
    const results = await Promise.all([
        updateAvatar(token, avatar),
        updateCover(token, cover)
    ]);
    
    return results.every(r => r.api_status === '200');
}
```

---

## Database Schema

Design settings are stored in the `Wo_Users` table:

```sql
SELECT 
    avatar,
    avatar_org,
    cover,
    cover_org,
    gender
FROM Wo_Users
WHERE user_id = ?;
```

---

## Related Endpoints

- **Profile Data**: `GET /api/v1/profile/user-data` (returns avatar and cover)
- **Profile Settings**: `POST /api/v1/settings/update-user-data`
- **Social Links**: `GET /api/v1/social-links`

