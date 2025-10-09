# Profile Settings API Documentation

This API mimics the old WoWonder API structure for user profile data retrieval.

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

## Get User Profile Data

Retrieves comprehensive user profile data with optional related information (mimics old `get_user_data.php`).

### Endpoint
```http
GET  /api/v1/profile/user-data
POST /api/v1/profile/user-data
```

Both GET and POST methods are supported for backward compatibility.

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `user_profile_id` | integer | No | User ID to fetch profile for (defaults to logged-in user) |
| `fetch` | string | No | Comma-separated list of data to fetch (default: "user_data") |
| `send_notify` | boolean | No | Send profile visit notification (default: false) |

### Fetch Options

The `fetch` parameter accepts a comma-separated list of the following values:

- `user_data` - Basic user profile information (default)
- `followers` - List of user's followers (up to 50)
- `following` - List of users being followed (up to 50)
- `liked_pages` - Pages liked by the user (up to 50)
- `joined_groups` - Groups joined by the user (up to 50)
- `family` - Family members added by the user

### Example Request (GET)
```http
GET /api/v1/profile/user-data?user_profile_id=123&fetch=user_data,followers,following&send_notify=1
Authorization: Bearer abc123session456
```

### Example Request (POST)
```json
POST /api/v1/profile/user-data
Authorization: Bearer abc123session456
Content-Type: application/json

{
    "user_profile_id": 123,
    "fetch": "user_data,followers,following,liked_pages",
    "send_notify": 1
}
```

---

## Success Response (200 OK)

### Basic User Data Only
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "user_data": {
        "user_id": "123",
        "username": "john_doe",
        "email": "john@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "avatar": "upload/photos/2024/01/avatar_123.jpg",
        "cover": "upload/photos/2024/01/cover_123.jpg",
        "avatar_url": "http://localhost/storage/upload/photos/2024/01/avatar_123.jpg",
        "cover_url": "http://localhost/storage/upload/photos/2024/01/cover_123.jpg",
        "about": "Software Developer",
        "gender": "male",
        "gender_text": "Male",
        "country_id": "840",
        "phone_number": "+1234567890",
        "website": "https://example.com",
        "facebook": "https://facebook.com/johndoe",
        "twitter": "https://twitter.com/johndoe",
        "instagram": "https://instagram.com/johndoe",
        "linkedin": "https://linkedin.com/in/johndoe",
        "youtube": "https://youtube.com/johndoe",
        "vk": "",
        "working": "Tech Company",
        "working_link": "https://company.com",
        "address": "123 Main St",
        "school": "University Name",
        "language": "english",
        "verified": "0",
        "lastseen_time_text": "5 minutes ago",
        "is_following": 1,
        "can_follow": 1,
        "is_following_me": 0,
        "is_blocked": 0,
        "post_count": 42,
        "followers_number": 150,
        "following_number": 200,
        "message_privacy": "0",
        "follow_privacy": "0",
        "birth_privacy": "0",
        "status": "1"
    }
}
```

### Complete Response (All Fetch Options)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "user_data": {
        "user_id": "123",
        "username": "john_doe",
        "email": "john@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "avatar_url": "http://localhost/storage/upload/photos/2024/01/avatar_123.jpg",
        "cover_url": "http://localhost/storage/upload/photos/2024/01/cover_123.jpg",
        "about": "Software Developer",
        "gender": "male",
        "gender_text": "Male",
        "lastseen_time_text": "5 minutes ago",
        "is_following": 1,
        "can_follow": 1,
        "is_following_me": 0,
        "is_blocked": 0,
        "post_count": 42,
        "followers_number": 150,
        "following_number": 200
    },
    "followers": [
        {
            "user_id": "456",
            "username": "jane_smith",
            "name": "Jane Smith",
            "avatar_url": "http://localhost/storage/upload/photos/default-avatar.png",
            "is_following": 1
        }
    ],
    "following": [
        {
            "user_id": "789",
            "username": "bob_jones",
            "name": "Bob Jones",
            "avatar_url": "http://localhost/storage/upload/photos/default-avatar.png",
            "is_following": 0
        }
    ],
    "liked_pages": [
        {
            "page_id": "10",
            "page_name": "Tech Page",
            "page_title": "Technology News",
            "avatar_url": "http://localhost/storage/upload/pages/page_10.jpg",
            "is_liked": 1
        }
    ],
    "joined_groups": [
        {
            "id": "5",
            "group_name": "developers",
            "group_title": "Developers Community",
            "avatar_url": "http://localhost/storage/upload/groups/group_5.jpg",
            "is_joined": 1
        }
    ],
    "family": [
        {
            "user_id": "321",
            "username": "mary_doe",
            "name": "Mary Doe",
            "avatar_url": "http://localhost/storage/upload/photos/avatar_321.jpg",
            "relationship_type": "sister"
        }
    ]
}
```

---

## Response Fields Explained

### User Data Fields

| Field | Type | Description |
|-------|------|-------------|
| `user_id` | string | Unique user identifier |
| `username` | string | User's username |
| `email` | string | User's email address |
| `first_name` | string | First name |
| `last_name` | string | Last name |
| `avatar` | string | Relative path to avatar image |
| `cover` | string | Relative path to cover image |
| `avatar_url` | string | Full URL to avatar image |
| `cover_url` | string | Full URL to cover image |
| `about` | string | User's bio/about text |
| `gender` | string | "male" or "female" |
| `gender_text` | string | "Male" or "Female" (formatted) |
| `phone_number` | string | Phone number |
| `website` | string | Personal website URL |
| `working` | string | Current workplace |
| `working_link` | string | Company website |
| `address` | string | Address |
| `school` | string | School/University name |
| `country_id` | string | Country ID |
| `language` | string | Preferred language |
| `lastseen_time_text` | string | Human-readable last seen time |
| `is_following` | integer | 0=Not following, 1=Following, 2=Request pending |
| `can_follow` | integer | 0=Cannot follow, 1=Can follow |
| `is_following_me` | integer | 0=Not following you, 1=Following you |
| `is_blocked` | integer | 0=Not blocked, 1=Blocked |
| `post_count` | integer | Number of posts |
| `followers_number` | integer | Number of followers |
| `following_number` | integer | Number of following |
| `message_privacy` | string | Message privacy setting |
| `follow_privacy` | string | Follow privacy setting |
| `birth_privacy` | string | Birth date privacy setting |
| `status` | string | 0=Offline, 1=Online |

### Social Media Fields
- `facebook` - Facebook profile URL
- `twitter` - Twitter profile URL
- `instagram` - Instagram profile URL
- `linkedin` - LinkedIn profile URL
- `youtube` - YouTube channel URL
- `google` - Google+ profile URL
- `vk` - VKontakte profile URL

### Following Status Values

**`is_following` values:**
- `0` - Not following this user
- `1` - Currently following this user
- `2` - Follow request sent (pending approval)

**`can_follow` values:**
- `0` - Cannot send follow request (privacy settings)
- `1` - Can send follow request

**`is_following_me` values:**
- `0` - This user is not following you
- `1` - This user is following you

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
        "error_text": "User profile is not exists."
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
        "The user_profile_id must be an integer."
    ]
}
```

---

## Feature: Profile Visit Notifications

When `send_notify=1` is provided and the following conditions are met:
- Profile visits are enabled in system settings
- Viewer's `visit_privacy` is set to 0 (visible)
- Profile owner's `visit_privacy` is set to 0 (accepts visitors)
- Profile owner is a Pro member
- Viewer is not viewing their own profile

A notification will be sent to the profile owner indicating someone visited their profile.

---

## Security & Privacy

### Sensitive Fields Filtered

The following fields are automatically removed from responses for security:
- `password`
- `email_code`, `sms_code`
- `password_reset_code`
- `ip_address`
- `wallet`, `balance`
- `admin`, `verified`
- `two_factor` settings
- Device IDs
- Internal tracking data

### Privacy Checks

**Follow Privacy:**
- If user's `follow_privacy` is `1` (Only people I follow), only mutual followers can send follow requests
- If `follow_privacy` is `0` (Everyone), anyone can send follow requests

**Profile Visit Privacy:**
- Users can disable profile visit notifications by setting `visit_privacy` to `1`
- Profile visits are only tracked for Pro members

---

## Example Usage

### cURL Examples

#### Get Own Profile
```bash
curl -X GET "http://localhost/api/v1/profile/user-data" \
  -H "Authorization: Bearer abc123session456"
```

#### Get Another User's Profile with All Data
```bash
curl -X GET "http://localhost/api/v1/profile/user-data?user_profile_id=123&fetch=user_data,followers,following,liked_pages,joined_groups,family&send_notify=1" \
  -H "Authorization: Bearer abc123session456"
```

#### POST Request with Specific Data
```bash
curl -X POST "http://localhost/api/v1/profile/user-data" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "user_profile_id": 123,
    "fetch": "user_data,followers",
    "send_notify": 1
  }'
```

---

## Migration from Old API

### Old API → New API Mapping

| Old Endpoint | New Endpoint |
|--------------|--------------|
| `GET /phone/get_user_data.php?type=get_user_data` | `GET /api/v1/profile/user-data` |
| `POST /v2/endpoints/get-user-data.php` | `POST /api/v1/profile/user-data` |

### Parameter Changes

| Old Parameter | New Parameter | Notes |
|---------------|---------------|-------|
| `s` (session) | `Authorization: Bearer {token}` | Moved to header |
| `user_id` | (automatic) | Extracted from session token |
| `user_profile_id` | `user_profile_id` | Same |
| `fetch` | `fetch` | Same format |

### Response Format

The response format remains identical to the old API for backward compatibility:
- Same field names and structure
- Same `api_status`, `api_text`, `api_version` format
- Same error codes and messages
- Same nested data structure

---

## Performance Notes

1. **Lazy Loading**: Data is only fetched when explicitly requested via the `fetch` parameter
2. **Limits**: Followers, following, pages, and groups are limited to 50 results each
3. **Caching**: Consider implementing client-side caching for frequently accessed profiles
4. **Batch Requests**: Use the `fetch` parameter to retrieve multiple data types in a single request

---

## Integration Tips

### Fetching Multiple Data Types
Always specify exactly what data you need to minimize response size and improve performance:

```javascript
// Good - Only fetch needed data
fetch = "user_data,followers"

// Avoid - Don't fetch everything if not needed
fetch = "user_data,followers,following,liked_pages,joined_groups,family"
```

### Following Status UI

Use the `is_following` and `can_follow` fields to determine button state:

```javascript
if (user_data.can_follow === 0) {
    // Hide follow button
} else if (user_data.is_following === 0) {
    // Show "Follow" button
} else if (user_data.is_following === 1) {
    // Show "Following" / "Unfollow" button
} else if (user_data.is_following === 2) {
    // Show "Request Pending" button
}
```

### Profile Visit Tracking

Only send `send_notify=1` when:
- User actively navigates to a profile page
- Don't send for API calls in background
- Don't send for profile previews/hover cards
- Don't send for bulk profile fetches

---

## Database Tables Used

This API interacts with the following tables:
- `Wo_Users` - User profiles
- `Wo_Followers` - Follow relationships
- `Wo_FollowRequests` - Pending follow requests
- `Wo_Blocks` - Blocked users
- `Wo_Posts` - Post count
- `Wo_PageLikes` - Liked pages
- `Wo_Pages` - Page details
- `Wo_GroupMembers` - Group memberships
- `Wo_Groups` - Group details
- `Wo_Family` - Family relationships
- `Wo_Notifications` - Profile visit notifications
- `Wo_Config` - System settings
- `Wo_AppsSessions` - Authentication

