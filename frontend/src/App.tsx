import { Suspense } from 'react'
import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from 'react-router-dom'
import { TooltipProvider } from '@/components/ui/tooltip'
import { Toaster } from '@/components/ui/sonner'
import { AuthProvider } from '@/features/auth/auth-provider'
import { ConfigGate } from '@/features/config/config-gate'
import { FullScreenLoader } from '@/components/full-screen-loader'
import { queryClient } from '@/app/query-client'
import { router } from '@/routes/router'

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ConfigGate>
        <AuthProvider>
          <TooltipProvider>
            <Suspense fallback={<FullScreenLoader />}>
              <RouterProvider router={router} />
            </Suspense>
            <Toaster />
          </TooltipProvider>
        </AuthProvider>
      </ConfigGate>
    </QueryClientProvider>
  )
}

export default App
