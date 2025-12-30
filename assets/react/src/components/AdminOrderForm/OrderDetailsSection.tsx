/**
 * Order Details Section Component
 * 
 * Contains all order specification fields
 */
import React, { useEffect } from 'react'
import { Input } from '@/components/UI'
import type { OrderFormData, FormConfig } from '@/types/orderForm'

interface OrderDetailsSectionProps {
  formData: Partial<OrderFormData>
  formConfig: FormConfig
  onChange: (field: keyof OrderFormData, value: any) => void
}

export const OrderDetailsSection: React.FC<OrderDetailsSectionProps> = ({
  formData,
  formConfig,
  onChange,
}) => {
  // Handle paper type change to update available weights
  const [availableWeights, setAvailableWeights] = React.useState<string[]>([])
  
  useEffect(() => {
    if (formData.paper_type && formConfig.paper_types[formData.paper_type]) {
      setAvailableWeights(formConfig.paper_types[formData.paper_type])
      // Reset weight if not in new list
      if (formData.paper_weight && !formConfig.paper_types[formData.paper_type].includes(formData.paper_weight)) {
        onChange('paper_weight', '')
      }
    } else {
      setAvailableWeights([])
    }
  }, [formData.paper_type])
  
  // Handle print type change to show/hide page count fields
  const isMixedPrintType = formData.print_type === 'رنگی و سیاه‌وسفید'
  
  return (
    <section className="aof-section aof-section-order">
      <div className="aof-section-header">
        <span className="section-badge">۲</span>
        <span className="section-label">مشخصات سفارش</span>
      </div>
      
      {/* Book Title - Full Width */}
      <div className="aof-field aof-field-full">
        <label htmlFor="book-title">
          عنوان کتاب <span className="req">*</span>
        </label>
        <Input
          id="book-title"
          type="text"
          value={formData.book_title || ''}
          onChange={(e) => onChange('book_title', e.target.value)}
          placeholder="عنوان کتاب را وارد کنید"
          required
        />
      </div>
      
      {/* Grid Row 1: Basic Specs */}
      <div className="aof-grid">
        <div className="aof-field">
          <label htmlFor="book-size">
            قطع <span className="req">*</span>
          </label>
          <select
            id="book-size"
            value={formData.book_size || ''}
            onChange={(e) => onChange('book_size', e.target.value)}
            required
            className="tabesh-aof-select"
          >
            <option value="">انتخاب...</option>
            {formConfig.book_sizes.map((size) => (
              <option key={size} value={size}>
                {size}
              </option>
            ))}
          </select>
        </div>
        
        <div className="aof-field">
          <label htmlFor="paper-type">
            نوع کاغذ <span className="req">*</span>
          </label>
          <select
            id="paper-type"
            value={formData.paper_type || ''}
            onChange={(e) => onChange('paper_type', e.target.value)}
            required
            className="tabesh-aof-select"
          >
            <option value="">انتخاب...</option>
            {Object.keys(formConfig.paper_types).map((type) => (
              <option key={type} value={type}>
                {type}
              </option>
            ))}
          </select>
        </div>
        
        <div className="aof-field">
          <label htmlFor="paper-weight">
            گرماژ <span className="req">*</span>
          </label>
          <select
            id="paper-weight"
            value={formData.paper_weight || ''}
            onChange={(e) => onChange('paper_weight', e.target.value)}
            required
            disabled={!formData.paper_type}
            className="tabesh-aof-select"
          >
            <option value="">
              {formData.paper_type ? 'انتخاب...' : 'ابتدا نوع کاغذ'}
            </option>
            {availableWeights.map((weight) => (
              <option key={weight} value={weight}>
                {weight}
              </option>
            ))}
          </select>
        </div>
        
        <div className="aof-field">
          <label htmlFor="print-type">
            نوع چاپ <span className="req">*</span>
          </label>
          <select
            id="print-type"
            value={formData.print_type || ''}
            onChange={(e) => onChange('print_type', e.target.value)}
            required
            className="tabesh-aof-select"
          >
            <option value="">انتخاب...</option>
            {formConfig.print_types.map((type) => (
              <option key={type} value={type}>
                {type}
              </option>
            ))}
          </select>
        </div>
        
        <div className="aof-field">
          <label htmlFor="binding-type">
            صحافی <span className="req">*</span>
          </label>
          <select
            id="binding-type"
            value={formData.binding_type || ''}
            onChange={(e) => onChange('binding_type', e.target.value)}
            required
            className="tabesh-aof-select"
          >
            <option value="">انتخاب...</option>
            {formConfig.binding_types.map((type) => (
              <option key={type} value={type}>
                {type}
              </option>
            ))}
          </select>
        </div>
        
        <div className="aof-field">
          <label htmlFor="license-type">
            مجوز <span className="req">*</span>
          </label>
          <select
            id="license-type"
            value={formData.license_type || ''}
            onChange={(e) => onChange('license_type', e.target.value)}
            required
            className="tabesh-aof-select"
          >
            <option value="">انتخاب...</option>
            {formConfig.license_types.map((type) => (
              <option key={type} value={type}>
                {type}
              </option>
            ))}
          </select>
        </div>
      </div>
      
      {/* Grid Row 2: Quantity and Pages */}
      <div className="aof-grid">
        <div className="aof-field">
          <label htmlFor="quantity">
            تیراژ <span className="req">*</span>
          </label>
          <Input
            id="quantity"
            type="number"
            value={formData.quantity || formConfig.min_quantity}
            onChange={(e) => onChange('quantity', parseInt(e.target.value))}
            min={formConfig.min_quantity}
            max={formConfig.max_quantity}
            step={formConfig.quantity_step}
            required
          />
        </div>
        
        {!isMixedPrintType && (
          <div className="aof-field">
            <label htmlFor="page-count-total">
              تعداد صفحات <span className="req">*</span>
            </label>
            <Input
              id="page-count-total"
              type="number"
              value={formData.page_count_total || ''}
              onChange={(e) => onChange('page_count_total', parseInt(e.target.value))}
              min={1}
              required
            />
          </div>
        )}
        
        {isMixedPrintType && (
          <>
            <div className="aof-field">
              <label htmlFor="page-count-color">
                صفحات رنگی <span className="req">*</span>
              </label>
              <Input
                id="page-count-color"
                type="number"
                value={formData.page_count_color || 0}
                onChange={(e) => onChange('page_count_color', parseInt(e.target.value))}
                min={0}
                required
              />
            </div>
            
            <div className="aof-field">
              <label htmlFor="page-count-bw">
                صفحات سیاه‌وسفید <span className="req">*</span>
              </label>
              <Input
                id="page-count-bw"
                type="number"
                value={formData.page_count_bw || 0}
                onChange={(e) => onChange('page_count_bw', parseInt(e.target.value))}
                min={0}
                required
              />
            </div>
          </>
        )}
        
        <div className="aof-field">
          <label htmlFor="cover-paper-weight">گرماژ جلد</label>
          <select
            id="cover-paper-weight"
            value={formData.cover_paper_weight || ''}
            onChange={(e) => onChange('cover_paper_weight', e.target.value)}
            className="tabesh-aof-select"
          >
            <option value="">انتخاب...</option>
            {formConfig.cover_paper_weights.map((weight) => (
              <option key={weight} value={weight}>
                {weight}
              </option>
            ))}
          </select>
        </div>
        
        <div className="aof-field">
          <label htmlFor="lamination-type">سلفون</label>
          <select
            id="lamination-type"
            value={formData.lamination_type || ''}
            onChange={(e) => onChange('lamination_type', e.target.value)}
            className="tabesh-aof-select"
          >
            <option value="">انتخاب...</option>
            {formConfig.lamination_types.map((type) => (
              <option key={type} value={type}>
                {type}
              </option>
            ))}
          </select>
        </div>
      </div>
      
      {/* Extras and Notes */}
      <div className="aof-extras-row">
        {formConfig.extras.length > 0 && (
          <div className="aof-extras">
            <span className="extras-label">آپشن‌ها:</span>
            <div className="aof-checkbox-group">
              {formConfig.extras.map((extra) => (
                <label key={extra} className="aof-chip">
                  <input
                    type="checkbox"
                    checked={formData.extras?.includes(extra) || false}
                    onChange={(e) => {
                      const currentExtras = formData.extras || []
                      if (e.target.checked) {
                        onChange('extras', [...currentExtras, extra])
                      } else {
                        onChange('extras', currentExtras.filter((x) => x !== extra))
                      }
                    }}
                  />
                  <span className="chip-text">{extra}</span>
                </label>
              ))}
            </div>
          </div>
        )}
        
        <div className="aof-notes">
          <textarea
            id="notes"
            value={formData.notes || ''}
            onChange={(e) => onChange('notes', e.target.value)}
            className="aof-textarea"
            placeholder="یادداشت (اختیاری)..."
          />
        </div>
      </div>
      
      {/* SMS Options */}
      <div className="aof-sms-options">
        <h4>ارسال پیامک</h4>
        <div className="aof-checkbox-group">
          {formData.customer_type === 'new' && (
            <label>
              <input
                type="checkbox"
                checked={formData.send_registration_sms || false}
                onChange={(e) => onChange('send_registration_sms', e.target.checked)}
              />
              ارسال پیامک ثبت‌نام به کاربر جدید
            </label>
          )}
          <label>
            <input
              type="checkbox"
              checked={formData.send_order_sms || false}
              onChange={(e) => onChange('send_order_sms', e.target.checked)}
            />
            ارسال پیامک ثبت سفارش به مشتری
          </label>
        </div>
      </div>
    </section>
  )
}
