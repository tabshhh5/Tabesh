/**
 * Dashboard Component - Main container
 */
import React, { useState } from 'react'
import { Button } from '@/components/UI'
import { Statistics } from '@/components/Statistics'
import { FTPStatus } from '@/components/FTPStatus'
import { Filters } from '@/components/Filters'
import { OrderTable } from '@/components/OrderTable'
import { useTheme } from '@/contexts/ThemeContext'
import type { FilterOptions } from '@/types'

export const Dashboard: React.FC = () => {
  const { theme, toggleTheme } = useTheme()
  const [filters, setFilters] = useState<Partial<FilterOptions>>({
    sortBy: 'newest',
  })

  const handleResetFilters = () => {
    setFilters({ sortBy: 'newest' })
  }

  return (
    <div className="tabesh-admin-dashboard" dir="rtl">
      <div className="dashboard-header">
        <h1>داشبورد تابش</h1>
        <div className="dashboard-actions">
          <Button
            variant="ghost"
            size="sm"
            onClick={toggleTheme}
            title={theme === 'light' ? 'تم تیره' : 'تم روشن'}
          >
            {theme === 'light' ? '🌙' : '☀️'}
          </Button>
        </div>
      </div>

      <FTPStatus />

      <Statistics />

      <div className="dashboard-content">
        <div className="dashboard-section">
          <h2>مدیریت سفارشات</h2>
          <Filters
            filters={filters}
            onChange={setFilters}
            onReset={handleResetFilters}
          />
          <OrderTable filters={filters} />
        </div>
      </div>
    </div>
  )
}
