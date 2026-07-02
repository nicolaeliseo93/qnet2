import { ModuleRegistry } from 'ag-grid-community'
import { AllEnterpriseModule, LicenseManager } from 'ag-grid-enterprise'
import { env } from '@/config/env'

/**
 * One-time AG Grid Enterprise bootstrap: registers all enterprise modules
 * (which include the Server-Side Row Model) and applies the license key from the
 * environment. Importing this module performs the side effects exactly once,
 * regardless of how many grids mount.
 *
 * The license key is read from env only and is optional at build time — without
 * it AG Grid still runs but shows a watermark / console warning.
 */
let initialized = false

export function setupAgGrid(): void {
  if (initialized) {
    return
  }
  initialized = true

  ModuleRegistry.registerModules([AllEnterpriseModule])

  if (env.agGridLicenseKey) {
    LicenseManager.setLicenseKey(env.agGridLicenseKey)
  }
}
