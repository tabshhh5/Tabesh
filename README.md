# Tabesh - تابش
## سامانه جامع ثبت سفارش چاپ کتاب

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/tabshhh12/Tabesh)
[![WordPress](https://img.shields.io/badge/WordPress-6.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.2.2%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A comprehensive WordPress plugin for managing book printing orders with full WooCommerce integration, SMS notifications, and RTL (Persian) support.

## 📋 Features

### Core Functionality
- ✅ **Dynamic Price Calculator** - Calculate book printing costs based on multiple parameters
- ✅ **Order Management System** - Complete order lifecycle management
- ✅ **User Dashboard** - Track order status with visual progress indicators
- ✅ **Admin Panel** - Comprehensive management interface for orders and settings
- ✅ **Staff Panel** - Modern, mobile-first panel with live search, dark/light themes, and AJAX status updates
- ✅ **SMS Notifications** - Automated notifications via MelliPayamak API
- ✅ **Email Notifications** - Email alerts for order updates
- ✅ **Role-Based Access** - Different views for Admin, Staff, and Customers
- ✅ **Archive System** - Archive and restore completed orders

### Technical Features
- 🎨 **RTL Support** - Full right-to-left interface for Persian language
- 📱 **Responsive Design** - Mobile-friendly interface
- 🔌 **REST API** - Modern AJAX-based interactions
- 🗄️ **Custom Database Tables** - Optimized data storage
- 🔒 **Security** - Follows WordPress coding standards
- ⚡ **LiteSpeed Compatible** - Optimized for LiteSpeed servers

## 📦 Installation

### Requirements
- WordPress 6.8 or higher
- PHP 8.2.2 or higher
- WooCommerce (latest version)
- MySQL 5.7+ or MariaDB 10.2+

### Installation Steps

1. **Download the Plugin**
   ```bash
   git clone https://github.com/tabshhh12/Tabesh.git
   ```

2. **Upload to WordPress**
   - Upload the `Tabesh` folder to `/wp-content/plugins/`
   - Or create a ZIP file and upload via WordPress admin panel

3. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Tabesh - سامانه جامع ثبت سفارش چاپ کتاب"
   - Click "Activate"

4. **Configure Settings**
   - Go to Tabesh → Settings
   - Configure product parameters, pricing, and SMS settings

## 🚀 Usage

### Shortcodes

#### Order Form (V1 - Legacy)
Display the legacy order submission form:
```
[tabesh_order_form]
```

#### Order Form V2 (New - Recommended)
Display the advanced order form with dynamic dependency mapping:
```
[tabesh_order_form_v2]
```
**Features:**
- Dynamic option filtering based on pricing matrix V2
- Cascading form that shows only allowed combinations
- Real-time price calculation
- Better performance with object caching
- Requires V2 pricing engine to be enabled

See [ORDER_FORM_V2_GUIDE.md](ORDER_FORM_V2_GUIDE.md) for complete documentation.

#### User Orders
Show logged-in user's orders:
```
[tabesh_user_orders]
```

#### Staff Panel
Display staff management panel (requires edit_shop_orders capability):
```
[tabesh_staff_panel]
```

#### Admin Dashboard
Show admin dashboard overview (requires manage_woocommerce capability):
```
[tabesh_admin_dashboard]
```

#### Product Pricing (New V2)
Manage pricing parameters with matrix-based engine (requires manage_woocommerce capability):
```
[tabesh_product_pricing]
```
See [PRICING_ENGINE_V2.md](PRICING_ENGINE_V2.md) for details about the new pricing system.

### Admin Menu
After activation, access the plugin via:
- **Dashboard**: Tabesh → Dashboard
- **Active Orders**: Tabesh → Active Orders
- **Archived Orders**: Tabesh → Archived Orders
- **Settings**: Tabesh → Settings

## ⚙️ Configuration

### Product Parameters
Configure in Tabesh → Settings → Product Parameters:
- Book sizes (قطع کتاب)
- Paper types and weights
- Print types (color, B&W, mixed)
- Binding types
- License types
- Cover options
- Extra services

### SMS Settings
Configure in Tabesh → Settings → SMS:
1. Enter MelliPayamak credentials
2. Set sender number
3. Add admin phone for notifications
4. Enable/disable notification triggers

### Pricing
The pricing algorithm is currently defined in code. To modify:
- Edit `includes/class-tabesh-order.php`
- Modify the `calculate_price()` method
- Future versions will include GUI for pricing configuration

## 🗃️ Database Structure

### Tables Created
- `wp_tabesh_orders` - Store all order data
- `wp_tabesh_settings` - Plugin configuration
- `wp_tabesh_logs` - Activity logs

## 🔌 API Endpoints

### REST API Routes
All routes are prefixed with `/wp-json/tabesh/v1/`

- **POST** `/calculate-price` - Calculate order price
- **POST** `/submit-order` - Submit new order (requires authentication)
- **POST** `/update-status` - Update order status (requires permission)

## 🎨 Customization

### Styling
CSS files located in `assets/css/`:
- `frontend.css` - Front-end styles
- `admin.css` - Admin panel styles

### JavaScript
JS files located in `assets/js/`:
- `frontend.js` - Front-end functionality
- `admin.js` - Admin panel functionality

## 🔒 Security

- Nonce verification for all forms
- Input sanitization and validation
- SQL injection prevention
- XSS protection
- Role-based access control

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📝 License

This plugin is licensed under the GPL v2 or later.

## 👥 Credits

Developed by [Chapco](https://chapco.ir)

## 📞 Support

For support and questions:
- Create an issue on [GitHub](https://github.com/tabshhh12/Tabesh/issues)
- Contact: [Chapco Support](https://chapco.ir)

## 🔧 Troubleshooting

### Pricing Configuration Issues

If you're experiencing issues with pricing configuration settings not saving or displaying correctly, we've created comprehensive debugging tools:

1. **Diagnostic Tool** - Upload `tabesh-diagnostic.php` to your WordPress root and access it via browser to identify issues
2. **Enhanced Logging** - Enable WP_DEBUG to see detailed logs in `wp-content/debug.log`
3. **Console Debugging** - Open browser console (F12) when saving settings to see real-time feedback

📖 **Complete Guide:** See [PRICING_TROUBLESHOOTING.md](PRICING_TROUBLESHOOTING.md) for detailed instructions

📋 **Fix Summary:** See [FIX_SUMMARY_PRICING.md](FIX_SUMMARY_PRICING.md) for complete technical details

### Common Issues

- **Settings disappear after save**: Check debug logs and ensure database permissions are correct
- **Fields not displaying**: Clear browser cache and verify WooCommerce is active
- **Price calculation errors**: Verify all pricing fields are filled in correct format (`key=value`)

## 🗺️ Roadmap

### Planned Features
- [ ] GUI-based pricing configuration
- [ ] Multiple product types (posters, cards, etc.)
- [ ] Advanced reporting and analytics
- [ ] File upload management
- [ ] Integration with more SMS providers
- [ ] Multi-language support
- [ ] Payment gateway integration
- [ ] Automated invoice generation
- [ ] Customer file requirements checker

## 📜 Changelog

### Version 1.0.0 (2024)
- Initial release
- Core order management system
- Price calculation engine
- SMS notifications via MelliPayamak
- Admin, Staff, and User panels
- WooCommerce integration
- RTL support for Persian
- Responsive design

---

Made with ❤️ for the Persian printing industry
