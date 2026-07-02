import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import '@/i18n'
import App from '@/App'
import { queryClient } from '@/app/query-client'
import { configQueryOptions } from '@/features/config/query-options'

// Config-first bootstrap (ADR 0009): kick off GET /api/config as the very first
// request, before the first paint. We don't await it — the ConfigGate reads the
// cache to drive the boot splash / error / success states. Shares its options
// with useConfig (configQueryOptions), so this primes the same cache entry with
// no duplicate fetch and the identical retry policy.
void queryClient.ensureQueryData(configQueryOptions)

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
