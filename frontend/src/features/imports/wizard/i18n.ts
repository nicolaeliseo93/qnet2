import i18n from '@/i18n'
import { importWizard as importWizardEn } from '@/i18n/locales/en-import-wizard'
import { importWizard as importWizardIt } from '@/i18n/locales/it-import-wizard'

/**
 * Registers the `importWizard` i18next namespace as a side effect of
 * importing this module (spec 0033), mirroring `features/migrations/i18n.ts`:
 * `en.ts`/`it.ts` stay within the engineering size limits, and this module
 * never requires editing those two shared files. Imported once by
 * `import-wizard.tsx` (the feature's render entry point); ES module caching
 * makes the registration idempotent.
 */
i18n.addResourceBundle('en', 'importWizard', importWizardEn)
i18n.addResourceBundle('it', 'importWizard', importWizardIt)
