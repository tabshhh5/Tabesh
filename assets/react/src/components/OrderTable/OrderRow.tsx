/**
 * Order Row Component
 */
import React from 'react'
import { Badge } from '@/components/UI'
import { formatCurrency, formatDate } from '@/utils/format'
import { ORDER_STATUS_LABELS } from '@/utils/constants'
import type { Order } from '@/types'

interface OrderRowProps {
  order: Order
  onSelect: (order: Order) => void
}

export const OrderRow: React.FC<OrderRowProps> = ({ order, onSelect }) => {
  const getStatusVariant = (status: string) => {
    switch (status) {
      case 'completed':
        return 'success'
      case 'cancelled':
        return 'danger'
      case 'processing':
        return 'info'
      case 'pending':
        return 'warning'
      default:
        return 'default'
    }
  }

  return (
    <tr className="order-row" onClick={() => onSelect(order)}>
      <td>{order.serial_number}</td>
      <td>{order.book_title || '-'}</td>
      <td>{order.customer_name}</td>
      <td>{formatCurrency(order.total_price)}</td>
      <td>
        <Badge variant={getStatusVariant(order.status)}>
          {ORDER_STATUS_LABELS[order.status]}
        </Badge>
      </td>
      <td>{formatDate(order.created_at)}</td>
    </tr>
  )
}
