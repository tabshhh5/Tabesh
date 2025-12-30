/**
 * Dashboard Component - Main container
 */
import React, { useState } from 'react'
import { Button, Modal } from '@/components/UI'
import { Statistics } from '@/components/Statistics'
import { FTPStatus } from '@/components/FTPStatus'
import { Filters } from '@/components/Filters'
import { OrderTable } from '@/components/OrderTable'
import { AdminOrderForm } from '@/components/AdminOrderForm'
import { useTheme } from '@/contexts/ThemeContext'
import { useNotifications } from '@/contexts/NotificationsContext'
import type { FilterOptions } from '@/types'

export const Dashboard: React.FC = () => {
  const { theme, toggleTheme } = useTheme()
  const { addNotification } = useNotifications()
  const [filters, setFilters] = useState<Partial<FilterOptions>>({
    sortBy: 'newest',
  })
  const [isOrderFormOpen, setIsOrderFormOpen] = useState(false)

  const handleResetFilters = () => {
    setFilters({ sortBy: 'newest' })
  }

  const handleOrderSuccess = (_orderId: number, orderNumber: string) => {
    addNotification('success', `سفارش ${orderNumber} با موفقیت ثبت شد`)
    setIsOrderFormOpen(false)
    // Optionally refresh orders table
  }

  return (
    <div className="tabesh-admin-dashboard" dir="rtl">
      <div className="dashboard-header">
        <div className="dashboard-header-content">
          <h1>سوپر پنل مدیریت سفارشات</h1>
          <p className="dashboard-subtitle">داشبورد مدیریت تابش</p>
        </div>
        <div className="dashboard-actions">
          <Button
            variant="primary"
            onClick={() => setIsOrderFormOpen(true)}
            title="ثبت سفارش جدید"
          >
            📝 ثبت سفارش جدید
          </Button>
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

      {/* Admin Order Form Modal */}
      <Modal
        isOpen={isOrderFormOpen}
        onClose={() => setIsOrderFormOpen(false)}
        title="ثبت سفارش جدید"
        size="lg"
      >
        <AdminOrderForm
          onSuccess={handleOrderSuccess}
          onCancel={() => setIsOrderFormOpen(false)}
        />
      </Modal>
    </div>
  )
}
