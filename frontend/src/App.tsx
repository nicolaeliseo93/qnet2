import { Suspense } from 'react'
import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from 'react-router-dom'
import { TooltipProvider } from '@/components/ui/tooltip'
import { Toaster } from '@/components/ui/sonner'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ThemeProvider } from '@/components/theme-provider'
import { AuthProvider } from '@/features/auth/auth-provider'
import { UserDetailSheetProvider } from '@/features/users/user-detail-sheet'
import { ConfigGate } from '@/features/config/config-gate'
import { FullScreenLoader } from '@/components/full-screen-loader'
import { queryClient } from '@/app/query-client'
import { router } from '@/routes/router'

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <ConfigGate>
          <AuthProvider>
            <TooltipProvider>
              <ConfirmDialogProvider>
                <UserDetailSheetProvider>
                  <Suspense fallback={<FullScreenLoader />}>
                    <RouterProvider router={router} />
                  </Suspense>
                </UserDetailSheetProvider>
              </ConfirmDialogProvider>
              <Toaster />
            </TooltipProvider>
          </AuthProvider>
        </ConfigGate>
      </ThemeProvider>
    </QueryClientProvider>
  )
}

export default App
