/**
 * Toast Notification Component
 */
import React from 'react'
import { useNotifications } from '@/contexts/NotificationsContext'
import { cn } from '@/utils/cn'

export const ToastContainer: React.FC = () => {
  const { notifications, removeNotification } = useNotifications()

  const iconMap = {
    success: '✓',
    error: '✕',
    warning: '⚠',
    info: 'ℹ',
  }

  const colorMap = {
    success: 'toast-success',
    error: 'toast-error',
    warning: 'toast-warning',
    info: 'toast-info',
  }

  return (
    <div className="toast-container">
      {notifications.map((notification) => (
        <div
          key={notification.id}
          className={cn('toast', colorMap[notification.type])}
        >
          <span className="toast-icon">{iconMap[notification.type]}</span>
          <span className="toast-message">{notification.message}</span>
          <button
            className="toast-close"
            onClick={() => removeNotification(notification.id)}
          >
            ×
          </button>
        </div>
      ))}
    </div>
  )
}
