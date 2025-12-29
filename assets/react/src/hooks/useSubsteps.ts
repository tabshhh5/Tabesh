/**
 * Custom hooks for print substeps
 */
import { useQuery, useMutation, useQueryClient } from 'react-query'
import { substepsService } from '@/services/substeps'
import { useNotifications } from '@/contexts/NotificationsContext'

export const useSubsteps = (orderId: number) => {
  return useQuery(
    ['substeps', orderId],
    () => substepsService.getSubsteps(orderId),
    {
      enabled: orderId > 0,
    }
  )
}

export const useUpdateSubstep = () => {
  const queryClient = useQueryClient()
  const { addNotification } = useNotifications()

  return useMutation(
    ({ orderId, substepKey, isCompleted }: {
      orderId: number
      substepKey: string
      isCompleted: boolean
    }) => substepsService.updateSubstep(orderId, substepKey, isCompleted),
    {
      onSuccess: (_, variables) => {
        queryClient.invalidateQueries(['substeps', variables.orderId])
        addNotification('success', 'زیرمرحله با موفقیت به‌روزرسانی شد')
      },
      onError: (error: any) => {
        addNotification('error', error.error || 'خطا در به‌روزرسانی زیرمرحله')
      },
    }
  )
}
