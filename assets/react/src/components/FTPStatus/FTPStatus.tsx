/**
 * FTP Status Component
 */
import React from 'react'
import { useFTPStatus, useRefreshFTP } from '@/hooks/useFTP'
import { Button } from '@/components/UI'
import { formatDateTime } from '@/utils/format'

export const FTPStatus: React.FC = () => {
  const { data, isLoading } = useFTPStatus()
  const refreshMutation = useRefreshFTP()

  if (isLoading || !data?.success || !data.data) {
    return null
  }

  const status = data.data
  const statusClass = status.connected
    ? 'ftp-status-connected'
    : 'ftp-status-disconnected'
  const statusIcon = status.connected ? 'yes-alt' : 'dismiss'

  return (
    <div className={`notice notice-info tabesh-ftp-status ${statusClass}`}>
      <span className={`dashicons dashicons-${statusIcon}`}></span>
      <div className="ftp-status-content">
        <strong>وضعیت اتصال FTP:</strong>
        <span>{status.message}</span>
        {status.connected && status.uptime && (
          <span className="ftp-status-detail">
            | مدت فعالیت: {status.uptime}
          </span>
        )}
        {status.last_success && (
          <span className="ftp-status-detail">
            | آخرین اتصال موفق: {formatDateTime(status.last_success)}
          </span>
        )}
      </div>
      <Button
        size="sm"
        variant="secondary"
        onClick={() => refreshMutation.mutate()}
        loading={refreshMutation.isLoading}
      >
        بروزرسانی
      </Button>
    </div>
  )
}
