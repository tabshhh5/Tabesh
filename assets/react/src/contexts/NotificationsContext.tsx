/**
 * Notifications Context - Toast notifications management
 */
import React, { createContext, useContext, useState, useCallback } from 'react'
import type { ToastNotification } from '@/types'

interface NotificationsContextType {
  notifications: ToastNotification[]
  addNotification: (
    type: ToastNotification['type'],
    message: string,
    duration?: number
  ) => void
  removeNotification: (id: string) => void
}

const NotificationsContext = createContext<NotificationsContextType | undefined>(
  undefined
)

export const useNotifications = () => {
  const context = useContext(NotificationsContext)
  if (!context) {
    throw new Error(
      'useNotifications must be used within NotificationsProvider'
    )
  }
  return context
}

export const NotificationsProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const [notifications, setNotifications] = useState<ToastNotification[]>([])

  const addNotification = useCallback(
    (
      type: ToastNotification['type'],
      message: string,
      duration = 3000
    ) => {
      const id = `toast-${Date.now()}-${Math.random()}`
      const notification: ToastNotification = {
        id,
        type,
        message,
        duration,
      }

      setNotifications((prev) => [...prev, notification])

      if (duration > 0) {
        setTimeout(() => {
          removeNotification(id)
        }, duration)
      }
    },
    []
  )

  const removeNotification = useCallback((id: string) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id))
  }, [])

  return (
    <NotificationsContext.Provider
      value={{ notifications, addNotification, removeNotification }}
    >
      {children}
    </NotificationsContext.Provider>
  )
}
