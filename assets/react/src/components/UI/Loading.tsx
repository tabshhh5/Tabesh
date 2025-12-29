/**
 * Loading Spinner Component
 */
import React from 'react'
import { cn } from '@/utils/cn'

interface LoadingProps {
  size?: 'sm' | 'md' | 'lg'
  className?: string
}

export const Loading: React.FC<LoadingProps> = ({
  size = 'md',
  className,
}) => {
  const sizeClasses = {
    sm: 'spinner-sm',
    md: 'spinner-md',
    lg: 'spinner-lg',
  }

  return (
    <div className={cn('loading-container', className)}>
      <div className={cn('spinner', sizeClasses[size])}></div>
    </div>
  )
}
