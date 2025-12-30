/**
 * Admin Order Form Component
 * 
 * A comprehensive form for administrators to create orders on behalf of customers
 * Includes customer search/creation, order details, price calculation, and submission
 */
import React, { useState, useEffect } from 'react'
import { useNotifications } from '@/contexts/NotificationsContext'
import { adminOrderFormService } from '@/services/adminOrderForm'
import { Button, Loading } from '@/components/UI'
import { CustomerSection } from './CustomerSection'
import { OrderDetailsSection } from './OrderDetailsSection'
import { PriceFooter } from './PriceFooter'
import type { OrderFormData, FormConfig, PriceCalculation } from '@/types/orderForm'

interface AdminOrderFormProps {
  onSuccess?: (orderId: number, orderNumber: string) => void
  onCancel?: () => void
}

export const AdminOrderForm: React.FC<AdminOrderFormProps> = ({ onSuccess, onCancel }) => {
  const { addNotification } = useNotifications()
  
  // Form configuration
  const [formConfig, setFormConfig] = useState<FormConfig | null>(null)
  const [isLoadingConfig, setIsLoadingConfig] = useState(true)
  
  // Form data
  const [formData, setFormData] = useState<Partial<OrderFormData>>({
    customer_type: 'existing',
    quantity: 10,
    send_order_sms: true,
    send_registration_sms: true,
  })
  
  // Price calculation
  const [priceData, setPriceData] = useState<PriceCalculation | null>(null)
  const [isCalculating, setIsCalculating] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  
  // Load form configuration on mount
  useEffect(() => {
    loadFormConfig()
  }, [])
  
  const loadFormConfig = async () => {
    setIsLoadingConfig(true)
    const response = await adminOrderFormService.getFormConfig()
    
    if (response.success && response.data) {
      setFormConfig(response.data)
      // Set initial quantity from config
      setFormData(prev => ({
        ...prev,
        quantity: response.data!.min_quantity,
      }))
    } else {
      addNotification('error', response.error || 'خطا در بارگذاری تنظیمات فرم')
    }
    
    setIsLoadingConfig(false)
  }
  
  const handleFieldChange = (field: keyof OrderFormData, value: any) => {
    setFormData(prev => ({
      ...prev,
      [field]: value,
    }))
  }
  
  const handleCalculatePrice = async () => {
    // Validate required fields
    if (!validateRequiredFields()) {
      addNotification('error', 'لطفاً تمام فیلدهای الزامی را پر کنید')
      return
    }
    
    setIsCalculating(true)
    const response = await adminOrderFormService.calculatePrice(formData)
    
    if (response.success && response.data) {
      setPriceData(response.data)
      addNotification('success', 'قیمت با موفقیت محاسبه شد')
    } else {
      addNotification('error', response.error || 'خطا در محاسبه قیمت')
    }
    
    setIsCalculating(false)
  }
  
  const validateRequiredFields = (): boolean => {
    const required = [
      'user_id',
      'book_title',
      'book_size',
      'paper_type',
      'paper_weight',
      'print_type',
      'binding_type',
      'license_type',
      'quantity',
    ]
    
    for (const field of required) {
      if (!formData[field as keyof OrderFormData]) {
        return false
      }
    }
    
    // Check page counts based on print type
    if (formData.print_type === 'رنگی و سیاه‌وسفید') {
      if (!formData.page_count_color || !formData.page_count_bw) {
        return false
      }
    } else if (!formData.page_count_total) {
      return false
    }
    
    return true
  }
  
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    // Validate
    if (!validateRequiredFields()) {
      addNotification('error', 'لطفاً تمام فیلدهای الزامی را پر کنید')
      return
    }
    
    if (!formData.user_id) {
      addNotification('error', 'لطفاً یک مشتری را انتخاب یا ایجاد کنید')
      return
    }
    
    setIsSubmitting(true)
    const response = await adminOrderFormService.submitOrder(formData as OrderFormData)
    
    if (response.success && response.data) {
      addNotification('success', 'سفارش با موفقیت ثبت شد')
      
      if (onSuccess) {
        onSuccess(response.data.order_id, response.data.order_number)
      }
      
      // Reset form
      resetForm()
    } else {
      addNotification('error', response.error || 'خطا در ثبت سفارش')
    }
    
    setIsSubmitting(false)
  }
  
  const resetForm = () => {
    setFormData({
      customer_type: 'existing',
      quantity: formConfig?.min_quantity || 10,
      send_order_sms: true,
      send_registration_sms: true,
    })
    setPriceData(null)
  }
  
  if (isLoadingConfig) {
    return (
      <div className="admin-order-form-loading">
        <Loading />
        <p>در حال بارگذاری فرم...</p>
      </div>
    )
  }
  
  if (!formConfig) {
    return (
      <div className="admin-order-form-error">
        <p>خطا در بارگذاری تنظیمات فرم</p>
        <Button onClick={loadFormConfig}>تلاش مجدد</Button>
      </div>
    )
  }
  
  return (
    <div className="tabesh-admin-order-form" dir="rtl">
      <form onSubmit={handleSubmit} className="aof-form">
        <CustomerSection
          formData={formData}
          onChange={handleFieldChange}
        />
        
        <OrderDetailsSection
          formData={formData}
          formConfig={formConfig}
          onChange={handleFieldChange}
        />
        
        <PriceFooter
          priceData={priceData}
          isCalculating={isCalculating}
          isSubmitting={isSubmitting}
          onCalculate={handleCalculatePrice}
          onSubmit={handleSubmit}
          onCancel={onCancel}
          overridePrice={formData.override_unit_price}
          onOverridePriceChange={(value) => handleFieldChange('override_unit_price', value)}
        />
      </form>
    </div>
  )
}
