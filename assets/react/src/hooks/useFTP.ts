/**
 * Custom hooks for FTP status
 */
import { useQuery, useMutation, useQueryClient } from 'react-query'
import { ftpService } from '@/services/ftp'
import { useNotifications } from '@/contexts/NotificationsContext'

export const useFTPStatus = () => {
  return useQuery(
    'ftpStatus',
    () => ftpService.getStatus(),
    {
      staleTime: 30000, // 30 seconds
      refetchInterval: 60000, // Refresh every minute
    }
  )
}

export const useRefreshFTP = () => {
  const queryClient = useQueryClient()
  const { addNotification } = useNotifications()

  return useMutation(
    () => ftpService.refreshConnection(),
    {
      onSuccess: () => {
        queryClient.invalidateQueries('ftpStatus')
        addNotification('success', 'اتصال FTP بروزرسانی شد')
      },
      onError: (error: any) => {
        addNotification('error', error.error || 'خطا در بروزرسانی اتصال FTP')
      },
    }
  )
}
