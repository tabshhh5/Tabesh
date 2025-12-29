/**
 * Filters Component for Orders
 */
import React from 'react'
import { Input, Select, Button } from '@/components/UI'
import { ORDER_STATUS_LABELS } from '@/utils/constants'
import type { FilterOptions } from '@/types'

interface FiltersProps {
  filters: Partial<FilterOptions>
  onChange: (filters: Partial<FilterOptions>) => void
  onReset: () => void
}

export const Filters: React.FC<FiltersProps> = ({
  filters,
  onChange,
  onReset,
}) => {
  const handleChange = (key: keyof FilterOptions, value: string) => {
    onChange({ ...filters, [key]: value })
  }

  const statusOptions = [
    { value: '', label: 'همه وضعیت‌ها' },
    ...Object.entries(ORDER_STATUS_LABELS).map(([value, label]) => ({
      value,
      label,
    })),
  ]

  const sortOptions = [
    { value: 'newest', label: 'جدیدترین' },
    { value: 'oldest', label: 'قدیمی‌ترین' },
    { value: 'price_high', label: 'گران‌ترین' },
    { value: 'price_low', label: 'ارزان‌ترین' },
  ]

  return (
    <div className="filters-container">
      <div className="filters-row">
        <Input
          type="text"
          placeholder="جستجو بر اساس مشتری..."
          value={filters.customer || ''}
          onChange={(e) => handleChange('customer', e.target.value)}
        />
        
        <Select
          options={statusOptions}
          value={filters.status || ''}
          onChange={(e) => handleChange('status', e.target.value)}
        />
        
        <Select
          options={sortOptions}
          value={filters.sortBy || 'newest'}
          onChange={(e) => handleChange('sortBy', e.target.value)}
        />
      </div>

      <div className="filters-row">
        <Input
          type="number"
          placeholder="حداقل قیمت"
          value={filters.priceMin || ''}
          onChange={(e) => handleChange('priceMin', e.target.value)}
        />
        
        <Input
          type="number"
          placeholder="حداکثر قیمت"
          value={filters.priceMax || ''}
          onChange={(e) => handleChange('priceMax', e.target.value)}
        />
        
        <Input
          type="date"
          placeholder="از تاریخ"
          value={filters.dateFrom || ''}
          onChange={(e) => handleChange('dateFrom', e.target.value)}
        />
        
        <Input
          type="date"
          placeholder="تا تاریخ"
          value={filters.dateTo || ''}
          onChange={(e) => handleChange('dateTo', e.target.value)}
        />
      </div>

      <div className="filters-actions">
        <Button variant="secondary" size="sm" onClick={onReset}>
          پاک کردن فیلترها
        </Button>
      </div>
    </div>
  )
}
