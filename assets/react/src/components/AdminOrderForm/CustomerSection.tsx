/**
 * Customer Section Component
 * 
 * Handles customer selection (existing) or creation (new)
 */
import React, { useState, useEffect } from 'react'
import { useNotifications } from '@/contexts/NotificationsContext'
import { adminOrderFormService } from '@/services/adminOrderForm'
import { Button, Input } from '@/components/UI'
import type { OrderFormData, Customer } from '@/types/orderForm'

interface CustomerSectionProps {
  formData: Partial<OrderFormData>
  onChange: (field: keyof OrderFormData, value: any) => void
}

export const CustomerSection: React.FC<CustomerSectionProps> = ({ formData, onChange }) => {
  const { addNotification } = useNotifications()
  
  const [searchQuery, setSearchQuery] = useState('')
  const [searchResults, setSearchResults] = useState<Customer[]>([])
  const [isSearching, setIsSearching] = useState(false)
  const [selectedCustomer, setSelectedCustomer] = useState<Customer | null>(null)
  const [isCreatingCustomer, setIsCreatingCustomer] = useState(false)
  
  // Debounce search
  useEffect(() => {
    if (formData.customer_type === 'existing' && searchQuery.length >= 2) {
      const timer = setTimeout(() => {
        handleSearch()
      }, 300)
      
      return () => clearTimeout(timer)
    } else {
      setSearchResults([])
    }
  }, [searchQuery, formData.customer_type])
  
  const handleSearch = async () => {
    setIsSearching(true)
    const response = await adminOrderFormService.searchCustomers(searchQuery)
    
    if (response.success && response.data) {
      setSearchResults(response.data)
    } else {
      addNotification('error', response.error || 'خطا در جستجو')
      setSearchResults([])
    }
    
    setIsSearching(false)
  }
  
  const handleSelectCustomer = (customer: Customer) => {
    setSelectedCustomer(customer)
    onChange('user_id', customer.ID)
    setSearchQuery('')
    setSearchResults([])
  }
  
  const handleCreateCustomer = async () => {
    if (!formData.new_mobile || !formData.new_first_name || !formData.new_last_name) {
      addNotification('error', 'لطفاً تمام فیلدها را پر کنید')
      return
    }
    
    // Validate mobile format
    const mobilePattern = /^09[0-9]{9}$/
    if (!mobilePattern.test(formData.new_mobile)) {
      addNotification('error', 'فرمت شماره موبایل نامعتبر است')
      return
    }
    
    setIsCreatingCustomer(true)
    const response = await adminOrderFormService.createCustomer({
      mobile: formData.new_mobile,
      first_name: formData.new_first_name,
      last_name: formData.new_last_name,
    })
    
    if (response.success && response.data) {
      addNotification('success', 'کاربر با موفقیت ایجاد شد')
      setSelectedCustomer(response.data)
      onChange('user_id', response.data.ID)
      
      // Show registration SMS option
      onChange('send_registration_sms', true)
    } else {
      addNotification('error', response.error || 'خطا در ایجاد کاربر')
    }
    
    setIsCreatingCustomer(false)
  }
  
  return (
    <section className="aof-section aof-section-customer">
      <div className="aof-section-header">
        <span className="section-badge">۱</span>
        <span className="section-label">مشتری</span>
      </div>
      
      <div className="aof-customer-row">
        {/* Customer type toggle */}
        <div className="aof-toggle-group">
          <label className={`aof-toggle ${formData.customer_type === 'existing' ? 'active' : ''}`}>
            <input
              type="radio"
              name="customer_type"
              value="existing"
              checked={formData.customer_type === 'existing'}
              onChange={(e) => onChange('customer_type', e.target.value as 'existing' | 'new')}
            />
            <span className="toggle-text">موجود</span>
          </label>
          <label className={`aof-toggle ${formData.customer_type === 'new' ? 'active' : ''}`}>
            <input
              type="radio"
              name="customer_type"
              value="new"
              checked={formData.customer_type === 'new'}
              onChange={(e) => onChange('customer_type', e.target.value as 'existing' | 'new')}
            />
            <span className="toggle-text">جدید</span>
          </label>
        </div>
        
        {/* Existing customer search */}
        {formData.customer_type === 'existing' && (
          <div className="aof-customer-fields">
            {!selectedCustomer ? (
              <div className="aof-search-box">
                <span className="search-icon">🔍</span>
                <Input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="جستجوی نام، موبایل یا ایمیل..."
                  autoComplete="off"
                />
                
                {isSearching && <div className="search-loading">در حال جستجو...</div>}
                
                {searchResults.length > 0 && (
                  <div className="aof-search-results">
                    {searchResults.map((customer) => (
                      <div
                        key={customer.ID}
                        className="search-result-item"
                        onClick={() => handleSelectCustomer(customer)}
                      >
                        <div className="customer-name">{customer.display_name}</div>
                        <div className="customer-details">
                          {customer.user_email} • {customer.billing_phone || 'بدون شماره'}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
                
                {searchQuery.length >= 2 && !isSearching && searchResults.length === 0 && (
                  <div className="search-no-results">کاربری یافت نشد</div>
                )}
              </div>
            ) : (
              <div className="aof-selected-customer">
                <div className="selected-customer-info">
                  <span className="customer-name">{selectedCustomer.display_name}</span>
                  <span className="customer-email">{selectedCustomer.user_email}</span>
                  {selectedCustomer.billing_phone && (
                    <span className="customer-phone">{selectedCustomer.billing_phone}</span>
                  )}
                </div>
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => {
                    setSelectedCustomer(null)
                    onChange('user_id', 0)
                  }}
                >
                  تغییر مشتری
                </Button>
              </div>
            )}
          </div>
        )}
        
        {/* New customer creation */}
        {formData.customer_type === 'new' && (
          <div className="aof-customer-fields">
            <div className="aof-inline-fields">
              <Input
                type="tel"
                value={formData.new_mobile || ''}
                onChange={(e) => onChange('new_mobile', e.target.value)}
                placeholder="09xxxxxxxxx"
                pattern="09[0-9]{9}"
                dir="ltr"
              />
              <Input
                type="text"
                value={formData.new_first_name || ''}
                onChange={(e) => onChange('new_first_name', e.target.value)}
                placeholder="نام"
              />
              <Input
                type="text"
                value={formData.new_last_name || ''}
                onChange={(e) => onChange('new_last_name', e.target.value)}
                placeholder="نام خانوادگی"
              />
              <Button
                size="sm"
                onClick={handleCreateCustomer}
                disabled={isCreatingCustomer}
              >
                {isCreatingCustomer ? 'در حال ایجاد...' : 'ایجاد'}
              </Button>
            </div>
          </div>
        )}
      </div>
    </section>
  )
}
