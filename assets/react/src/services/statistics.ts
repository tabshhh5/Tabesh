/**
 * Statistics API Service
 */
import { apiClient } from './api'
import type { Statistics, ApiResponse } from '@/types'

export const statisticsService = {
  /**
   * Get dashboard statistics
   */
  async getStatistics(): Promise<ApiResponse<Statistics>> {
    return apiClient.get<Statistics>('/statistics')
  },

  /**
   * Get statistics for date range
   */
  async getStatisticsByDateRange(
    dateFrom: string,
    dateTo: string
  ): Promise<ApiResponse<Statistics>> {
    return apiClient.get<Statistics>('/statistics', {
      date_from: dateFrom,
      date_to: dateTo,
    })
  },
}
