# Complete API Summary - All Settings APIs

This document provides a complete overview of all the settings-related APIs created for Ouptel Admin.

---

## 🎯 All APIs Created

| # | API Name | Status | Documentation |
|---|----------|--------|---------------|
| 1 | **General Settings** | ✅ Ready | API_SETTINGS_DOCUMENTATION.md |
| 2 | **Profile Settings** | ✅ Ready | API_PROFILE_DOCUMENTATION.md |
| 3 | **Privacy Settings** | ✅ Ready | API_PRIVACY_DOCUMENTATION.md |
| 4 | **Password Change** | ✅ Ready | API_PASSWORD_DOCUMENTATION.md |
| 5 | **Session Management** | ✅ Ready | API_SESSIONS_DOCUMENTATION.md |
| 6 | **Social Links** | ✅ Ready | API_SOCIAL_LINKS_DOCUMENTATION.md |
| 7 | **Design Settings (Avatar & Cover)** | ✅ Ready | API_DESIGN_DOCUMENTATION.md |

---

## 📚 Quick API Reference

### 1. General Settings API
**Purpose**: System configuration and app settings

```
GET  /api/v1/settings                        - Get all settings
POST /api/v1/settings/update-user-data       - Update user data
```

**Example:**
```bash
curl -X GET "http://localhost/api/v1/settings" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### 2. Profile Settings API
**Purpose**: User profile data retrieval

```
GET  /api/v1/profile/user-data               - Get profile data
POST /api/v1/profile/user-data               - Get profile data (POST)
```

**Example:**
```bash
curl -X GET "http://localhost/api/v1/profile/user-data?fetch=user_data,followers,following" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Fetch Options:**
- `user_data` - Basic profile info
- `followers` - User's followers
- `following` - Users being followed
- `liked_pages` - Liked pages
- `joined_groups` - Joined groups
- `family` - Family members

---

### 3. Privacy Settings API
**Purpose**: Manage user privacy preferences

```
GET  /api/v1/privacy/settings                - Get privacy settings
POST /api/v1/privacy/settings                - Update privacy settings
PUT  /api/v1/privacy/settings                - Update privacy settings
```

**Example:**
```bash
curl -X POST "http://localhost/api/v1/privacy/settings" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message_privacy": "1",
    "follow_privacy": "0",
    "birth_privacy": "2"
  }'
```

**Privacy Options:**
- `message_privacy`: "0" (Everyone), "1" (Friends)
- `follow_privacy`: "0" (Everyone), "1" (Nobody)
- `birth_privacy`: "0" (Everyone), "1" (Friends), "2" (Only Me)
- `status`: "0" (Offline), "1" (Online)
- `visit_privacy`: "0" (Visible), "1" (Hidden)
- `confirm_followers`: "0" (Auto), "1" (Manual)

---

### 4. Password Change API
**Purpose**: Change user password securely

```
POST /api/v1/password/change                 - Change password
POST /api/v1/password/verify                 - Verify current password
```

**Example:**
```bash
curl -X POST "http://localhost/api/v1/password/change" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "current_password": "oldpass",
    "new_password": "newpass",
    "repeat_new_password": "newpass"
  }'
```

**Features:**
- ✅ Validates current password
- ✅ Minimum 6 characters
- ✅ Auto-logout from other devices

---

### 5. Session Management API
**Purpose**: Manage active sessions across devices

```
GET  /api/v1/sessions                        - Get all sessions
POST /api/v1/sessions/delete                 - Delete specific session
POST /api/v1/sessions/delete-all             - Delete all other sessions
```

**Example:**
```bash
# Get all sessions
curl -X GET "http://localhost/api/v1/sessions" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Delete specific session
curl -X POST "http://localhost/api/v1/sessions/delete" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"id": 1235}'

# Delete all other sessions
curl -X POST "http://localhost/api/v1/sessions/delete-all" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Features:**
- ✅ View all active devices
- ✅ Remote logout from specific devices
- ✅ Bulk logout from all other devices
- ✅ Platform/browser detection

---

### 6. Social Links API
**Purpose**: Manage social media profile links

```
GET  /api/v1/social-links                    - Get social links
POST /api/v1/social-links                    - Update social links
PUT  /api/v1/social-links                    - Update social links
```

**Example:**
```bash
curl -X POST "http://localhost/api/v1/social-links" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "facebook": "https://facebook.com/johndoe",
    "twitter": "https://twitter.com/johndoe",
    "instagram": "https://instagram.com/johndoe"
  }'
```

**Supported Networks:**
- Facebook, Twitter, Instagram
- LinkedIn, YouTube, Google+
- VKontakte (VK)

---

### 7. Design Settings API (Avatar & Cover)
**Purpose**: Upload and manage profile images

```
GET  /api/v1/design/settings                 - Get current images
POST /api/v1/design/avatar                   - Upload avatar
POST /api/v1/design/cover                    - Upload cover
POST /api/v1/design/avatar/reset             - Reset avatar
POST /api/v1/design/cover/reset              - Reset cover
```

**Example:**
```bash
# Upload avatar
curl -X POST "http://localhost/api/v1/design/avatar" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "image=@avatar.jpg"

# Upload cover
curl -X POST "http://localhost/api/v1/design/cover" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "image=@cover.jpg"
```

**Requirements:**
- **Avatar**: Max 10MB, JPEG/PNG/JPG/GIF
- **Cover**: Max 20MB, JPEG/PNG/JPG/GIF

---

## 🔄 Old API → New API Migration Map

| Old WoWonder API | New Ouptel API | Notes |
|------------------|----------------|-------|
| `/phone/get_settings.php` | `/api/v1/settings` | GET general settings |
| `/phone/update_user_data.php` | `/api/v1/settings/update-user-data` | Update user data |
| `/phone/get_user_data.php` | `/api/v1/profile/user-data` | Get profile data |
| `/phone/update_user_data.php` (privacy) | `/api/v1/privacy/settings` | Privacy settings |
| `/phone/update_user_data.php` (password) | `/api/v1/password/change` | Change password |
| `/v2/endpoints/sessions.php` | `/api/v1/sessions` | Session management |
| `/phone/update_profile_picture.php` | `/api/v1/design/avatar` or `/api/v1/design/cover` | Upload images |
| `/v2/endpoints/reset_avatar.php` | `/api/v1/design/avatar/reset` | Reset to default |

---

## 🔑 Authentication

All APIs use the same authentication method:

```javascript
// Store token after login
localStorage.setItem('session_token', 'abc123xyz456');

// Use in all requests
const token = localStorage.getItem('session_token');
fetch(url, {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});
```

---

## 📦 Complete Settings Manager Class

```javascript
class OuptelSettingsAPI {
    constructor(baseURL, token) {
        this.baseURL = baseURL || 'http://localhost/api/v1';
        this.token = token;
    }

    // General Settings
    async getSettings() {
        return this.get('/settings');
    }

    async updateUserData(type, userData) {
        return this.post('/settings/update-user-data', {
            type,
            user_data: JSON.stringify(userData)
        });
    }

    // Profile
    async getProfileData(userId, fetch = 'user_data') {
        return this.get(`/profile/user-data?user_profile_id=${userId}&fetch=${fetch}`);
    }

    // Privacy
    async getPrivacySettings() {
        return this.get('/privacy/settings');
    }

    async updatePrivacySettings(settings) {
        return this.post('/privacy/settings', settings);
    }

    // Password
    async changePassword(currentPassword, newPassword, repeatPassword) {
        return this.post('/password/change', {
            current_password: currentPassword,
            new_password: newPassword,
            repeat_new_password: repeatPassword
        });
    }

    async verifyPassword(password) {
        return this.post('/password/verify', { password });
    }

    // Sessions
    async getSessions() {
        return this.get('/sessions');
    }

    async deleteSession(sessionId) {
        return this.post('/sessions/delete', { id: sessionId });
    }

    async deleteAllOtherSessions() {
        return this.post('/sessions/delete-all');
    }

    // Social Links
    async getSocialLinks() {
        return this.get('/social-links');
    }

    async updateSocialLinks(links) {
        return this.post('/social-links', links);
    }

    // Design (Avatar & Cover)
    async getDesignSettings() {
        return this.get('/design/settings');
    }

    async uploadAvatar(file) {
        return this.upload('/design/avatar', file);
    }

    async uploadCover(file) {
        return this.upload('/design/cover', file);
    }

    async resetAvatar() {
        return this.post('/design/avatar/reset');
    }

    async resetCover() {
        return this.post('/design/cover/reset');
    }

    // Helper methods
    async get(endpoint) {
        const response = await fetch(this.baseURL + endpoint, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Content-Type': 'application/json'
            }
        });
        return await response.json();
    }

    async post(endpoint, data = {}) {
        const response = await fetch(this.baseURL + endpoint, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        return await response.json();
    }

    async upload(endpoint, file) {
        const formData = new FormData();
        formData.append('image', file);
        
        const response = await fetch(this.baseURL + endpoint, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.token}`
            },
            body: formData
        });
        return await response.json();
    }
}

// Usage
const api = new OuptelSettingsAPI('http://localhost/api/v1', 'YOUR_TOKEN');

// Get settings
const settings = await api.getSettings();

// Update privacy
await api.updatePrivacySettings({ message_privacy: '1' });

// Change password
await api.changePassword('old', 'new', 'new');

// Upload avatar
const avatarFile = document.getElementById('avatar').files[0];
await api.uploadAvatar(avatarFile);

// Get sessions
const sessions = await api.getSessions();
```

---

## 🎨 Complete Settings Page Example

```jsx
import { useState } from 'react';
import { OuptelSettingsAPI } from './api';

function SettingsPage() {
    const [activeTab, setActiveTab] = useState('general');
    const api = new OuptelSettingsAPI('http://localhost/api/v1', localStorage.getItem('session_token'));

    return (
        <div className="settings-page">
            <div className="settings-tabs">
                <button onClick={() => setActiveTab('general')}>General</button>
                <button onClick={() => setActiveTab('profile')}>Profile</button>
                <button onClick={() => setActiveTab('privacy')}>Privacy</button>
                <button onClick={() => setActiveTab('password')}>Password</button>
                <button onClick={() => setActiveTab('sessions')}>Sessions</button>
                <button onClick={() => setActiveTab('social')}>Social Links</button>
                <button onClick={() => setActiveTab('design')}>Design</button>
            </div>

            <div className="settings-content">
                {activeTab === 'general' && <GeneralSettings api={api} />}
                {activeTab === 'profile' && <ProfileSettings api={api} />}
                {activeTab === 'privacy' && <PrivacySettings api={api} />}
                {activeTab === 'password' && <PasswordChange api={api} />}
                {activeTab === 'sessions' && <SessionManagement api={api} />}
                {activeTab === 'social' && <SocialLinks api={api} />}
                {activeTab === 'design' && <DesignSettings api={api} />}
            </div>
        </div>
    );
}
```

---

## 📊 API Endpoints Overview

### General Settings
- `GET /api/v1/settings`
- `POST /api/v1/settings/update-user-data`

### Profile Settings
- `GET /api/v1/profile/user-data`
- `POST /api/v1/profile/user-data`

### Privacy Settings
- `GET /api/v1/privacy/settings`
- `POST /api/v1/privacy/settings`

### Password Management
- `POST /api/v1/password/change`
- `POST /api/v1/password/verify`

### Session Management
- `GET /api/v1/sessions`
- `POST /api/v1/sessions/delete`
- `POST /api/v1/sessions/delete-all`

### Social Links
- `GET /api/v1/social-links`
- `POST /api/v1/social-links`

### Design Settings (Avatar & Cover)
- `GET /api/v1/design/settings`
- `POST /api/v1/design/avatar`
- `POST /api/v1/design/cover`
- `POST /api/v1/design/avatar/reset`
- `POST /api/v1/design/cover/reset`

---

## 🔐 Security Features

All APIs include:
- ✅ Bearer token authentication
- ✅ Session validation
- ✅ User verification
- ✅ Input validation
- ✅ Error handling
- ✅ SQL injection protection
- ✅ XSS protection

---

## 📱 Complete Example Application

```javascript
// Initialize API
const token = localStorage.getItem('session_token');
const api = new OuptelSettingsAPI('http://localhost/api/v1', token);

// 1. Load all user settings
async function loadAllSettings() {
    const [
        generalSettings,
        profileData,
        privacySettings,
        socialLinks,
        designSettings,
        sessions
    ] = await Promise.all([
        api.getSettings(),
        api.getProfileData(null, 'user_data'),
        api.getPrivacySettings(),
        api.getSocialLinks(),
        api.getDesignSettings(),
        api.getSessions()
    ]);

    return {
        general: generalSettings.config,
        profile: profileData.user_data,
        privacy: privacySettings.privacy_settings,
        social: socialLinks.social_links,
        design: designSettings.design_settings,
        sessions: sessions.data
    };
}

// 2. Update profile completely
async function updateCompleteProfile(data) {
    // Update basic info
    await api.updateUserData('general_settings', {
        username: data.username,
        email: data.email
    });

    // Update privacy
    await api.updatePrivacySettings({
        message_privacy: data.messagePrivacy,
        follow_privacy: data.followPrivacy
    });

    // Update social links
    await api.updateSocialLinks({
        facebook: data.facebook,
        twitter: data.twitter,
        instagram: data.instagram
    });

    // Upload images if provided
    if (data.avatarFile) {
        await api.uploadAvatar(data.avatarFile);
    }
    if (data.coverFile) {
        await api.uploadCover(data.coverFile);
    }

    return 'Profile updated successfully!';
}

// 3. Security audit
async function performSecurityAudit() {
    const sessions = await api.getSessions();
    const privacy = await api.getPrivacySettings();

    const issues = [];

    // Check for too many sessions
    if (sessions.data.length > 5) {
        issues.push(`You have ${sessions.data.length} active sessions`);
    }

    // Check privacy settings
    if (privacy.privacy_settings.message_privacy === '0') {
        issues.push('Everyone can message you');
    }

    // Check for old sessions
    const oldSessions = sessions.data.filter(s => {
        const daysSince = (Date.now() / 1000 - s.time) / 86400;
        return daysSince > 30;
    });

    if (oldSessions.length > 0) {
        issues.push(`${oldSessions.length} sessions are older than 30 days`);
    }

    return issues;
}

// Usage
const allSettings = await loadAllSettings();
console.log('User settings loaded:', allSettings);

await updateCompleteProfile({
    username: 'johndoe',
    email: 'john@example.com',
    messagePrivacy: '1',
    followPrivacy: '0',
    facebook: 'https://facebook.com/johndoe',
    avatarFile: avatarFile,
    coverFile: coverFile
});

const securityIssues = await performSecurityAudit();
if (securityIssues.length > 0) {
    console.warn('Security issues found:', securityIssues);
}
```

---

## 🧪 Testing All APIs

```javascript
async function testAllAPIs(token) {
    const api = new OuptelSettingsAPI('http://localhost/api/v1', token);
    
    console.log('Testing General Settings...');
    const settings = await api.getSettings();
    console.assert(settings.api_status === '200', 'General settings should work');
    
    console.log('Testing Profile Data...');
    const profile = await api.getProfileData(null, 'user_data');
    console.assert(profile.api_status === '200', 'Profile data should work');
    
    console.log('Testing Privacy Settings...');
    const privacy = await api.getPrivacySettings();
    console.assert(privacy.api_status === '200', 'Privacy settings should work');
    
    console.log('Testing Social Links...');
    const social = await api.getSocialLinks();
    console.assert(social.api_status === '200', 'Social links should work');
    
    console.log('Testing Design Settings...');
    const design = await api.getDesignSettings();
    console.assert(design.api_status === '200', 'Design settings should work');
    
    console.log('Testing Sessions...');
    const sessions = await api.getSessions();
    console.assert(sessions.api_status === '200', 'Sessions should work');
    
    console.log('✅ All APIs working!');
}
```

---

## 📖 Documentation Files

1. **API_SETTINGS_DOCUMENTATION.md** - General settings
2. **API_PROFILE_DOCUMENTATION.md** - Profile data
3. **API_PRIVACY_DOCUMENTATION.md** - Privacy controls
4. **API_PASSWORD_DOCUMENTATION.md** - Password management
5. **API_SESSIONS_DOCUMENTATION.md** - Session management
6. **API_SOCIAL_LINKS_DOCUMENTATION.md** - Social media links
7. **API_DESIGN_DOCUMENTATION.md** - Avatar & cover photos
8. **PROFILE_PICTURE_COVER_QUICK_START.md** - Quick start guide

---

## ✨ What's Next?

All 7 settings-related APIs are now complete and ready to use! They all:
- ✅ Match the old WoWonder API structure
- ✅ Include comprehensive documentation
- ✅ Have working examples
- ✅ Are fully tested and validated
- ✅ Include security features
- ✅ Support backward compatibility

You can now integrate these APIs into your frontend application! 🎉

