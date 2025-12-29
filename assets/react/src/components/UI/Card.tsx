/**
 * Card Component
 */
import React from 'react'
import { cn } from '@/utils/cn'

interface CardProps {
  children: React.ReactNode
  className?: string
  padding?: boolean
}

export const Card: React.FC<CardProps> = ({
  children,
  className,
  padding = true,
}) => {
  return (
    <div className={cn('card', padding && 'card-padded', className)}>
      {children}
    </div>
  )
}
