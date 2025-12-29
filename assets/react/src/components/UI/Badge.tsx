/**
 * Badge Component
 */
import React from 'react'
import { cn } from '@/utils/cn'

interface BadgeProps {
  children: React.ReactNode
  variant?: 'default' | 'success' | 'warning' | 'danger' | 'info'
  className?: string
}

export const Badge: React.FC<BadgeProps> = ({
  children,
  variant = 'default',
  className,
}) => {
  const variantClasses = {
    default: 'badge-default',
    success: 'badge-success',
    warning: 'badge-warning',
    danger: 'badge-danger',
    info: 'badge-info',
  }

  return (
    <span className={cn('badge', variantClasses[variant], className)}>
      {children}
    </span>
  )
}
