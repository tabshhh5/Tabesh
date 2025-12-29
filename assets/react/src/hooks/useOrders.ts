/**
 * Custom hooks for orders data fetching
 */
import { useQuery, useMutation, useQueryClient } from 'react-query'
import { ordersService } from '@/services/orders'
import { useNotifications } from '@/contexts/NotificationsContext'
import type { FilterOptions } from '@/types'

export const useOrders = (
  filters: Partial<FilterOptions>,
  page = 1,
  perPage = 20
) => {
  return useQuery(
    ['orders', filters, page, perPage],
    () => ordersService.getOrders(filters, page, perPage),
    {
      keepPreviousData: true,
      staleTime: 30000, // 30 seconds
    }
  )
}

export const useOrder = (orderId: number) => {
  return useQuery(
    ['order', orderId],
    () => ordersService.getOrder(orderId),
    {
      enabled: orderId > 0,
    }
  )
}

export const useUpdateOrderStatus = () => {
  const queryClient = useQueryClient()
  const { addNotification } = useNotifications()

  return useMutation(
    ({ orderId, newStatus, notes }: {
      orderId: number
      newStatus: string
      notes?: string
    }) => ordersService.updateStatus(orderId, newStatus, notes),
    {
      onSuccess: () => {
        queryClient.invalidateQueries('orders')
        queryClient.invalidateQueries('statistics')
        addNotification('success', 'وضعیت سفارش با موفقیت به‌روزرسانی شد')
      },
      onError: (error: any) => {
        addNotification('error', error.error || 'خطا در به‌روزرسانی وضعیت')
      },
    }
  )
}

export const useSearchOrders = (query: string, filters?: Partial<FilterOptions>) => {
  return useQuery(
    ['searchOrders', query, filters],
    () => ordersService.searchOrders(query, filters),
    {
      enabled: query.length > 0,
      staleTime: 10000, // 10 seconds
    }
  )
}

export const useArchiveOrder = () => {
  const queryClient = useQueryClient()
  const { addNotification } = useNotifications()

  return useMutation(
    (orderId: number) => ordersService.archiveOrder(orderId),
    {
      onSuccess: () => {
        queryClient.invalidateQueries('orders')
        addNotification('success', 'سفارش با موفقیت بایگانی شد')
      },
      onError: (error: any) => {
        addNotification('error', error.error || 'خطا در بایگانی سفارش')
      },
    }
  )
}
