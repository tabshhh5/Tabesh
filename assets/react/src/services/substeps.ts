/**
 * Print Substeps API Service
 */
import { apiClient } from './api'
import type { PrintSubstep, ApiResponse } from '@/types'

export const substepsService = {
  /**
   * Get substeps for order
   */
  async getSubsteps(orderId: number): Promise<ApiResponse<PrintSubstep[]>> {
    return apiClient.get<PrintSubstep[]>(`/print-substeps/${orderId}`)
  },

  /**
   * Update substep status
   */
  async updateSubstep(
    orderId: number,
    substepKey: string,
    isCompleted: boolean
  ): Promise<ApiResponse<PrintSubstep>> {
    return apiClient.post<PrintSubstep>('/print-substeps/update', {
      order_id: orderId,
      substep_key: substepKey,
      is_completed: isCompleted,
    })
  },
}
