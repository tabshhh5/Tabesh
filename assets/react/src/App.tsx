/**
 * Main App Component
 */
import React from 'react'
import { QueryClient, QueryClientProvider } from 'react-query'
import { NotificationsProvider } from './contexts/NotificationsContext'
import { ThemeProvider } from './contexts/ThemeContext'
import { Dashboard } from './components/Dashboard'
import { ToastContainer } from './components/Notifications'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: 1,
    },
  },
})

const App: React.FC = () => {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <NotificationsProvider>
          <Dashboard />
          <ToastContainer />
        </NotificationsProvider>
      </ThemeProvider>
    </QueryClientProvider>
  )
}

export default App
