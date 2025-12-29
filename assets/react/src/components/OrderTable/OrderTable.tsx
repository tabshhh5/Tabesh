/**
 * Order Table Component
 */
import React, { useState } from 'react'
import { useOrders } from '@/hooks/useOrders'
import { Card, Loading, Button } from '@/components/UI'
import { OrderRow } from './OrderRow'
import { OrderDetails } from '@/components/OrderDetails'
import type { FilterOptions, Order } from '@/types'

interface OrderTableProps {
  filters: Partial<FilterOptions>
}

export const OrderTable: React.FC<OrderTableProps> = ({ filters }) => {
  const [currentPage, setCurrentPage] = useState(1)
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null)
  const { data, isLoading, isError } = useOrders(filters, currentPage, 20)

  if (isLoading) {
    return (
      <Card>
        <Loading />
      </Card>
    )
  }

  if (isError || !data?.success || !data.data) {
    return (
      <Card>
        <div className="error-message">خطا در دریافت لیست سفارشات</div>
      </Card>
    )
  }

  const { orders, pagination } = data.data

  return (
    <>
      <Card padding={false}>
        <div className="table-container">
          <table className="orders-table">
            <thead>
              <tr>
                <th>شماره</th>
                <th>عنوان کتاب</th>
                <th>مشتری</th>
                <th>قیمت</th>
                <th>وضعیت</th>
                <th>تاریخ ثبت</th>
              </tr>
            </thead>
            <tbody>
              {orders.length === 0 ? (
                <tr>
                  <td colSpan={6} className="empty-state">
                    سفارشی یافت نشد
                  </td>
                </tr>
              ) : (
                orders.map((order) => (
                  <OrderRow
                    key={order.id}
                    order={order}
                    onSelect={setSelectedOrder}
                  />
                ))
              )}
            </tbody>
          </table>
        </div>

        {pagination.totalPages > 1 && (
          <div className="pagination">
            <Button
              size="sm"
              disabled={currentPage === 1}
              onClick={() => setCurrentPage((p) => p - 1)}
            >
              قبلی
            </Button>
            <span className="pagination-info">
              صفحه {pagination.currentPage} از {pagination.totalPages}
            </span>
            <Button
              size="sm"
              disabled={currentPage === pagination.totalPages}
              onClick={() => setCurrentPage((p) => p + 1)}
            >
              بعدی
            </Button>
          </div>
        )}
      </Card>

      {selectedOrder && (
        <OrderDetails
          order={selectedOrder}
          onClose={() => setSelectedOrder(null)}
        />
      )}
    </>
  )
}
