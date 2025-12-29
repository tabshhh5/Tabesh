/**
 * Statistics Cards Component
 */
import React from 'react'
import { useStatistics } from '@/hooks/useStatistics'
import { Card, Loading } from '@/components/UI'
import { formatNumber, formatCurrency } from '@/utils/format'

export const Statistics: React.FC = () => {
  const { data, isLoading, isError } = useStatistics()

  if (isLoading) {
    return (
      <div className="stats-grid">
        <Loading />
      </div>
    )
  }

  if (isError || !data?.success || !data.data) {
    return (
      <div className="stats-error">
        خطا در دریافت آمار
      </div>
    )
  }

  const stats = data.data

  const statCards = [
    {
      label: 'کل سفارشات فعال',
      value: formatNumber(stats.total_orders),
      icon: 'dashicons-cart',
      color: '#3498db',
    },
    {
      label: 'در انتظار بررسی',
      value: formatNumber(stats.pending_orders),
      icon: 'dashicons-clock',
      color: '#f39c12',
    },
    {
      label: 'در حال پردازش',
      value: formatNumber(stats.processing_orders),
      icon: 'dashicons-admin-customizer',
      color: '#9b59b6',
    },
    {
      label: 'تکمیل شده',
      value: formatNumber(stats.completed_orders),
      icon: 'dashicons-yes-alt',
      color: '#27ae60',
    },
    {
      label: 'درآمد کل',
      value: formatCurrency(stats.total_revenue),
      icon: 'dashicons-chart-line',
      color: '#1abc9c',
    },
    {
      label: 'میانگین ارزش سفارش',
      value: formatCurrency(stats.average_order_value),
      icon: 'dashicons-money-alt',
      color: '#e67e22',
    },
  ]

  return (
    <div className="stats-grid">
      {statCards.map((stat, index) => (
        <Card key={index} className="stat-card">
          <div className="stat-icon" style={{ background: stat.color }}>
            <span className={`dashicons ${stat.icon}`}></span>
          </div>
          <div className="stat-content">
            <div className="stat-label">{stat.label}</div>
            <div className="stat-value">{stat.value}</div>
          </div>
        </Card>
      ))}
    </div>
  )
}
