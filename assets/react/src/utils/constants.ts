/**
 * Constants for the application
 */

export const ORDER_STATUS_LABELS: Record<string, string> = {
  pending: 'در انتظار بررسی',
  confirmed: 'تایید شده',
  processing: 'در حال چاپ',
  ready: 'آماده تحویل',
  completed: 'تحویل داده شده',
  cancelled: 'لغو شده',
}

export const ORDER_STATUS_COLORS: Record<string, string> = {
  pending: '#f39c12',
  confirmed: '#3498db',
  processing: '#9b59b6',
  ready: '#1abc9c',
  completed: '#27ae60',
  cancelled: '#e74c3c',
}

export const ORDER_STATUS_PROGRESS: Record<string, number> = {
  pending: 10,
  confirmed: 25,
  processing: 50,
  ready: 80,
  completed: 100,
  cancelled: 0,
}

export const ITEMS_PER_PAGE = 20

export const SEARCH_DEBOUNCE_MS = 500

export const TOAST_DURATION = 3000
