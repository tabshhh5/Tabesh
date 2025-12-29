# Tabesh Admin Dashboard - React SPA

This directory contains the React code for the Tabesh order management dashboard.

## Installation & Setup

### Prerequisites
- Node.js 18.x or higher
- npm or yarn

### Install Dependencies
```bash
cd assets/react
npm install
```

### Run Development Server
```bash
npm run dev
```
The app will run on port 3000: http://localhost:3000

### Build for Production
```bash
npm run build
```
Build files will be created in `../dist/admin-dashboard`

### Run Tests
```bash
# Run all tests
npm test

# Run tests in watch mode
npm run test:watch

# Generate coverage report
npm run test:coverage
```

### Code Quality
```bash
# Run ESLint
npm run lint

# Auto-fix ESLint issues
npm run lint:fix

# Type check with TypeScript
npm run type-check
```

## Project Structure

```
src/
├── components/         # React components
│   ├── Dashboard/      # Main dashboard component
│   ├── OrderTable/     # Orders table
│   ├── OrderDetails/   # Order details
│   ├── Statistics/     # Statistics cards
│   ├── Filters/        # Order filters
│   ├── FTPStatus/      # FTP status
│   ├── Notifications/  # Toast notifications
│   └── UI/            # Base UI components (Button, Modal, etc.)
├── contexts/          # Context API for state management
├── hooks/            # Custom hooks
├── services/         # API services
├── types/            # TypeScript type definitions
├── utils/            # Utility functions
├── styles/           # CSS files
├── App.tsx           # Main App component
└── main.tsx          # Entry point
```

## Features

### Technical
- ✅ React 18 with TypeScript
- ✅ Vite for fast bundling
- ✅ React Query for data fetching & caching
- ✅ Context API for state management
- ✅ Axios for REST API communication
- ✅ Jest & React Testing Library for testing
- ✅ ESLint for code quality

### UI/UX
- ✅ Full RTL support
- ✅ Light and dark themes
- ✅ Responsive design
- ✅ Toast notifications
- ✅ Modal for order details
- ✅ Advanced filters
- ✅ Pagination

### Business Features
- ✅ View and manage orders
- ✅ Statistics and reporting
- ✅ Filter and search orders
- ✅ Update order status
- ✅ View complete order details
- ✅ Display FTP status

## WordPress Integration

The React dashboard is loaded via the `[tabesh_admin_dashboard]` shortcode in WordPress.

### WordPress Configuration
1. Built files are placed in `assets/dist/admin-dashboard`
2. PHP handler must enqueue JS and CSS files
3. Configuration is passed to React via `window.tabeshConfig`

Example configuration:
```javascript
window.tabeshConfig = {
  nonce: 'wp-nonce-here',
  restUrl: '/wp-json/tabesh/v1',
  restNamespace: 'tabesh/v1',
  currentUserId: 1,
  currentUserRole: 'administrator',
  isAdmin: true,
  canEditOrders: true,
  avatarUrl: 'https://...',
  userName: 'User Name',
  userEmail: 'email@example.com'
}
```

## Security

- ✅ Nonce verification for all API requests
- ✅ User permission checks
- ✅ Input sanitization
- ✅ Output escaping

## Contributing

To add a new feature:
1. Create a new component in the appropriate directory
2. Write tests for the component
3. Update documentation
4. Run ESLint

## License

GPL v2 or later
