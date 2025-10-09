# Profile Picture & Cover Photo API - Quick Start Guide

This guide shows you how to use the Profile Picture and Cover Photo APIs.

## 🚀 Quick Reference

### Base URL
```
http://localhost/api/v1
```

### Authentication
All requests require Bearer token:
```
Authorization: Bearer {your_session_token}
```

---

## 📸 Profile Picture (Avatar) APIs

### 1. Upload/Update Avatar
```http
POST /api/v1/design/avatar
Content-Type: multipart/form-data
Authorization: Bearer {token}

FormData:
  - image: [file]
```

**Example with cURL:**
```bash
curl -X POST "http://localhost/api/v1/design/avatar" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "image=@/path/to/profile-picture.jpg"
```

**Response:**
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Avatar updated successfully",
    "avatar": "http://localhost/storage/upload/photos/2024/01/avatar_123.jpg",
    "avatar_path": "upload/photos/2024/01/avatar_123.jpg"
}
```

### 2. Reset Avatar to Default
```http
POST /api/v1/design/avatar/reset
Authorization: Bearer {token}
```

**Example with cURL:**
```bash
curl -X POST "http://localhost/api/v1/design/avatar/reset" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**
```json
{
    "api_status": "200",
    "api_text": "success",
    "message": "Avatar reset to default successfully",
    "avatar": "http://localhost/storage/upload/photos/d-avatar.jpg"
}
```

---

## 🖼️ Cover Photo APIs

### 1. Upload/Update Cover
```http
POST /api/v1/design/cover
Content-Type: multipart/form-data
Authorization: Bearer {token}

FormData:
  - image: [file]
```

**Example with cURL:**
```bash
curl -X POST "http://localhost/api/v1/design/cover" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "image=@/path/to/cover-photo.jpg"
```

**Response:**
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Cover photo updated successfully",
    "cover": "http://localhost/storage/upload/photos/2024/01/cover_123.jpg",
    "cover_path": "upload/photos/2024/01/cover_123.jpg"
}
```

### 2. Reset Cover to Default
```http
POST /api/v1/design/cover/reset
Authorization: Bearer {token}
```

**Example with cURL:**
```bash
curl -X POST "http://localhost/api/v1/design/cover/reset" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 📊 Get Current Design Settings

```http
GET /api/v1/design/settings
Authorization: Bearer {token}
```

**Example with cURL:**
```bash
curl -X GET "http://localhost/api/v1/design/settings" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "design_settings": {
        "avatar": "http://localhost/storage/upload/photos/2024/01/avatar_123.jpg",
        "avatar_path": "upload/photos/2024/01/avatar_123.jpg",
        "cover": "http://localhost/storage/upload/photos/2024/01/cover_123.jpg",
        "cover_path": "upload/photos/2024/01/cover_123.jpg",
        "is_avatar_default": false,
        "is_cover_default": false
    }
}
```

---

## 💻 JavaScript Examples

### Upload Avatar
```javascript
async function uploadAvatar(token, imageFile) {
    const formData = new FormData();
    formData.append('image', imageFile);
    
    const response = await fetch('http://localhost/api/v1/design/avatar', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`
        },
        body: formData
    });
    
    return await response.json();
}

// Usage
const avatarInput = document.getElementById('avatar-input');
const file = avatarInput.files[0];
const result = await uploadAvatar('YOUR_TOKEN', file);
console.log(result.message); // "Avatar updated successfully"
console.log(result.avatar);  // Full URL to new avatar
```

### Upload Cover
```javascript
async function uploadCover(token, imageFile) {
    const formData = new FormData();
    formData.append('image', imageFile);
    
    const response = await fetch('http://localhost/api/v1/design/cover', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`
        },
        body: formData
    });
    
    return await response.json();
}

// Usage
const coverInput = document.getElementById('cover-input');
const file = coverInput.files[0];
const result = await uploadCover('YOUR_TOKEN', file);
console.log(result.message); // "Cover photo updated successfully"
console.log(result.cover);   // Full URL to new cover
```

---

## 📱 React Component Example

```jsx
import { useState } from 'react';
import axios from 'axios';

function ProfileImageUploader() {
    const [avatarPreview, setAvatarPreview] = useState(null);
    const [coverPreview, setCoverPreview] = useState(null);
    const [uploading, setUploading] = useState(false);

    const handleAvatarChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            // Preview
            const reader = new FileReader();
            reader.onloadend = () => setAvatarPreview(reader.result);
            reader.readAsDataURL(file);
            
            // Upload
            uploadAvatar(file);
        }
    };

    const handleCoverChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            // Preview
            const reader = new FileReader();
            reader.onloadend = () => setCoverPreview(reader.result);
            reader.readAsDataURL(file);
            
            // Upload
            uploadCover(file);
        }
    };

    const uploadAvatar = async (file) => {
        setUploading(true);
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
                alert('Avatar updated successfully!');
            }
        } catch (error) {
            alert('Failed to upload avatar: ' + error.response?.data?.errors?.join(', '));
        } finally {
            setUploading(false);
        }
    };

    const uploadCover = async (file) => {
        setUploading(true);
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
                alert('Cover photo updated successfully!');
            }
        } catch (error) {
            alert('Failed to upload cover: ' + error.response?.data?.errors?.join(', '));
        } finally {
            setUploading(false);
        }
    };

    const handleResetAvatar = async () => {
        try {
            const token = localStorage.getItem('session_token');
            await axios.post(
                'http://localhost/api/v1/design/avatar/reset',
                {},
                { headers: { 'Authorization': `Bearer ${token}` } }
            );
            
            alert('Avatar reset to default');
            window.location.reload();
        } catch (error) {
            alert('Failed to reset avatar');
        }
    };

    const handleResetCover = async () => {
        try {
            const token = localStorage.getItem('session_token');
            await axios.post(
                'http://localhost/api/v1/design/cover/reset',
                {},
                { headers: { 'Authorization': `Bearer ${token}` } }
            );
            
            alert('Cover photo reset to default');
            window.location.reload();
        } catch (error) {
            alert('Failed to reset cover');
        }
    };

    return (
        <div className="profile-image-uploader">
            <h2>Profile Picture & Cover Photo</h2>
            
            {/* Avatar Section */}
            <div className="avatar-section">
                <h3>Profile Picture</h3>
                <div className="image-preview">
                    {avatarPreview && <img src={avatarPreview} alt="Avatar Preview" />}
                </div>
                
                <label className="upload-btn">
                    {uploading ? 'Uploading...' : 'Upload Avatar'}
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/jpg,image/gif"
                        onChange={handleAvatarChange}
                        disabled={uploading}
                        style={{ display: 'none' }}
                    />
                </label>
                
                <button onClick={handleResetAvatar}>Reset to Default</button>
                <small>Max 10MB | JPEG, PNG, JPG, GIF</small>
            </div>

            {/* Cover Section */}
            <div className="cover-section">
                <h3>Cover Photo</h3>
                <div className="image-preview wide">
                    {coverPreview && <img src={coverPreview} alt="Cover Preview" />}
                </div>
                
                <label className="upload-btn">
                    {uploading ? 'Uploading...' : 'Upload Cover'}
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/jpg,image/gif"
                        onChange={handleCoverChange}
                        disabled={uploading}
                        style={{ display: 'none' }}
                    />
                </label>
                
                <button onClick={handleResetCover}>Reset to Default</button>
                <small>Max 20MB | JPEG, PNG, JPG, GIF</small>
            </div>
        </div>
    );
}

export default ProfileImageUploader;
```

---

## 📝 File Requirements

### Avatar (Profile Picture)
- ✅ **Max Size**: 10 MB
- ✅ **Formats**: JPEG, PNG, JPG, GIF
- ✅ **Recommended**: 500x500 to 800x800 pixels (square)
- ✅ **Aspect Ratio**: 1:1 (square)

### Cover Photo
- ✅ **Max Size**: 20 MB
- ✅ **Formats**: JPEG, PNG, JPG, GIF
- ✅ **Recommended**: 1920x1080 or 1500x500 pixels (wide)
- ✅ **Aspect Ratio**: 16:9 or 3:1 (wide)

---

## 🔄 API Endpoints Summary

| Endpoint | Method | Purpose | Body Type |
|----------|--------|---------|-----------|
| `/design/settings` | GET | Get current avatar & cover | - |
| `/design/avatar` | POST | Upload new avatar | multipart/form-data |
| `/design/cover` | POST | Upload new cover | multipart/form-data |
| `/design/avatar/reset` | POST | Reset avatar to default | JSON |
| `/design/cover/reset` | POST | Reset cover to default | JSON |

---

## ⚡ Quick Test with Postman

### 1. Upload Avatar
- **Method**: POST
- **URL**: `http://localhost/api/v1/design/avatar`
- **Headers**: 
  - `Authorization: Bearer YOUR_TOKEN`
- **Body**: 
  - Type: `form-data`
  - Key: `image`
  - Type: `File`
  - Value: Select your image file

### 2. Upload Cover
- **Method**: POST
- **URL**: `http://localhost/api/v1/design/cover`
- **Headers**: 
  - `Authorization: Bearer YOUR_TOKEN`
- **Body**: 
  - Type: `form-data`
  - Key: `image`
  - Type: `File`
  - Value: Select your image file

### 3. Get Current Images
- **Method**: GET
- **URL**: `http://localhost/api/v1/design/settings`
- **Headers**: 
  - `Authorization: Bearer YOUR_TOKEN`

---

## 🎯 Common Scenarios

### Scenario 1: User Uploads Profile Picture
```javascript
// HTML
<input type="file" id="avatar" accept="image/*">
<button onclick="uploadAvatar()">Upload</button>

// JavaScript
async function uploadAvatar() {
    const token = localStorage.getItem('session_token');
    const file = document.getElementById('avatar').files[0];
    
    if (!file) {
        alert('Please select a file');
        return;
    }
    
    const formData = new FormData();
    formData.append('image', file);
    
    const response = await fetch('http://localhost/api/v1/design/avatar', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: formData
    });
    
    const data = await response.json();
    
    if (data.api_status === '200') {
        alert('Profile picture updated!');
        document.getElementById('profile-img').src = data.avatar;
    } else {
        alert('Error: ' + data.errors.join(', '));
    }
}
```

### Scenario 2: User Uploads Cover Photo
```javascript
async function uploadCover() {
    const token = localStorage.getItem('session_token');
    const file = document.getElementById('cover').files[0];
    
    if (!file) {
        alert('Please select a file');
        return;
    }
    
    const formData = new FormData();
    formData.append('image', file);
    
    const response = await fetch('http://localhost/api/v1/design/cover', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: formData
    });
    
    const data = await response.json();
    
    if (data.api_status === '200') {
        alert('Cover photo updated!');
        document.getElementById('cover-img').src = data.cover;
    } else {
        alert('Error: ' + data.errors.join(', '));
    }
}
```

### Scenario 3: Complete Profile Setup
```javascript
async function setupProfile(avatarFile, coverFile) {
    const token = localStorage.getItem('session_token');
    
    // Upload avatar
    const avatarForm = new FormData();
    avatarForm.append('image', avatarFile);
    await fetch('http://localhost/api/v1/design/avatar', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: avatarForm
    });
    
    // Upload cover
    const coverForm = new FormData();
    coverForm.append('image', coverFile);
    await fetch('http://localhost/api/v1/design/cover', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: coverForm
    });
    
    alert('Profile setup complete!');
}
```

---

## 🔒 Security Features

1. ✅ **File Type Validation**: Only images allowed (JPEG, PNG, JPG, GIF)
2. ✅ **Size Limits**: 10MB for avatar, 20MB for cover
3. ✅ **User Verification**: Only authenticated users can upload
4. ✅ **Auto-Cleanup**: Old images are automatically deleted
5. ✅ **Secure Storage**: Images stored in protected directories

---

## ❌ Common Errors & Solutions

### Error: "The image field is required"
**Solution**: Make sure you're sending a file with the key name `image`

### Error: "The image must be an image"
**Solution**: Only image files are accepted. Check the file type.

### Error: "The image may not be greater than X kilobytes"
**Solution**: Compress your image or choose a smaller file.

### Error: "Session id is wrong"
**Solution**: Your session token expired. Login again.

---

## 🎨 Image Optimization Tips

### Before Uploading
```javascript
// Compress image before upload
async function compressAndUpload(file) {
    // Create canvas
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const img = new Image();
    
    return new Promise((resolve) => {
        img.onload = () => {
            // Set max dimensions
            const maxWidth = 800;
            const maxHeight = 800;
            let width = img.width;
            let height = img.height;
            
            if (width > maxWidth || height > maxHeight) {
                if (width > height) {
                    height = (height * maxWidth) / width;
                    width = maxWidth;
                } else {
                    width = (width * maxHeight) / height;
                    height = maxHeight;
                }
            }
            
            canvas.width = width;
            canvas.height = height;
            ctx.drawImage(img, 0, 0, width, height);
            
            canvas.toBlob(async (blob) => {
                const compressedFile = new File([blob], file.name, {
                    type: file.type,
                    lastModified: Date.now()
                });
                
                const result = await uploadAvatar(token, compressedFile);
                resolve(result);
            }, file.type, 0.85);
        };
        
        const reader = new FileReader();
        reader.onload = (e) => img.src = e.target.result;
        reader.readAsDataURL(file);
    });
}
```

---

## 📚 Complete Workflow Example

```javascript
class ProfileImageManager {
    constructor(token) {
        this.token = token;
        this.baseURL = 'http://localhost/api/v1';
    }

    async getCurrentImages() {
        const response = await fetch(`${this.baseURL}/design/settings`, {
            headers: { 'Authorization': `Bearer ${this.token}` }
        });
        const data = await response.json();
        return data.design_settings;
    }

    async uploadAvatar(file) {
        // Validate
        if (!this.validateFile(file, 10)) {
            throw new Error('Invalid avatar file');
        }

        // Upload
        const formData = new FormData();
        formData.append('image', file);

        const response = await fetch(`${this.baseURL}/design/avatar`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${this.token}` },
            body: formData
        });

        return await response.json();
    }

    async uploadCover(file) {
        // Validate
        if (!this.validateFile(file, 20)) {
            throw new Error('Invalid cover file');
        }

        // Upload
        const formData = new FormData();
        formData.append('image', file);

        const response = await fetch(`${this.baseURL}/design/cover`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${this.token}` },
            body: formData
        });

        return await response.json();
    }

    async resetAvatar() {
        const response = await fetch(`${this.baseURL}/design/avatar/reset`, {
            method: 'POST',
            headers: { 
                'Authorization': `Bearer ${this.token}`,
                'Content-Type': 'application/json'
            }
        });

        return await response.json();
    }

    async resetCover() {
        const response = await fetch(`${this.baseURL}/design/cover/reset`, {
            method: 'POST',
            headers: { 
                'Authorization': `Bearer ${this.token}`,
                'Content-Type': 'application/json'
            }
        });

        return await response.json();
    }

    validateFile(file, maxSizeMB) {
        // Check file type
        const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            return false;
        }

        // Check file size
        const maxSize = maxSizeMB * 1024 * 1024; // Convert MB to bytes
        if (file.size > maxSize) {
            return false;
        }

        return true;
    }
}

// Usage
const manager = new ProfileImageManager('YOUR_TOKEN');

// Get current images
const current = await manager.getCurrentImages();
console.log(current.avatar, current.cover);

// Upload new images
const avatarFile = document.getElementById('avatar').files[0];
const coverFile = document.getElementById('cover').files[0];

await manager.uploadAvatar(avatarFile);
await manager.uploadCover(coverFile);

// Reset to defaults
await manager.resetAvatar();
await manager.resetCover();
```

---

## ✅ Migration Checklist

If migrating from the old API, update your code:

- [ ] Change endpoint from `/phone/update_profile_picture.php` to `/api/v1/design/avatar` or `/api/v1/design/cover`
- [ ] Move authentication from POST `s` parameter to `Authorization: Bearer` header
- [ ] Remove `user_id` parameter (auto-extracted from token)
- [ ] Remove `image_type` parameter (use separate endpoints instead)
- [ ] Use `image` as the form field name for file uploads
- [ ] Update response parsing to match new structure

---

## 📖 Additional Resources

For more detailed information, see:
- **API_DESIGN_DOCUMENTATION.md** - Complete design API documentation
- **API_PROFILE_DOCUMENTATION.md** - Profile data retrieval
- **API_SETTINGS_DOCUMENTATION.md** - General settings

---

## 🎉 Summary

The Profile Picture & Cover Photo API is **already created and ready to use**! 

**Available Endpoints:**
- ✅ `GET /api/v1/design/settings` - Get current images
- ✅ `POST /api/v1/design/avatar` - Upload profile picture
- ✅ `POST /api/v1/design/cover` - Upload cover photo
- ✅ `POST /api/v1/design/avatar/reset` - Reset avatar
- ✅ `POST /api/v1/design/cover/reset` - Reset cover

All endpoints follow the old WoWonder API structure for backward compatibility! 🚀

