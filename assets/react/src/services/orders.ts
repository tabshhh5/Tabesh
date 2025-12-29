/**
 * Orders API Service
 */
import { apiClient } from './api'
import type { Order, FilterOptions, PaginationInfo, ApiResponse } from '@/types'

export interface OrdersListResponse {
  orders: Order[]
  pagination: PaginationInfo
}

export const ordersService = {
  /**
   * Get list of orders with filters
   */
  async getOrders(
    filters: Partial<FilterOptions>,
    page = 1,
    perPage = 20
  ): Promise<ApiResponse<OrdersListResponse>> {
    return apiClient.get<OrdersListResponse>('/orders', {
      ...filters,
      page,
      per_page: perPage,
    })
  },

  /**
   * Get single order details
   */
  async getOrder(orderId: number): Promise<ApiResponse<Order>> {
    return apiClient.get<Order>(`/orders/${orderId}`)
  },

  /**
   * Update order status
   */
  async updateStatus(
    orderId: number,
    newStatus: string,
    notes?: string
  ): Promise<ApiResponse<Order>> {
    return apiClient.post<Order>('/update-status', {
      order_id: orderId,
      new_status: newStatus,
      notes,
    })
  },

  /**
   * Search orders
   */
  async searchOrders(
    query: string,
    filters?: Partial<FilterOptions>
  ): Promise<ApiResponse<Order[]>> {
    return apiClient.get<Order[]>('/staff/search-orders', {
      q: query,
      ...filters,
    })
  },

  /**
   * Archive order
   */
  async archiveOrder(orderId: number): Promise<ApiResponse<Order>> {
    return apiClient.post<Order>('/archive-order', {
      order_id: orderId,
    })
  },

  /**
   * Restore archived order
   */
  async restoreOrder(orderId: number): Promise<ApiResponse<Order>> {
    return apiClient.post<Order>('/restore-order', {
      order_id: orderId,
    })
  },

  /**
   * Delete order
   */
  async deleteOrder(orderId: number): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/orders/${orderId}`)
  },
}
