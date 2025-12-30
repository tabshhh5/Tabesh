/**
 * API Client for Tabesh REST API
 */
import axios, { AxiosInstance, AxiosError } from 'axios'
import type { ApiResponse } from '@/types'

class ApiClient {
  private client: AxiosInstance
  private nonce: string
  private restUrl: string

  constructor() {
    this.nonce = window.tabeshConfig?.nonce || ''
    this.restUrl = window.tabeshConfig?.restUrl || '/wp-json/tabesh/v1'

    this.client = axios.create({
      baseURL: this.restUrl,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.nonce,
      },
      withCredentials: true,
    })

    // Response interceptor for error handling
    this.client.interceptors.response.use(
      (response) => response,
      (error: AxiosError) => {
        console.error('API Error:', error)
        return Promise.reject(this.handleError(error))
      }
    )
  }

  private handleError(error: AxiosError): ApiResponse<null> {
    if (error.response) {
      // Server responded with error status
      const data = error.response.data as any
      return {
        success: false,
        error: data?.message || 'خطایی رخ داده است',
      }
    } else if (error.request) {
      // Request made but no response
      return {
        success: false,
        error: 'عدم دریافت پاسخ از سرور',
      }
    } else {
      // Something else happened
      return {
        success: false,
        error: error.message || 'خطای ناشناخته',
      }
    }
  }

  async get<T>(endpoint: string, params?: any): Promise<ApiResponse<T>> {
    try {
      const response = await this.client.get(endpoint, { params })
      return {
        success: true,
        data: response.data,
      }
    } catch (error) {
      return error as ApiResponse<T>
    }
  }

  async post<T>(endpoint: string, data?: any): Promise<ApiResponse<T>> {
    try {
      const response = await this.client.post(endpoint, data)
      return {
        success: true,
        data: response.data,
      }
    } catch (error) {
      return error as ApiResponse<T>
    }
  }

  async put<T>(endpoint: string, data?: any): Promise<ApiResponse<T>> {
    try {
      const response = await this.client.put(endpoint, data)
      return {
        success: true,
        data: response.data,
      }
    } catch (error) {
      return error as ApiResponse<T>
    }
  }

  async delete<T>(endpoint: string): Promise<ApiResponse<T>> {
    try {
      const response = await this.client.delete(endpoint)
      return {
        success: true,
        data: response.data,
      }
    } catch (error) {
      return error as ApiResponse<T>
    }
  }

  updateNonce(nonce: string) {
    this.nonce = nonce
    this.client.defaults.headers['X-WP-Nonce'] = nonce
  }
}

export const apiClient = new ApiClient()
