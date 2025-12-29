/**
 * Type definitions for Tabesh Admin Dashboard
 */

export interface TabeshConfig {
  nonce: string
  restUrl: string
  restNamespace: string
  currentUserId: number
  currentUserRole: string
  isAdmin: boolean
  canEditOrders: boolean
  avatarUrl: string
  userName: string
  userEmail: string
}

export interface Order {
  id: number
  serial_number: number
  order_number: string
  book_title?: string
  user_id: number
  customer_name: string
  customer_email: string
  book_size: string
  paper_type: string
  paper_weight: string
  print_type: string
  page_count_color: number
  page_count_bw: number
  page_count_total: number
  quantity: number
  binding_type: string
  license_type: string
  cover_paper_type?: string
  cover_paper_weight?: string
  lamination_type?: string
  extras?: string[]
  total_price: number
  status: OrderStatus
  files?: OrderFile[]
  notes?: string
  created_at: string
  updated_at: string
  archived: boolean
  archived_at?: string
  substeps?: PrintSubstep[]
}

export type OrderStatus = 
  | 'pending'
  | 'confirmed'
  | 'processing'
  | 'ready'
  | 'completed'
  | 'cancelled'

export interface OrderFile {
  id: number
  order_id: number
  filename: string
  original_filename: string
  file_type: string
  file_size: number
  upload_status: string
  transfer_status?: string
  uploaded_at: string
  transferred_at?: string
  download_url?: string
}

export interface PrintSubstep {
  id: number
  order_id: number
  substep_key: string
  substep_label: string
  is_completed: boolean
  completed_at?: string
  completed_by?: number
}

export interface Statistics {
  total_orders: number
  pending_orders: number
  processing_orders: number
  completed_orders: number
  total_revenue: number
  average_order_value: number
}

export interface FTPStatus {
  connected: boolean
  message: string
  uptime?: string
  last_success?: string
}

export interface FilterOptions {
  status: string
  customer: string
  priceMin: string
  priceMax: string
  dateFrom: string
  dateTo: string
  sortBy: string
  sortOrder: 'asc' | 'desc'
}

export interface PaginationInfo {
  currentPage: number
  totalPages: number
  perPage: number
  totalItems: number
}

export interface ApiResponse<T> {
  success: boolean
  data?: T
  message?: string
  error?: string
}

export interface ToastNotification {
  id: string
  type: 'success' | 'error' | 'info' | 'warning'
  message: string
  duration?: number
}

// Window interface extension for WordPress data
declare global {
  interface Window {
    tabeshConfig: TabeshConfig
  }
}
