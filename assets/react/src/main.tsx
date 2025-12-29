/**
 * Main Entry Point
 */
import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'
import './styles/main.css'

// Ensure tabeshConfig is available
if (!window.tabeshConfig) {
  console.error('Tabesh: Configuration not found. Make sure WordPress is loading the config.')
  window.tabeshConfig = {
    nonce: '',
    restUrl: '/wp-json/tabesh/v1',
    restNamespace: 'tabesh/v1',
    currentUserId: 0,
    currentUserRole: '',
    isAdmin: false,
    canEditOrders: false,
    avatarUrl: '',
    userName: '',
    userEmail: '',
  }
}

const root = document.getElementById('tabesh-admin-dashboard-root')

if (root) {
  ReactDOM.createRoot(root).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  )
} else {
  console.error('Tabesh: Root element not found')
}
