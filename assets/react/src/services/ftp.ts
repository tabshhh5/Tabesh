/**
 * FTP Status API Service
 */
import { apiClient } from './api'
import type { FTPStatus, ApiResponse } from '@/types'

export const ftpService = {
  /**
   * Get FTP connection status
   */
  async getStatus(): Promise<ApiResponse<FTPStatus>> {
    return apiClient.get<FTPStatus>('/ftp/status')
  },

  /**
   * Refresh FTP connection
   */
  async refreshConnection(): Promise<ApiResponse<FTPStatus>> {
    return apiClient.post<FTPStatus>('/ftp/refresh')
  },
}
