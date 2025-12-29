/**
 * Custom hooks for statistics data fetching
 */
import { useQuery } from 'react-query'
import { statisticsService } from '@/services/statistics'

export const useStatistics = (dateFrom?: string, dateTo?: string) => {
  return useQuery(
    ['statistics', dateFrom, dateTo],
    () => {
      if (dateFrom && dateTo) {
        return statisticsService.getStatisticsByDateRange(dateFrom, dateTo)
      }
      return statisticsService.getStatistics()
    },
    {
      staleTime: 60000, // 1 minute
      refetchInterval: 300000, // Refresh every 5 minutes
    }
  )
}
