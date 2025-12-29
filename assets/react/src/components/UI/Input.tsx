/**
 * Input Component
 */
import React from 'react'
import { cn } from '@/utils/cn'

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
}

export const Input: React.FC<InputProps> = ({
  label,
  error,
  className,
  ...props
}) => {
  return (
    <div className="input-group">
      {label && <label className="input-label">{label}</label>}
      <input
        className={cn('input', error && 'input-error', className)}
        {...props}
      />
      {error && <span className="input-error-text">{error}</span>}
    </div>
  )
}
