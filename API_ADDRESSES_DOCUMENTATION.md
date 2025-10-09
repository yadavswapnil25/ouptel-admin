# Addresses API Documentation

This API mimics the old WoWonder API structure for managing user shipping/delivery addresses.

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

The Addresses API allows users to:
- 📍 Store multiple delivery/shipping addresses
- ✏️ Update existing addresses
- 🗑️ Delete addresses
- 📋 View all saved addresses
- 🔍 Get specific address by ID

This is essential for:
- E-commerce and marketplace features
- Product delivery management
- Order fulfillment
- User convenience (saved addresses)

---

## 1. Get All Addresses

Retrieves all saved addresses for the authenticated user.

### Endpoint
```http
GET /api/v1/addresses
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Query Parameters (Optional)

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `limit` | integer | Number of addresses to return (max 50) | 20 |
| `offset` | integer | Address ID to paginate from | 0 |

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "total": 3,
    "data": [
        {
            "id": 1,
            "user_id": 123,
            "name": "John Doe",
            "phone": "+1234567890",
            "country": "United States",
            "city": "New York",
            "zip": "10001",
            "address": "123 Main Street, Apt 4B",
            "time": 1704441600
        },
        {
            "id": 2,
            "user_id": 123,
            "name": "John Doe - Office",
            "phone": "+1234567890",
            "country": "United States",
            "city": "Brooklyn",
            "zip": "11201",
            "address": "456 Office Tower, Suite 900",
            "time": 1704355200
        }
    ]
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique address ID |
| `user_id` | integer | User ID who owns this address |
| `name` | string | Recipient name |
| `phone` | string | Contact phone number |
| `country` | string | Country name |
| `city` | string | City name |
| `zip` | string | ZIP/Postal code |
| `address` | string | Full street address |
| `time` | integer | Unix timestamp when address was created |

---

## 2. Get Address by ID

Retrieves a specific address by its ID.

### Endpoint
```http
GET /api/v1/addresses/{id}
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Address ID |

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "data": {
        "id": 1,
        "user_id": 123,
        "name": "John Doe",
        "phone": "+1234567890",
        "country": "United States",
        "city": "New York",
        "zip": "10001",
        "address": "123 Main Street, Apt 4B",
        "time": 1704441600
    }
}
```

---

## 3. Add New Address

Creates a new delivery/shipping address.

### Endpoint
```http
POST /api/v1/addresses
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Request Body

```json
{
    "name": "John Doe",
    "phone": "+1234567890",
    "country": "United States",
    "city": "New York",
    "zip": "10001",
    "address": "123 Main Street, Apt 4B"
}
```

### Request Parameters

| Parameter | Type | Required | Max Length | Description |
|-----------|------|----------|------------|-------------|
| `name` | string | Yes | 255 | Recipient name |
| `phone` | string | Yes | 50 | Contact phone number |
| `country` | string | Yes | 100 | Country name |
| `city` | string | Yes | 100 | City name |
| `zip` | string | Yes | 20 | ZIP/Postal code |
| `address` | string | Yes | 500 | Full street address |

### Success Response (201 Created)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Address successfully added",
    "data": {
        "id": 3,
        "user_id": 123,
        "name": "John Doe",
        "phone": "+1234567890",
        "country": "United States",
        "city": "New York",
        "zip": "10001",
        "address": "123 Main Street, Apt 4B",
        "time": 1704441600
    }
}
```

---

## 4. Update Address

Updates an existing address.

### Endpoint
```http
PUT /api/v1/addresses/{id}
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Address ID to update |

### Request Body

```json
{
    "name": "John Doe - Updated",
    "phone": "+1987654321",
    "country": "United States",
    "city": "Brooklyn",
    "zip": "11201",
    "address": "789 New Street, Floor 3"
}
```

### Request Parameters

All parameters are required (same as add address).

| Parameter | Type | Required | Max Length | Description |
|-----------|------|----------|------------|-------------|
| `name` | string | Yes | 255 | Recipient name |
| `phone` | string | Yes | 50 | Contact phone number |
| `country` | string | Yes | 100 | Country name |
| `city` | string | Yes | 100 | City name |
| `zip` | string | Yes | 20 | ZIP/Postal code |
| `address` | string | Yes | 500 | Full street address |

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Address successfully edited",
    "data": {
        "id": 1,
        "user_id": 123,
        "name": "John Doe - Updated",
        "phone": "+1987654321",
        "country": "United States",
        "city": "Brooklyn",
        "zip": "11201",
        "address": "789 New Street, Floor 3",
        "time": 1704441600
    }
}
```

---

## 5. Delete Address

Deletes an existing address.

### Endpoint
```http
DELETE /api/v1/addresses/{id}
```

### Headers
```
Authorization: Bearer {session_id}
Content-Type: application/json
```

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Address ID to delete |

### Success Response (200 OK)
```json
{
    "api_status": "200",
    "api_text": "success",
    "api_version": "1.0",
    "message": "Address successfully deleted"
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

### 404 Not Found - Address Not Found
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": {
        "error_id": "6",
        "error_text": "Address not found."
    }
}
```

### 422 Validation Error - Missing Required Fields
```json
{
    "api_status": "400",
    "api_text": "failed",
    "api_version": "1.0",
    "errors": [
        "The name field is required.",
        "The phone field is required.",
        "The address field is required."
    ]
}
```

---

## Example Usage

### cURL Examples

#### Get All Addresses
```bash
curl -X GET "http://localhost/api/v1/addresses" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

#### Get All Addresses with Pagination
```bash
curl -X GET "http://localhost/api/v1/addresses?limit=10&offset=0" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

#### Get Specific Address
```bash
curl -X GET "http://localhost/api/v1/addresses/1" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

#### Add New Address
```bash
curl -X POST "http://localhost/api/v1/addresses" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "phone": "+1234567890",
    "country": "United States",
    "city": "New York",
    "zip": "10001",
    "address": "123 Main Street, Apt 4B"
  }'
```

#### Update Address
```bash
curl -X PUT "http://localhost/api/v1/addresses/1" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe - Home",
    "phone": "+1987654321",
    "country": "United States",
    "city": "Brooklyn",
    "zip": "11201",
    "address": "456 New Street"
  }'
```

#### Delete Address
```bash
curl -X DELETE "http://localhost/api/v1/addresses/1" \
  -H "Authorization: Bearer abc123session456" \
  -H "Content-Type: application/json"
```

---

## JavaScript/TypeScript Examples

### Using Fetch API

```javascript
// Get All Addresses
async function getAddresses(token, limit = 20, offset = 0) {
    const response = await fetch(
        `http://localhost/api/v1/addresses?limit=${limit}&offset=${offset}`,
        {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        }
    );
    
    const data = await response.json();
    return data.data;
}

// Get Specific Address
async function getAddressById(token, id) {
    const response = await fetch(`http://localhost/api/v1/addresses/${id}`, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        }
    });
    
    const data = await response.json();
    return data.data;
}

// Add New Address
async function addAddress(token, addressData) {
    const response = await fetch('http://localhost/api/v1/addresses', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(addressData)
    });
    
    const data = await response.json();
    return data;
}

// Update Address
async function updateAddress(token, id, addressData) {
    const response = await fetch(`http://localhost/api/v1/addresses/${id}`, {
        method: 'PUT',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(addressData)
    });
    
    const data = await response.json();
    return data;
}

// Delete Address
async function deleteAddress(token, id) {
    const response = await fetch(`http://localhost/api/v1/addresses/${id}`, {
        method: 'DELETE',
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

// Get all addresses
const addresses = await getAddresses(token);
console.log(`You have ${addresses.length} saved addresses`);

// Add new address
const newAddress = await addAddress(token, {
    name: 'John Doe',
    phone: '+1234567890',
    country: 'United States',
    city: 'New York',
    zip: '10001',
    address: '123 Main Street, Apt 4B'
});
console.log('Address added:', newAddress.data.id);

// Update address
await updateAddress(token, 1, {
    name: 'John Doe - Home',
    phone: '+1987654321',
    country: 'United States',
    city: 'Brooklyn',
    zip: '11201',
    address: '456 New Street'
});

// Delete address
await deleteAddress(token, 1);
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

// Get All Addresses
async function getAddresses(limit = 20, offset = 0) {
    try {
        const response = await api.get('/addresses', {
            params: { limit, offset }
        });
        return response.data.data;
    } catch (error) {
        console.error('Error fetching addresses:', error.response?.data);
        throw error;
    }
}

// Add Address
async function addAddress(addressData) {
    try {
        const response = await api.post('/addresses', addressData);
        return response.data;
    } catch (error) {
        console.error('Error adding address:', error.response?.data);
        throw error;
    }
}

// Update Address
async function updateAddress(id, addressData) {
    try {
        const response = await api.put(`/addresses/${id}`, addressData);
        return response.data;
    } catch (error) {
        console.error('Error updating address:', error.response?.data);
        throw error;
    }
}

// Delete Address
async function deleteAddress(id) {
    try {
        const response = await api.delete(`/addresses/${id}`);
        return response.data;
    } catch (error) {
        console.error('Error deleting address:', error.response?.data);
        throw error;
    }
}
```

---

## React Example with CRUD UI

```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function AddressManagement() {
    const [addresses, setAddresses] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [formData, setFormData] = useState({
        name: '',
        phone: '',
        country: '',
        city: '',
        zip: '',
        address: ''
    });
    const [errors, setErrors] = useState([]);

    useEffect(() => {
        loadAddresses();
    }, []);

    const loadAddresses = async () => {
        setLoading(true);
        try {
            const token = localStorage.getItem('session_token');
            const response = await axios.get('http://localhost/api/v1/addresses', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            
            setAddresses(response.data.data);
        } catch (error) {
            console.error('Failed to load addresses:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleChange = (e) => {
        setFormData({
            ...formData,
            [e.target.name]: e.target.value
        });
        setErrors([]);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors([]);

        try {
            const token = localStorage.getItem('session_token');
            
            if (editingId) {
                // Update existing address
                await axios.put(
                    `http://localhost/api/v1/addresses/${editingId}`,
                    formData,
                    { headers: { 'Authorization': `Bearer ${token}` } }
                );
                alert('Address updated successfully!');
            } else {
                // Add new address
                await axios.post(
                    'http://localhost/api/v1/addresses',
                    formData,
                    { headers: { 'Authorization': `Bearer ${token}` } }
                );
                alert('Address added successfully!');
            }

            // Reset form and reload
            setFormData({
                name: '',
                phone: '',
                country: '',
                city: '',
                zip: '',
                address: ''
            });
            setEditingId(null);
            setShowForm(false);
            await loadAddresses();

        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(Array.isArray(error.response.data.errors) 
                    ? error.response.data.errors 
                    : [error.response.data.errors.error_text]);
            } else {
                setErrors(['Failed to save address']);
            }
        }
    };

    const handleEdit = (address) => {
        setFormData({
            name: address.name,
            phone: address.phone,
            country: address.country,
            city: address.city,
            zip: address.zip,
            address: address.address
        });
        setEditingId(address.id);
        setShowForm(true);
    };

    const handleDelete = async (id, name) => {
        if (!confirm(`Delete address for ${name}?`)) return;

        try {
            const token = localStorage.getItem('session_token');
            await axios.delete(
                `http://localhost/api/v1/addresses/${id}`,
                { headers: { 'Authorization': `Bearer ${token}` } }
            );
            
            alert('Address deleted successfully');
            await loadAddresses();
        } catch (error) {
            alert('Failed to delete address');
        }
    };

    const handleCancel = () => {
        setFormData({
            name: '',
            phone: '',
            country: '',
            city: '',
            zip: '',
            address: ''
        });
        setEditingId(null);
        setShowForm(false);
        setErrors([]);
    };

    if (loading) return <div>Loading addresses...</div>;

    return (
        <div className="address-management">
            <h2>My Addresses</h2>
            <p>Manage your delivery and shipping addresses</p>

            <button 
                onClick={() => setShowForm(!showForm)} 
                className="btn btn-primary"
            >
                {showForm ? 'Cancel' : '+ Add New Address'}
            </button>

            {showForm && (
                <div className="address-form">
                    <h3>{editingId ? 'Edit Address' : 'Add New Address'}</h3>

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
                            <label>Recipient Name *</label>
                            <input
                                type="text"
                                name="name"
                                value={formData.name}
                                onChange={handleChange}
                                placeholder="Full name"
                                required
                            />
                        </div>

                        <div className="form-group">
                            <label>Phone Number *</label>
                            <input
                                type="tel"
                                name="phone"
                                value={formData.phone}
                                onChange={handleChange}
                                placeholder="+1234567890"
                                required
                            />
                        </div>

                        <div className="form-row">
                            <div className="form-group">
                                <label>Country *</label>
                                <input
                                    type="text"
                                    name="country"
                                    value={formData.country}
                                    onChange={handleChange}
                                    placeholder="Country"
                                    required
                                />
                            </div>

                            <div className="form-group">
                                <label>City *</label>
                                <input
                                    type="text"
                                    name="city"
                                    value={formData.city}
                                    onChange={handleChange}
                                    placeholder="City"
                                    required
                                />
                            </div>

                            <div className="form-group">
                                <label>ZIP Code *</label>
                                <input
                                    type="text"
                                    name="zip"
                                    value={formData.zip}
                                    onChange={handleChange}
                                    placeholder="10001"
                                    required
                                />
                            </div>
                        </div>

                        <div className="form-group">
                            <label>Street Address *</label>
                            <textarea
                                name="address"
                                value={formData.address}
                                onChange={handleChange}
                                placeholder="Street address, apartment, suite, etc."
                                required
                                rows="3"
                            />
                        </div>

                        <div className="form-actions">
                            <button type="submit" className="btn btn-primary">
                                {editingId ? 'Update Address' : 'Add Address'}
                            </button>
                            <button 
                                type="button" 
                                onClick={handleCancel}
                                className="btn btn-secondary"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            )}

            <div className="addresses-list">
                {addresses.length === 0 ? (
                    <div className="empty-state">
                        <p>No saved addresses yet.</p>
                        <p>Add your first address to get started.</p>
                    </div>
                ) : (
                    addresses.map(address => (
                        <div key={address.id} className="address-card">
                            <div className="address-info">
                                <h3>{address.name}</h3>
                                <p>{address.address}</p>
                                <p>{address.city}, {address.zip}</p>
                                <p>{address.country}</p>
                                <p className="phone">📞 {address.phone}</p>
                            </div>
                            
                            <div className="address-actions">
                                <button
                                    onClick={() => handleEdit(address)}
                                    className="btn btn-edit"
                                >
                                    Edit
                                </button>
                                <button
                                    onClick={() => handleDelete(address.id, address.name)}
                                    className="btn btn-delete"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

export default AddressManagement;
```

---

## Migration from Old API

### Old API → New API Mapping

| Old Endpoint | New Endpoint |
|--------------|--------------|
| `POST /v2/endpoints/address.php` with `type=get` | `GET /api/v1/addresses` |
| `POST /v2/endpoints/address.php` with `type=get_by_id` | `GET /api/v1/addresses/{id}` |
| `POST /v2/endpoints/address.php` with `type=add` | `POST /api/v1/addresses` |
| `POST /v2/endpoints/address.php` with `type=edit` | `PUT /api/v1/addresses/{id}` |
| `POST /v2/endpoints/address.php` with `type=delete` | `DELETE /api/v1/addresses/{id}` |

### Parameter Changes

**Old API (address.php):**
```json
{
    "access_token": "session_token",
    "type": "add",
    "name": "John Doe",
    "phone": "+1234567890",
    "country": "United States",
    "city": "New York",
    "zip": "10001",
    "address": "123 Main Street"
}
```

**New API:**
```json
// Header: Authorization: Bearer session_token
// POST /api/v1/addresses
{
    "name": "John Doe",
    "phone": "+1234567890",
    "country": "United States",
    "city": "New York",
    "zip": "10001",
    "address": "123 Main Street"
}
```

---

## Best Practices

### 1. Validate Input Client-Side

Always validate before sending to API:

```javascript
function validateAddress(data) {
    const errors = [];
    
    if (!data.name || data.name.trim().length === 0) {
        errors.push('Name is required');
    }
    
    if (!data.phone || data.phone.trim().length === 0) {
        errors.push('Phone number is required');
    }
    
    if (!data.country || data.country.trim().length === 0) {
        errors.push('Country is required');
    }
    
    if (!data.city || data.city.trim().length === 0) {
        errors.push('City is required');
    }
    
    if (!data.zip || data.zip.trim().length === 0) {
        errors.push('ZIP code is required');
    }
    
    if (!data.address || data.address.trim().length === 0) {
        errors.push('Street address is required');
    }
    
    return errors;
}
```

### 2. Provide Address Templates

Offer common address labels:

```javascript
const addressTemplates = {
    home: { name: 'Home Address' },
    work: { name: 'Work Address' },
    other: { name: 'Other Address' }
};
```

### 3. Auto-complete/Geocoding

Integrate with address autocomplete services:

```javascript
// Using Google Places API
async function autocompleteAddress(input) {
    const service = new google.maps.places.AutocompleteService();
    return new Promise((resolve) => {
        service.getPlacePredictions(
            { input, types: ['address'] },
            (predictions) => resolve(predictions)
        );
    });
}
```

### 4. Format Phone Numbers

Ensure consistent phone number format:

```javascript
function formatPhoneNumber(phone) {
    // Remove all non-numeric characters
    const cleaned = phone.replace(/\D/g, '');
    
    // Format as needed (example for US)
    if (cleaned.length === 10) {
        return `+1${cleaned}`;
    }
    
    return phone; // Return as-is if not standard format
}
```

---

## Common Use Cases

### 1. Checkout Address Selection
```javascript
async function selectAddressForOrder(addressId) {
    const address = await getAddressById(token, addressId);
    
    // Use this address for current order
    localStorage.setItem('checkout_address', JSON.stringify(address));
    
    return address;
}
```

### 2. Set Default Address
```javascript
async function setDefaultAddress(addressId) {
    // Store default address ID
    localStorage.setItem('default_address_id', addressId);
    
    // Optional: Add a "default" flag to the address
    const address = await getAddressById(token, addressId);
    // Display as default in UI
}
```

### 3. Quick Add from Checkout
```javascript
async function quickAddAddressFromCheckout(addressData) {
    const result = await addAddress(token, addressData);
    
    if (result.api_status === '200') {
        // Use this address immediately for checkout
        return result.data.id;
    }
}
```

### 4. Duplicate Address
```javascript
async function duplicateAddress(addressId) {
    const original = await getAddressById(token, addressId);
    
    const duplicate = {
        ...original,
        name: original.name + ' (Copy)'
    };
    
    delete duplicate.id;
    delete duplicate.user_id;
    delete duplicate.time;
    
    await addAddress(token, duplicate);
}
```

---

## Field Validation Tips

### Name Field
- Should include full recipient name
- Can include labels (e.g., "John Doe - Home")
- Maximum 255 characters

### Phone Field
- Should include country code (e.g., +1 for US)
- Format: +[country code][number]
- Examples: +1234567890, +44 20 1234 5678
- Maximum 50 characters

### Country Field
- Full country name (e.g., "United States", not "US")
- Or use ISO country codes if preferred
- Maximum 100 characters

### City Field
- City name only (not state/province)
- Examples: "New York", "London", "Tokyo"
- Maximum 100 characters

### ZIP Field
- Postal/ZIP code
- Format varies by country
- Examples: "10001", "SW1A 1AA", "100-0001"
- Maximum 20 characters

### Address Field
- Complete street address
- Include apartment, suite, floor numbers
- Examples: "123 Main St, Apt 4B", "Suite 900, Office Tower"
- Maximum 500 characters

---

## Security Considerations

1. **User Isolation**: Users can only access their own addresses
2. **Ownership Validation**: All operations verify address ownership
3. **Data Privacy**: Addresses are never shared with other users
4. **Secure Storage**: Stored in protected database table
5. **No Admin Override**: Even admins cannot see other users' addresses (for privacy)

---

## Testing

### Test Cases

```javascript
// Test 1: Get all addresses
const addresses = await getAddresses(token);
console.assert(Array.isArray(addresses), 'Should return array');

// Test 2: Add address
const newAddress = await addAddress(token, {
    name: 'Test User',
    phone: '+1234567890',
    country: 'United States',
    city: 'New York',
    zip: '10001',
    address: '123 Test Street'
});
console.assert(newAddress.api_status === '200', 'Should add address');

// Test 3: Get specific address
const specific = await getAddressById(token, newAddress.data.id);
console.assert(specific.name === 'Test User', 'Should get correct address');

// Test 4: Update address
await updateAddress(token, newAddress.data.id, {
    name: 'Test User Updated',
    phone: '+1987654321',
    country: 'United States',
    city: 'Brooklyn',
    zip: '11201',
    address: '456 Updated Street'
});
const updated = await getAddressById(token, newAddress.data.id);
console.assert(updated.name === 'Test User Updated', 'Should update address');

// Test 5: Delete address
await deleteAddress(token, newAddress.data.id);
try {
    await getAddressById(token, newAddress.data.id);
    console.error('Should not find deleted address');
} catch (error) {
    console.assert(error.response.status === 404, 'Should return 404');
}

// Test 6: Cannot access other user's address
try {
    await getAddressById(token, otherUserAddressId);
    console.error('Should not access other user address');
} catch (error) {
    console.assert(error.response.status === 404, 'Should deny access');
}
```

---

## Database Schema

Addresses are stored in the `Wo_UserAddress` table:

```sql
CREATE TABLE Wo_UserAddress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    country VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    zip VARCHAR(20) NOT NULL,
    address VARCHAR(500) NOT NULL,
    time INT NOT NULL,
    INDEX (user_id),
    INDEX (time)
);

-- Get user's addresses
SELECT * FROM Wo_UserAddress 
WHERE user_id = ? 
ORDER BY id DESC;
```

---

## Related Endpoints

- **Orders**: Used with order/checkout APIs
- **Market**: Used with marketplace product purchases
- **Profile Settings**: Part of complete user profile

