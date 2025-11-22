# Tabesh API Documentation

## REST API Endpoints

Base URL: `/wp-json/tabesh/v1/`

All requests require proper authentication and nonce verification.

---

## Authentication

### Nonce Header
All authenticated requests must include the nonce in the header:

```javascript
headers: {
    'X-WP-Nonce': tabeshData.nonce
}
```

### User Authentication
Some endpoints require the user to be logged in. The plugin uses WordPress's built-in authentication.

---

## Endpoints

### 1. Calculate Price

Calculate the price for a book printing order.

**Endpoint:** `POST /calculate-price`

**Authentication:** None required (public endpoint)

**Request Body:**
```json
{
    "book_size": "A5",
    "paper_type": "تحریر",
    "paper_weight": "70",
    "print_type": "سیاه و سفید",
    "page_count_bw": 100,
    "page_count_color": 0,
    "quantity": 100,
    "binding_type": "شومیز",
    "license_type": "دارم",
    "cover_paper_weight": "250",
    "lamination_type": "براق",
    "extras": ["لب گرد", "شیرینک"]
}
```

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "price_per_book": 50000,
        "quantity": 100,
        "subtotal": 5000000,
        "discount_percent": 5,
        "discount_amount": 250000,
        "total_price": 4750000,
        "page_count_total": 100
    }
}
```

**Response (Error):**
```json
{
    "success": false,
    "message": "خطا در محاسبه قیمت"
}
```

---

### 2. Submit Order

Submit a new book printing order.

**Endpoint:** `POST /submit-order`

**Authentication:** Required (user must be logged in)

**Request Body:**
```json
{
    "book_size": "A5",
    "paper_type": "تحریر",
    "paper_weight": "70",
    "print_type": "سیاه و سفید",
    "page_count_bw": 100,
    "page_count_color": 0,
    "quantity": 100,
    "binding_type": "شومیز",
    "license_type": "دارم",
    "cover_paper_type": "کرافت",
    "cover_paper_weight": "250",
    "lamination_type": "براق",
    "extras": ["لب گرد"],
    "notes": "توضیحات اضافی"
}
```

**Response (Success):**
```json
{
    "success": true,
    "order_id": 123,
    "message": "سفارش با موفقیت ثبت شد"
}
```

**Response (Error - Not Logged In):**
```json
{
    "success": false,
    "message": "شما باید وارد حساب کاربری خود شوید."
}
```

---

### 3. Update Order Status

Update the status of an existing order.

**Endpoint:** `POST /update-status`

**Authentication:** Required (user must have `manage_woocommerce` capability)

**Request Body:**
```json
{
    "order_id": 123,
    "status": "processing"
}
```

**Possible Status Values:**
- `pending` - در انتظار بررسی
- `confirmed` - تایید شده
- `processing` - در حال چاپ
- `ready` - آماده تحویل
- `completed` - تحویل داده شده
- `cancelled` - لغو شده

**Response (Success):**
```json
{
    "success": true,
    "message": "وضعیت با موفقیت به‌روزرسانی شد"
}
```

**Response (Error):**
```json
{
    "success": false,
    "message": "خطا در به‌روزرسانی وضعیت"
}
```

---

## JavaScript API

### Frontend

The plugin provides a global JavaScript object `tabeshData` with the following properties:

```javascript
tabeshData = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    restUrl: '/wp-json/tabesh/v1',
    nonce: 'abc123...',
    strings: {
        calculating: 'در حال محاسبه...',
        error: 'خطا در پردازش درخواست',
        success: 'عملیات با موفقیت انجام شد'
    }
}
```

### Admin

For admin pages, use `tabeshAdminData`:

```javascript
tabeshAdminData = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    restUrl: '/wp-json/tabesh/v1',
    nonce: 'abc123...'
}
```

---

## PHP Hooks

### Actions

#### `tabesh_order_submitted`
Fired when a new order is submitted.

```php
do_action('tabesh_order_submitted', $order_id, $order_data);
```

**Parameters:**
- `$order_id` (int) - The ID of the newly created order
- `$order_data` (array) - The order data

**Example:**
```php
add_action('tabesh_order_submitted', function($order_id, $order_data) {
    // Custom code here
    error_log("New order submitted: " . $order_id);
}, 10, 2);
```

#### `tabesh_order_status_changed`
Fired when an order status is changed.

```php
do_action('tabesh_order_status_changed', $order_id, $status);
```

**Parameters:**
- `$order_id` (int) - The order ID
- `$status` (string) - The new status

**Example:**
```php
add_action('tabesh_order_status_changed', function($order_id, $status) {
    // Custom notification logic
    if ($status === 'completed') {
        // Send completion notification
    }
}, 10, 2);
```

### Filters

#### `tabesh_calculate_price`
Filter the calculated price.

```php
apply_filters('tabesh_calculate_price', $price_data, $params);
```

**Parameters:**
- `$price_data` (array) - The calculated price data
- `$params` (array) - The input parameters

**Example:**
```php
add_filter('tabesh_calculate_price', function($price_data, $params) {
    // Apply custom discount
    if ($params['quantity'] >= 500) {
        $price_data['discount_percent'] = 15;
        $price_data['discount_amount'] = ($price_data['subtotal'] * 15) / 100;
        $price_data['total_price'] = $price_data['subtotal'] - $price_data['discount_amount'];
    }
    return $price_data;
}, 10, 2);
```

#### `tabesh_order_data`
Filter order data before saving.

```php
apply_filters('tabesh_order_data', $data, $params);
```

**Example:**
```php
add_filter('tabesh_order_data', function($data, $params) {
    // Add custom fields
    $data['custom_field'] = 'custom_value';
    return $data;
}, 10, 2);
```

---

## Database Schema

### `wp_tabesh_orders` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) | Primary key |
| user_id | bigint(20) | WordPress user ID |
| order_number | varchar(50) | Unique order number |
| book_size | varchar(50) | Book size (قطع) |
| paper_type | varchar(50) | Paper type |
| paper_weight | varchar(20) | Paper weight |
| print_type | varchar(50) | Print type |
| page_count_color | int(11) | Number of color pages |
| page_count_bw | int(11) | Number of B&W pages |
| page_count_total | int(11) | Total pages |
| quantity | int(11) | Order quantity |
| binding_type | varchar(50) | Binding type |
| license_type | varchar(50) | License type |
| cover_paper_type | varchar(50) | Cover paper type |
| cover_paper_weight | varchar(20) | Cover paper weight |
| lamination_type | varchar(50) | Lamination type |
| extras | longtext | Serialized array of extras |
| total_price | decimal(10,2) | Total price |
| status | varchar(50) | Order status |
| files | longtext | Serialized array of file URLs |
| notes | longtext | Order notes |
| created_at | datetime | Creation timestamp |
| updated_at | datetime | Last update timestamp |
| archived | tinyint(1) | Archive flag |

### `wp_tabesh_settings` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) | Primary key |
| setting_key | varchar(255) | Setting key (unique) |
| setting_value | longtext | Setting value |
| setting_type | varchar(50) | Data type |
| created_at | datetime | Creation timestamp |
| updated_at | datetime | Last update timestamp |

### `wp_tabesh_logs` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) | Primary key |
| order_id | bigint(20) | Related order ID |
| user_id | bigint(20) | User who performed action |
| action | varchar(255) | Action type |
| description | longtext | Action description |
| created_at | datetime | Timestamp |

---

## Error Handling

### HTTP Status Codes

- **200** - Success
- **400** - Bad Request (invalid parameters)
- **401** - Unauthorized (not logged in)
- **403** - Forbidden (insufficient permissions)
- **404** - Not Found
- **500** - Internal Server Error

### Error Response Format

```json
{
    "success": false,
    "message": "Error message in Persian",
    "code": "error_code"
}
```

---

## Rate Limiting

Currently, there are no rate limits on API endpoints. However, standard WordPress authentication and security measures apply.

---

## Examples

### Calculate Price with jQuery

```javascript
jQuery.ajax({
    url: tabeshData.restUrl + '/calculate-price',
    method: 'POST',
    contentType: 'application/json',
    beforeSend: function(xhr) {
        xhr.setRequestHeader('X-WP-Nonce', tabeshData.nonce);
    },
    data: JSON.stringify({
        book_size: 'A5',
        paper_type: 'تحریر',
        paper_weight: '70',
        print_type: 'سیاه و سفید',
        page_count_bw: 100,
        page_count_color: 0,
        quantity: 100,
        binding_type: 'شومیز',
        license_type: 'دارم',
        cover_paper_weight: '250',
        lamination_type: 'براق',
        extras: []
    }),
    success: function(response) {
        if (response.success) {
            console.log('Total Price:', response.data.total_price);
        }
    }
});
```

### Submit Order with Fetch API

```javascript
fetch(tabeshData.restUrl + '/submit-order', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': tabeshData.nonce
    },
    body: JSON.stringify({
        book_size: 'A5',
        // ... other parameters
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Order ID:', data.order_id);
    }
});
```

---

## Support

For API support and questions:
- GitHub Issues: https://github.com/tabshhh12/Tabesh/issues
- Documentation: https://github.com/tabshhh12/Tabesh

---

**Last Updated:** October 2024  
**API Version:** 1.0.0
