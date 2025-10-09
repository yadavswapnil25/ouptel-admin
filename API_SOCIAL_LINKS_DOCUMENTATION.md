# Social Links API Documentation

This API mimics the old WoWonder API structure for managing user social media links.

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

The Social Links API allows users to:
- View their current social media profile links
- Update social media links (Facebook, Twitter, Instagram, etc.)
- Remove social media links by setting them to empty strings
- Validate URL formats before saving

Supported Social Networks:
- 📘 Facebook
- 🐦 Twitter
- 📷 Instagram
- 💼 LinkedIn
- 📺 YouTube
- ➕ Google+
- 🌐 VKontakte (VK)

---

## 1. Get Social Links

Retrieves the authenticated user's social media links.

### Endpoint
```http
GET /api/v1/social-links
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
    "social_links": {
        "facebook": "https://facebook.com/johndoe",
        "twitter": "https://twitter.com/johndoe",
        "google": "https://plus.google.com/+johndoe",
        "instagram": "https://instagram.com/johndoe",
        "linkedin": "https://linkedin.com/in/johndoe",
        "youtube": "https://youtube.com/c/johndoe",
        "vk": "https://vk.com/johndoe"
    }
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `facebook` | string | Facebook profile URL |
| `twitter` | string | Twitter profile URL |
| `google` | string | Google+ profile URL |
| `instagram` | string | Instagram profile URL |
| `linkedin` | string | LinkedIn profile URL |
| `youtube` | string | YouTube channel URL |
| `vk` | string | VKontakte (VK) profile URL |

**Note**: Empty strings are returned for links that haven't been set.

---

## 2. Update Social Links

Updates one or more social media links for the authenticated user.

### Endpoint
```http
POST /api/v1/social-links
PUT  /api/v1/social-links
```

Both POST and PUT methods are supported for flexibility.

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body

You can update one or multiple social links in a single request. Only include the fields you want to update.

```json
{
    "facebook": "https://facebook.com/johndoe",
    "twitter": "https://twitter.com/johndoe",
    "instagram": "https://instagram.com/johndoe",
    "linkedin": "https://linkedin.com/in/johndoe",
    "youtube": "https://youtube.com/c/johndoe",
    "google": "https://plus.google.com/+johndoe",
    "vk": "https://vk.com/johndoe"
}
```

### Request Parameters

All parameters are optional. Only include the social links you want to update.

| Parameter | Type | Required | Max Length | Description |
|-----------|------|----------|------------|-------------|
| `facebook` | string | No | 500 | Facebook profile URL |
| `twitter` | string | No | 500 | Twitter profile URL |
| `google` | string | No | 500 | Google+ profile URL |
| `instagram` | string | No | 500 | Instagram profile URL |
| `linkedin` | string | No | 500 | LinkedIn profile URL |
| `youtube` | string | No | 500 | YouTube channel URL |
| `vk` | string | No | 500 | VKontakte profile URL |

### URL Format Requirements

All URLs must:
- Start with `http://` or `https://`
- Be valid URLs (pass URL validation)
- Be no longer than 500 characters

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Social links updated successfully",
    "social_links": {
        "facebook": "https://facebook.com/johndoe",
        "twitter": "https://twitter.com/johndoe",
        "google": "https://plus.google.com/+johndoe",
        "instagram": "https://instagram.com/johndoe",
        "linkedin": "https://linkedin.com/in/johndoe",
        "youtube": "https://youtube.com/c/johndoe",
        "vk": "https://vk.com/johndoe"
    }
}
```

---

## Remove Social Links

To remove a social link, set it to an empty string:

```json
{
    "facebook": "",
    "twitter": ""
}
```

This will clear the Facebook and Twitter links while keeping other links unchanged.

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

### 422 Validation Error - Invalid URL
```json
{
    "api_status": "500",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "Facebook URL is invalid",
        "Twitter URL is invalid"
    ]
}
```

### 422 Validation Error - No Data to Update
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "8",
        "error_text": "No social links to update."
    }
}
```

### 422 Validation Error - URL Too Long
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "The facebook may not be greater than 500 characters."
    ]
}
```

---

## Example Usage

### cURL Examples

#### Get Social Links
```bash
curl -X GET "http://localhost/api/v1/social-links" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

#### Update Single Social Link
```bash
curl -X POST "http://localhost/api/v1/social-links" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "facebook": "https://facebook.com/johndoe"
  }'
```

#### Update Multiple Social Links
```bash
curl -X POST "http://localhost/api/v1/social-links" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "facebook": "https://facebook.com/johndoe",
    "twitter": "https://twitter.com/johndoe",
    "instagram": "https://instagram.com/johndoe"
  }'
```

#### Remove Social Links
```bash
curl -X POST "http://localhost/api/v1/social-links" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "facebook": "",
    "twitter": ""
  }'
```

#### Update All Social Links
```bash
curl -X PUT "http://localhost/api/v1/social-links" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "facebook": "https://facebook.com/johndoe",
    "twitter": "https://twitter.com/johndoe",
    "google": "https://plus.google.com/+johndoe",
    "instagram": "https://instagram.com/johndoe",
    "linkedin": "https://linkedin.com/in/johndoe",
    "youtube": "https://youtube.com/c/johndoe",
    "vk": "https://vk.com/johndoe"
  }'
```

---

## JavaScript/TypeScript Examples

### Using Fetch API

```javascript
// Get Social Links
async function getSocialLinks(token) {
    const response = await fetch('http://localhost/api/v1/social-links', {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        }
    });
    
    const data = await response.json();
    return data.social_links;
}

// Update Social Links
async function updateSocialLinks(token, links) {
    const response = await fetch('http://localhost/api/v1/social-links', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(links)
    });
    
    const data = await response.json();
    return data;
}

// Usage
const token = 'abc123session456';

// Get current links
const currentLinks = await getSocialLinks(token);
console.log('Current Facebook:', currentLinks.facebook);

// Update specific links
const result = await updateSocialLinks(token, {
    facebook: 'https://facebook.com/johndoe',
    twitter: 'https://twitter.com/johndoe'
});

if (result.api_status === '200') {
    console.log('Links updated successfully!');
} else {
    console.error('Failed to update links:', result.errors);
}

// Remove a link
await updateSocialLinks(token, {
    facebook: '' // Remove Facebook link
});
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

// Get Social Links
async function getSocialLinks() {
    try {
        const response = await api.get('/social-links');
        return response.data.social_links;
    } catch (error) {
        console.error('Error fetching social links:', error.response?.data);
        throw error;
    }
}

// Update Social Links
async function updateSocialLinks(links) {
    try {
        const response = await api.post('/social-links', links);
        return response.data;
    } catch (error) {
        console.error('Error updating social links:', error.response?.data);
        throw error;
    }
}

// Usage Example
async function manageSocialLinks() {
    // Get current links
    const links = await getSocialLinks();
    console.log('Current links:', links);
    
    // Update Facebook and Twitter
    const result = await updateSocialLinks({
        facebook: 'https://facebook.com/newprofile',
        twitter: 'https://twitter.com/newprofile'
    });
    
    console.log('Update result:', result.message);
}
```

---

## React Example with Form

```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function SocialLinksForm() {
    const [links, setLinks] = useState({
        facebook: '',
        twitter: '',
        instagram: '',
        linkedin: '',
        youtube: '',
        google: '',
        vk: ''
    });
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState([]);
    const [success, setSuccess] = useState(false);

    useEffect(() => {
        loadSocialLinks();
    }, []);

    const loadSocialLinks = async () => {
        setLoading(true);
        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.get('http://localhost/api/v1/social-links', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            
            setLinks(response.data.social_links);
        } catch (error) {
            console.error('Failed to load social links:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setLinks(prev => ({
            ...prev,
            [name]: value
        }));
        setErrors([]);
        setSuccess(false);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors([]);
        setSuccess(false);

        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.post(
                'http://localhost/api/v1/social-links',
                links,
                { headers: { 'Authorization': `Bearer ${token}` } }
            );

            if (response.data.api_status === '200') {
                setSuccess(true);
                setLinks(response.data.social_links);
                
                // Hide success message after 3 seconds
                setTimeout(() => setSuccess(false), 3000);
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(Array.isArray(error.response.data.errors) 
                    ? error.response.data.errors 
                    : [error.response.data.errors.error_text]);
            } else {
                setErrors(['Failed to update social links. Please try again.']);
            }
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <div>Loading...</div>;

    return (
        <div className="social-links-form">
            <h2>Social Media Links</h2>
            <p>Connect your social media profiles</p>

            {success && (
                <div className="alert alert-success">
                    Social links updated successfully!
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
                    <label>📘 Facebook</label>
                    <input
                        type="url"
                        name="facebook"
                        value={links.facebook}
                        onChange={handleChange}
                        placeholder="https://facebook.com/yourusername"
                    />
                </div>

                <div className="form-group">
                    <label>🐦 Twitter</label>
                    <input
                        type="url"
                        name="twitter"
                        value={links.twitter}
                        onChange={handleChange}
                        placeholder="https://twitter.com/yourusername"
                    />
                </div>

                <div className="form-group">
                    <label>📷 Instagram</label>
                    <input
                        type="url"
                        name="instagram"
                        value={links.instagram}
                        onChange={handleChange}
                        placeholder="https://instagram.com/yourusername"
                    />
                </div>

                <div className="form-group">
                    <label>💼 LinkedIn</label>
                    <input
                        type="url"
                        name="linkedin"
                        value={links.linkedin}
                        onChange={handleChange}
                        placeholder="https://linkedin.com/in/yourusername"
                    />
                </div>

                <div className="form-group">
                    <label>📺 YouTube</label>
                    <input
                        type="url"
                        name="youtube"
                        value={links.youtube}
                        onChange={handleChange}
                        placeholder="https://youtube.com/c/yourchannel"
                    />
                </div>

                <div className="form-group">
                    <label>➕ Google+</label>
                    <input
                        type="url"
                        name="google"
                        value={links.google}
                        onChange={handleChange}
                        placeholder="https://plus.google.com/+yourusername"
                    />
                </div>

                <div className="form-group">
                    <label>🌐 VKontakte</label>
                    <input
                        type="url"
                        name="vk"
                        value={links.vk}
                        onChange={handleChange}
                        placeholder="https://vk.com/yourusername"
                    />
                </div>

                <button type="submit" disabled={saving}>
                    {saving ? 'Saving...' : 'Save Social Links'}
                </button>
            </form>
        </div>
    );
}

export default SocialLinksForm;
```

---

## Migration from Old API

### Old API → New API Mapping

| Old Endpoint | New Endpoint |
|--------------|--------------|
| `POST /phone/update_user_data.php` with `type=profile_settings` | `POST /api/v1/social-links` |
| N/A (retrieved via get_user_data) | `GET /api/v1/social-links` |

### Parameter Changes

**Old API (update_user_data.php):**
```json
{
    "user_id": "123",
    "s": "session_token",
    "type": "profile_settings",
    "user_data": "{\"facebook\":\"https://facebook.com/user\",\"twitter\":\"https://twitter.com/user\"}"
}
```

**New API:**
```json
// Header: Authorization: Bearer session_token
{
    "facebook": "https://facebook.com/user",
    "twitter": "https://twitter.com/user"
}
```

### Key Differences

1. **Authentication**: Moved from POST parameters to Authorization header
2. **Data Format**: Direct JSON instead of JSON-encoded string
3. **Type Parameter**: Removed (now part of the endpoint URL)
4. **User ID**: Automatically extracted from session token
5. **Dedicated Endpoint**: Separate endpoint specifically for social links

---

## URL Validation

The API validates all URLs to ensure they are properly formatted:

### Valid URLs ✅
```
https://facebook.com/johndoe
http://twitter.com/johndoe
https://www.instagram.com/johndoe
https://linkedin.com/in/john-doe
```

### Invalid URLs ❌
```
facebook.com/johndoe          // Missing protocol
www.twitter.com/johndoe       // Missing protocol
ftp://instagram.com/user      // Wrong protocol
javascript:alert('xss')       // Security risk
```

### Client-Side Validation Example

```javascript
function validateSocialUrl(url, platform) {
    if (!url) return true; // Empty is valid (removes link)
    
    // Check if URL starts with http:// or https://
    if (!/^https?:\/\//i.test(url)) {
        return `${platform} URL must start with http:// or https://`;
    }
    
    // Basic URL validation
    try {
        new URL(url);
        return true;
    } catch {
        return `Invalid ${platform} URL format`;
    }
}

// Usage
const errors = [];
const facebookError = validateSocialUrl(formData.facebook, 'Facebook');
if (facebookError !== true) {
    errors.push(facebookError);
}
```

---

## Best Practices

### 1. Validate Before Submitting

Always validate URLs on the client side before sending to the API:

```javascript
function validateAllLinks(links) {
    const errors = [];
    const platforms = {
        facebook: 'Facebook',
        twitter: 'Twitter',
        instagram: 'Instagram',
        linkedin: 'LinkedIn',
        youtube: 'YouTube',
        google: 'Google+',
        vk: 'VKontakte'
    };
    
    for (const [key, value] of Object.entries(links)) {
        if (value && value.trim()) {
            const error = validateSocialUrl(value, platforms[key]);
            if (error !== true) {
                errors.push(error);
            }
        }
    }
    
    return errors;
}
```

### 2. Handle Empty Fields

Distinguish between "not updating" and "removing":

```javascript
// Update only Facebook (leave others unchanged)
await updateSocialLinks({ facebook: 'https://facebook.com/new' });

// Remove Facebook (set to empty string)
await updateSocialLinks({ facebook: '' });

// Update multiple, remove one
await updateSocialLinks({
    facebook: 'https://facebook.com/new',
    twitter: '', // Remove Twitter
    instagram: 'https://instagram.com/new'
});
```

### 3. Show Link Previews

Display clickable previews of social links:

```jsx
function SocialLinkPreview({ platform, url }) {
    if (!url) return null;
    
    const icons = {
        facebook: '📘',
        twitter: '🐦',
        instagram: '📷',
        linkedin: '💼',
        youtube: '📺',
        google: '➕',
        vk: '🌐'
    };
    
    return (
        <a href={url} target="_blank" rel="noopener noreferrer">
            {icons[platform]} Visit {platform}
        </a>
    );
}
```

### 4. Provide Quick Links

Offer common URL templates:

```javascript
const urlTemplates = {
    facebook: 'https://facebook.com/',
    twitter: 'https://twitter.com/',
    instagram: 'https://instagram.com/',
    linkedin: 'https://linkedin.com/in/',
    youtube: 'https://youtube.com/c/',
    google: 'https://plus.google.com/+',
    vk: 'https://vk.com/'
};

function fillTemplate(platform, username) {
    return urlTemplates[platform] + username;
}
```

---

## Common Use Cases

### 1. Complete Profile Setup
```javascript
async function completeProfile(username) {
    const links = {
        facebook: `https://facebook.com/${username}`,
        twitter: `https://twitter.com/${username}`,
        instagram: `https://instagram.com/${username}`
    };
    
    await updateSocialLinks(token, links);
}
```

### 2. Privacy Settings
```javascript
async function removeSocialLinks() {
    // Remove all social links for privacy
    const links = {
        facebook: '',
        twitter: '',
        instagram: '',
        linkedin: '',
        youtube: '',
        google: '',
        vk: ''
    };
    
    await updateSocialLinks(token, links);
}
```

### 3. Profile Migration
```javascript
async function migrateFromOldProfile(oldLinks) {
    // Migrate from old profile to new
    const newLinks = {
        facebook: oldLinks.fb_profile,
        twitter: oldLinks.twitter_handle ? `https://twitter.com/${oldLinks.twitter_handle}` : '',
        instagram: oldLinks.ig_username ? `https://instagram.com/${oldLinks.ig_username}` : ''
    };
    
    await updateSocialLinks(token, newLinks);
}
```

---

## Security Considerations

1. **URL Validation**: All URLs are validated to prevent XSS attacks
2. **Protocol Restriction**: Only `http://` and `https://` protocols are allowed
3. **Length Limits**: URLs are limited to 500 characters
4. **No JavaScript**: JavaScript URLs (`javascript:`) are blocked
5. **User-Only Access**: Users can only update their own social links

---

## Testing

### Test Cases

```javascript
// Test 1: Get social links
const links = await getSocialLinks(token);
console.assert(typeof links === 'object', 'Should return object');
console.assert('facebook' in links, 'Should have facebook field');

// Test 2: Update single link
await updateSocialLinks(token, { facebook: 'https://facebook.com/test' });
const updated = await getSocialLinks(token);
console.assert(updated.facebook === 'https://facebook.com/test', 'Should update Facebook');

// Test 3: Remove link
await updateSocialLinks(token, { facebook: '' });
const removed = await getSocialLinks(token);
console.assert(removed.facebook === '', 'Should remove Facebook link');

// Test 4: Invalid URL
try {
    await updateSocialLinks(token, { facebook: 'invalid-url' });
    console.error('Should reject invalid URL');
} catch (error) {
    console.assert(error.response.data.errors.includes('Facebook URL is invalid'));
}

// Test 5: Update multiple links
await updateSocialLinks(token, {
    facebook: 'https://facebook.com/test',
    twitter: 'https://twitter.com/test',
    instagram: 'https://instagram.com/test'
});
const multiple = await getSocialLinks(token);
console.assert(multiple.facebook && multiple.twitter && multiple.instagram, 'Should update all');
```

---

## Database Schema

Social links are stored in the `Wo_Users` table:

```sql
SELECT 
    facebook,
    twitter,
    google,
    instagram,
    linkedin,
    youtube,
    vk
FROM Wo_Users
WHERE user_id = ?;
```

---

## Related Endpoints

- **Profile Settings**: `POST /api/v1/settings/update-user-data` (includes social links)
- **Profile Data**: `GET /api/v1/profile/user-data` (returns social links)
- **General Settings**: `GET /api/v1/settings`

