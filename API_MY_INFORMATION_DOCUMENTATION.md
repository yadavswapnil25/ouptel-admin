# My Information API Documentation

This API mimics the old WoWonder API structure for retrieving and exporting comprehensive user data.

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

The My Information API allows users to:
- 📊 Retrieve all personal data stored in the platform
- 📥 Download complete data export as HTML file
- 🔐 Access account information, posts, pages, groups, and connections
- 📋 GDPR compliance - data portability right

**Data Types Available:**
- `my_information` - Account settings, sessions, blocked users, transactions, referrers
- `posts` - All user posts
- `pages` - Pages owned or managed
- `groups` - Groups joined
- `followers` - Users following you
- `following` - Users you're following
- `friends` - Friend connections

---

## 1. Get My Information (JSON)

Retrieves comprehensive user data as JSON.

### Endpoint
```http
POST /api/v1/my-information
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body

```json
{
    "data": "my_information,posts,pages,groups,followers,following,friends"
}
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `data` | string | Yes | Comma-separated list of data types to retrieve |

### Data Types

You can request one or more of the following (comma-separated):

| Type | Description | Includes |
|------|-------------|----------|
| `my_information` | Account information | Profile, sessions, blocked users, transactions, referrers |
| `posts` | User's posts | All posts with text, media, privacy settings |
| `pages` | User's pages | Pages owned or managed |
| `groups` | User's groups | Groups joined or created |
| `followers` | Followers list | Users following you |
| `following` | Following list | Users you're following |
| `friends` | Friends list | Accepted friend connections |

### Success Response (200 OK)

```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "User information retrieved successfully",
    "user_info": {
        "my_information": {
            "user_id": "123",
            "username": "johndoe",
            "email": "john@example.com",
            "name": "John Doe",
            "first_name": "John",
            "last_name": "Doe",
            "gender": "male",
            "phone_number": "+1234567890",
            "avatar": "upload/photos/avatar.jpg",
            "cover": "upload/photos/cover.jpg",
            "sessions": [
                {
                    "id": 1,
                    "user_id": 123,
                    "session_id": "abc123xyz456",
                    "platform_type": "web",
                    "time": 1704441600
                }
            ],
            "blocked_users": [
                {
                    "user_id": "456",
                    "username": "blocked_user",
                    "name": "Blocked User"
                }
            ],
            "transactions": [],
            "referrers": []
        },
        "posts": [
            {
                "post_id": 1001,
                "user_id": 123,
                "postText": "My first post!",
                "postPrivacy": "0",
                "time": 1704441600
            }
        ],
        "pages": [],
        "groups": [],
        "followers": [
            {
                "user_id": "789",
                "username": "follower1",
                "name": "Follower One",
                "avatar": "upload/photos/avatar_789.jpg"
            }
        ],
        "following": [
            {
                "user_id": "321",
                "username": "following1",
                "name": "Following One",
                "avatar": "upload/photos/avatar_321.jpg"
            }
        ],
        "friends": []
    }
}
```

---

## 2. Download My Information (HTML File)

Generates and downloads a complete HTML report of user data.

### Endpoint
```http
POST /api/v1/my-information/download
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body

```json
{
    "data": "my_information,posts,pages,groups,followers,following,friends"
}
```

### Request Parameters

Same as the JSON endpoint - specify which data types to include in the download.

### Success Response (200 OK)

```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Your information file is ready for download",
    "link": "http://localhost/storage/upload/files/2024/01/abc123_05_xyz789_info.html",
    "file_path": "upload/files/2024/01/abc123_05_xyz789_info.html"
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `message` | string | Success message |
| `link` | string | Full URL to download the HTML file |
| `file_path` | string | Relative path to the file |

### Generated File

The generated HTML file includes:
- ✅ Styled, professional-looking report
- ✅ All requested data in organized sections
- ✅ Tables for lists (sessions, posts, followers, etc.)
- ✅ Timestamp of generation
- ✅ User identification
- ✅ Print-friendly format

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

### 422 Validation Error - Missing Data Parameter
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "The data field is required."
    ]
}
```

---

## Example Usage

### cURL Examples

#### Get My Information (JSON)
```bash
curl -X POST "http://localhost/api/v1/my-information" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "data": "my_information,posts,followers,following"
  }'
```

#### Download My Information (HTML)
```bash
curl -X POST "http://localhost/api/v1/my-information/download" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "data": "my_information,posts,pages,groups,followers,following,friends"
  }'
```

#### Get Only Account Information
```bash
curl -X POST "http://localhost/api/v1/my-information" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "data": "my_information"
  }'
```

---

## JavaScript/TypeScript Examples

### Using Fetch API

```javascript
// Get My Information (JSON)
async function getMyInformation(token, dataTypes) {
    const response = await fetch('http://localhost/api/v1/my-information', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            data: dataTypes.join(',')
        })
    });
    
    const data = await response.json();
    return data.user_info;
}

// Download My Information (HTML)
async function downloadMyInformation(token, dataTypes) {
    const response = await fetch('http://localhost/api/v1/my-information/download', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            data: dataTypes.join(',')
        })
    });
    
    const data = await response.json();
    
    if (data.api_status === '200') {
        // Open download link in new tab
        window.open(data.link, '_blank');
        return data.link;
    }
    
    throw new Error('Failed to generate download');
}

// Usage
const token = 'abc123session456';

// Get comprehensive information
const info = await getMyInformation(token, [
    'my_information',
    'posts',
    'pages',
    'groups',
    'followers',
    'following'
]);

console.log('Total posts:', info.posts?.length || 0);
console.log('Total followers:', info.followers?.length || 0);
console.log('Total following:', info.following?.length || 0);

// Download complete data export
const downloadLink = await downloadMyInformation(token, [
    'my_information',
    'posts',
    'pages',
    'groups',
    'followers',
    'following',
    'friends'
]);
console.log('Download ready:', downloadLink);
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

// Get My Information
async function getMyInformation(dataTypes) {
    try {
        const response = await api.post('/my-information', {
            data: dataTypes.join(',')
        });
        return response.data.user_info;
    } catch (error) {
        console.error('Error fetching my information:', error.response?.data);
        throw error;
    }
}

// Download My Information
async function downloadMyInformation(dataTypes) {
    try {
        const response = await api.post('/my-information/download', {
            data: dataTypes.join(',')
        });
        
        if (response.data.api_status === '200') {
            return response.data.link;
        }
        
        throw new Error('Failed to generate download');
    } catch (error) {
        console.error('Error downloading information:', error.response?.data);
        throw error;
    }
}

// Usage Example
async function exportUserData() {
    try {
        const downloadLink = await downloadMyInformation([
            'my_information',
            'posts',
            'pages',
            'groups',
            'followers',
            'following',
            'friends'
        ]);
        
        // Open download in new window
        window.open(downloadLink, '_blank');
        
        alert('Your data export is ready!');
    } catch (error) {
        alert('Failed to export data');
    }
}
```

---

## React Example with UI

```jsx
import { useState } from 'react';
import axios from 'axios';

function MyInformationExport() {
    const [selectedData, setSelectedData] = useState({
        my_information: true,
        posts: true,
        pages: true,
        groups: true,
        followers: true,
        following: true,
        friends: true
    });
    const [loading, setLoading] = useState(false);
    const [downloadLink, setDownloadLink] = useState('');

    const dataOptions = [
        { key: 'my_information', label: '📊 Account Information', description: 'Profile, sessions, blocked users, transactions' },
        { key: 'posts', label: '📝 Posts', description: 'All your posts and content' },
        { key: 'pages', label: '📄 Pages', description: 'Pages you own or manage' },
        { key: 'groups', label: '👥 Groups', description: 'Groups you\'ve joined' },
        { key: 'followers', label: '👤 Followers', description: 'Users following you' },
        { key: 'following', label: '👣 Following', description: 'Users you\'re following' },
        { key: 'friends', label: '🤝 Friends', description: 'Your friend connections' },
    ];

    const handleToggle = (key) => {
        setSelectedData(prev => ({
            ...prev,
            [key]: !prev[key]
        }));
    };

    const handleSelectAll = () => {
        const newState = {};
        dataOptions.forEach(option => {
            newState[option.key] = true;
        });
        setSelectedData(newState);
    };

    const handleDeselectAll = () => {
        const newState = {};
        dataOptions.forEach(option => {
            newState[option.key] = false;
        });
        setSelectedData(newState);
    };

    const handleDownload = async () => {
        const selectedTypes = Object.keys(selectedData).filter(key => selectedData[key]);
        
        if (selectedTypes.length === 0) {
            alert('Please select at least one data type to export');
            return;
        }

        setLoading(true);
        setDownloadLink('');

        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.post(
                'http://localhost/api/v1/my-information/download',
                {
                    data: selectedTypes.join(',')
                },
                {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    }
                }
            );

            if (response.data.api_status === '200') {
                setDownloadLink(response.data.link);
                
                // Auto-open download
                window.open(response.data.link, '_blank');
                
                alert('Your data export is ready!');
            }
        } catch (error) {
            console.error('Download error:', error);
            alert('Failed to export data. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const handleViewJson = async () => {
        const selectedTypes = Object.keys(selectedData).filter(key => selectedData[key]);
        
        if (selectedTypes.length === 0) {
            alert('Please select at least one data type to view');
            return;
        }

        setLoading(true);

        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.post(
                'http://localhost/api/v1/my-information',
                {
                    data: selectedTypes.join(',')
                },
                {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    }
                }
            );

            if (response.data.api_status === '200') {
                // Display JSON in modal or new page
                const jsonWindow = window.open('', '_blank');
                jsonWindow.document.write('<pre>' + JSON.stringify(response.data.user_info, null, 2) + '</pre>');
            }
        } catch (error) {
            console.error('View error:', error);
            alert('Failed to retrieve data. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="my-information-export">
            <h2>Download My Information</h2>
            <p>Export your personal data stored on {config('app.name', 'Ouptel')}</p>

            <div className="bulk-actions">
                <button onClick={handleSelectAll} className="btn btn-secondary">
                    Select All
                </button>
                <button onClick={handleDeselectAll} className="btn btn-secondary">
                    Deselect All
                </button>
            </div>

            <div className="data-options">
                {dataOptions.map(option => (
                    <div key={option.key} className="data-option">
                        <label>
                            <input
                                type="checkbox"
                                checked={selectedData[option.key]}
                                onChange={() => handleToggle(option.key)}
                            />
                            <span className="option-label">{option.label}</span>
                        </label>
                        <p className="option-description">{option.description}</p>
                    </div>
                ))}
            </div>

            <div className="export-actions">
                <button
                    onClick={handleDownload}
                    disabled={loading}
                    className="btn btn-primary"
                >
                    {loading ? 'Generating...' : '📥 Download as HTML'}
                </button>
                
                <button
                    onClick={handleViewJson}
                    disabled={loading}
                    className="btn btn-secondary"
                >
                    👁️ View as JSON
                </button>
            </div>

            {downloadLink && (
                <div className="download-ready">
                    <h3>Your data export is ready!</h3>
                    <a href={downloadLink} target="_blank" rel="noopener noreferrer">
                        📥 Download File
                    </a>
                </div>
            )}

            <div className="gdpr-notice">
                <h3>About Your Data</h3>
                <p>This feature allows you to download a copy of your personal information stored on our platform. This is part of your data portability rights under GDPR.</p>
                <ul>
                    <li>All data is encrypted and securely stored</li>
                    <li>You can download your data at any time</li>
                    <li>The file contains only your personal data</li>
                    <li>Files are automatically cleaned up after 24 hours</li>
                </ul>
            </div>
        </div>
    );
}

export default MyInformationExport;
```

---

## Migration from Old API

### Old API → New API Mapping

| Old Endpoint | New Endpoint |
|--------------|--------------|
| `POST /v2/endpoints/download_info.php` | `POST /api/v1/my-information` (JSON) |
| `POST /v2/endpoints/download_info.php` | `POST /api/v1/my-information/download` (HTML) |

### Parameter Changes

**Old API (download_info.php):**
```json
{
    "access_token": "session_token",
    "data": "my_information,posts,followers"
}
```

**New API:**
```json
// Header: Authorization: Bearer session_token
{
    "data": "my_information,posts,followers"
}
```

---

## Use Cases

### 1. Complete Data Export (GDPR Compliance)
```javascript
// Export everything
const allData = [
    'my_information',
    'posts',
    'pages',
    'groups',
    'followers',
    'following',
    'friends'
];

const downloadLink = await downloadMyInformation(token, allData);
```

### 2. Account Backup
```javascript
// Backup account settings and connections
const backupData = [
    'my_information',
    'followers',
    'following'
];

const info = await getMyInformation(token, backupData);
localStorage.setItem('account_backup', JSON.stringify(info));
```

### 3. Content Archive
```javascript
// Archive all content
const contentData = ['posts', 'pages', 'groups'];
const archive = await getMyInformation(token, contentData);
```

### 4. Social Graph Export
```javascript
// Export social connections
const socialData = ['followers', 'following', 'friends'];
const connections = await getMyInformation(token, socialData);

console.log(`Followers: ${connections.followers.length}`);
console.log(`Following: ${connections.following.length}`);
console.log(`Friends: ${connections.friends.length}`);
```

---

## Best Practices

### 1. Selective Data Export

Only request data you need:

```javascript
// Good - Only request needed data
const quickInfo = await getMyInformation(token, ['my_information']);

// Avoid - Don't request everything if not needed
const allData = await getMyInformation(token, [
    'my_information', 'posts', 'pages', 'groups', 
    'followers', 'following', 'friends'
]); // This can be slow
```

### 2. Progress Indication

Show progress when generating large exports:

```javascript
async function exportWithProgress() {
    showProgress('Gathering your information...');
    
    const link = await downloadMyInformation(token, [
        'my_information', 'posts', 'followers', 'following'
    ]);
    
    hideProgress();
    showSuccess('Export ready!');
    window.open(link);
}
```

### 3. Error Handling

Handle errors gracefully:

```javascript
async function safeExport(dataTypes) {
    try {
        const link = await downloadMyInformation(token, dataTypes);
        return { success: true, link };
    } catch (error) {
        if (error.response?.status === 401) {
            // Session expired
            redirectToLogin();
        } else {
            return { success: false, error: 'Export failed' };
        }
    }
}
```

### 4. Scheduled Exports

Implement periodic backups:

```javascript
// Monthly backup
function scheduleMonthlyBackup() {
    setInterval(async () => {
        const dataTypes = ['my_information', 'posts', 'followers'];
        const info = await getMyInformation(token, dataTypes);
        
        // Store backup
        await saveToCloud(info);
    }, 30 * 24 * 60 * 60 * 1000); // 30 days
}
```

---

## GDPR Compliance

This API supports GDPR data portability requirements:

### Right to Data Portability (Article 20)
- ✅ Users can download all their personal data
- ✅ Data is provided in a structured, machine-readable format (JSON)
- ✅ Data is also available in human-readable format (HTML)
- ✅ Includes all categories of personal information

### Data Categories Included

1. **Personal Information**
   - Profile data (name, email, phone, etc.)
   - Account settings
   - Notification preferences

2. **Activity Data**
   - Posts and content
   - Comments and reactions
   - Pages and groups

3. **Social Data**
   - Followers and following
   - Friends
   - Blocked users

4. **Technical Data**
   - Active sessions
   - Login history

5. **Financial Data**
   - Payment transactions (if applicable)
   - Referral earnings

---

## Performance Considerations

### Large Data Sets

For users with large amounts of data:

1. **Pagination**: Posts are limited to 10,000 per request
2. **Async Processing**: Consider generating files in background for very large exports
3. **Caching**: Cache generated files for 24 hours
4. **Cleanup**: Old export files are automatically deleted

### Optimization Tips

```javascript
// Request only needed data
const minimalExport = ['my_information']; // Fast

const standardExport = ['my_information', 'posts']; // Medium

const fullExport = [
    'my_information', 'posts', 'pages', 'groups',
    'followers', 'following', 'friends'
]; // Slower for users with lots of data
```

---

## Security Considerations

1. **Authentication Required**: All endpoints require valid session token
2. **User Isolation**: Users can only access their own data
3. **Sensitive Data Filtered**: Passwords and tokens are excluded
4. **File Security**: Generated files are stored securely
5. **Automatic Cleanup**: Old export files are removed
6. **No Sharing**: Export links are user-specific

---

## Testing

### Test Cases

```javascript
// Test 1: Get account information only
const accountInfo = await getMyInformation(token, ['my_information']);
console.assert(accountInfo.my_information, 'Should have account info');
console.assert(accountInfo.my_information.username, 'Should have username');

// Test 2: Get posts
const postsInfo = await getMyInformation(token, ['posts']);
console.assert(Array.isArray(postsInfo.posts), 'Posts should be array');

// Test 3: Get multiple data types
const multiInfo = await getMyInformation(token, ['my_information', 'posts', 'followers']);
console.assert(multiInfo.my_information, 'Should have account info');
console.assert(multiInfo.posts, 'Should have posts');
console.assert(multiInfo.followers, 'Should have followers');

// Test 4: Download generation
const link = await downloadMyInformation(token, ['my_information']);
console.assert(link.includes('.html'), 'Should generate HTML file');
console.assert(link.includes('http'), 'Should be valid URL');

// Test 5: Empty data parameter
try {
    await getMyInformation(token, []);
    console.error('Should require data parameter');
} catch (error) {
    console.assert(error.response.status === 422, 'Should return validation error');
}
```

---

## Database Tables Used

This API accesses the following tables:

- `Wo_Users` - User profile data
- `Wo_AppsSessions` - Active sessions
- `Wo_Blocks` - Blocked users
- `Wo_PaymentTransactions` - Payment history
- `Wo_Posts` - User posts
- `Wo_Pages` - User pages
- `Wo_Groups` / `Wo_GroupMembers` - Group memberships
- `Wo_Followers` - Follower relationships
- `Wo_Friends` - Friend relationships

---

## File Storage

Generated HTML files are stored in:
```
storage/upload/files/{YEAR}/{MONTH}/{HASH}_info.html
```

**Example:**
```
storage/upload/files/2024/01/abc123def456_05_xyz789_info.html
```

**File Lifecycle:**
1. Generated on demand
2. Stored in user-specific directory
3. Previous export is deleted when new one is generated
4. Recommended cleanup after 24 hours

---

## Related Endpoints

- **Profile Data**: `GET /api/v1/profile/user-data`
- **Privacy Settings**: `GET /api/v1/privacy/settings`
- **Sessions**: `GET /api/v1/sessions`
- **Blocked Users**: `GET /api/v1/blocked-users`

