/**
 * Order Details Component
 */
import React, { useState } from 'react'
import { Modal, Button, Select, Badge } from '@/components/UI'
import { useUpdateOrderStatus } from '@/hooks/useOrders'
import { formatCurrency, formatDateTime } from '@/utils/format'
import { ORDER_STATUS_LABELS } from '@/utils/constants'
import type { Order } from '@/types'

interface OrderDetailsProps {
  order: Order
  onClose: () => void
}

export const OrderDetails: React.FC<OrderDetailsProps> = ({
  order,
  onClose,
}) => {
  const [newStatus, setNewStatus] = useState(order.status)
  const [notes, setNotes] = useState('')
  const updateStatusMutation = useUpdateOrderStatus()

  const handleUpdateStatus = async () => {
    if (newStatus === order.status) return

    await updateStatusMutation.mutateAsync({
      orderId: order.id,
      newStatus,
      notes,
    })
    onClose()
  }

  const statusOptions = Object.entries(ORDER_STATUS_LABELS).map(
    ([value, label]) => ({
      value,
      label,
    })
  )

  return (
    <Modal
      isOpen={true}
      onClose={onClose}
      title={`جزئیات سفارش #${order.serial_number}`}
      size="lg"
    >
      <div className="order-details">
        <div className="order-details-section">
          <h3>اطلاعات کلی</h3>
          <div className="order-details-grid">
            <div>
              <strong>شماره سفارش:</strong> {order.order_number}
            </div>
            <div>
              <strong>عنوان کتاب:</strong> {order.book_title || '-'}
            </div>
            <div>
              <strong>مشتری:</strong> {order.customer_name}
            </div>
            <div>
              <strong>ایمیل:</strong> {order.customer_email}
            </div>
            <div>
              <strong>تاریخ ثبت:</strong> {formatDateTime(order.created_at)}
            </div>
            <div>
              <strong>وضعیت فعلی:</strong>{' '}
              <Badge>{ORDER_STATUS_LABELS[order.status]}</Badge>
            </div>
          </div>
        </div>

        <div className="order-details-section">
          <h3>مشخصات چاپ</h3>
          <div className="order-details-grid">
            <div>
              <strong>قطع:</strong> {order.book_size}
            </div>
            <div>
              <strong>نوع کاغذ:</strong> {order.paper_type}
            </div>
            <div>
              <strong>وزن کاغذ:</strong> {order.paper_weight}
            </div>
            <div>
              <strong>نوع چاپ:</strong> {order.print_type}
            </div>
            <div>
              <strong>تعداد صفحات:</strong> {order.page_count_total}
            </div>
            <div>
              <strong>تیراژ:</strong> {order.quantity}
            </div>
            <div>
              <strong>نوع صحافی:</strong> {order.binding_type}
            </div>
            <div>
              <strong>نوع مجوز:</strong> {order.license_type}
            </div>
          </div>
        </div>

        {order.cover_paper_type && (
          <div className="order-details-section">
            <h3>مشخصات جلد</h3>
            <div className="order-details-grid">
              <div>
                <strong>نوع کاغذ جلد:</strong> {order.cover_paper_type}
              </div>
              <div>
                <strong>وزن کاغذ جلد:</strong> {order.cover_paper_weight}
              </div>
              {order.lamination_type && (
                <div>
                  <strong>سلفون:</strong> {order.lamination_type}
                </div>
              )}
            </div>
          </div>
        )}

        {order.extras && order.extras.length > 0 && (
          <div className="order-details-section">
            <h3>خدمات اضافی</h3>
            <div className="extras-list">
              {order.extras.map((extra, index) => (
                <Badge key={index}>{extra}</Badge>
              ))}
            </div>
          </div>
        )}

        <div className="order-details-section">
          <h3>قیمت</h3>
          <div className="order-price">
            <strong>قیمت کل:</strong>{' '}
            <span className="price-value">
              {formatCurrency(order.total_price)}
            </span>
          </div>
        </div>

        {order.notes && (
          <div className="order-details-section">
            <h3>یادداشت‌ها</h3>
            <p>{order.notes}</p>
          </div>
        )}

        <div className="order-details-section">
          <h3>به‌روزرسانی وضعیت</h3>
          <div className="status-update-form">
            <Select
              options={statusOptions}
              value={newStatus}
              onChange={(e) => setNewStatus(e.target.value as any)}
              label="وضعیت جدید"
            />
            <textarea
              className="notes-textarea"
              placeholder="یادداشت (اختیاری)"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
            />
            <Button
              onClick={handleUpdateStatus}
              loading={updateStatusMutation.isLoading}
              disabled={newStatus === order.status}
            >
              به‌روزرسانی وضعیت
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  )
}
