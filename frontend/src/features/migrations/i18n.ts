import i18n from '@/i18n'
import { migrations as migrationsEn } from '@/i18n/locales/en-migrations'
import { migrations as migrationsIt } from '@/i18n/locales/it-migrations'

/**
 * Registers the `migrations` i18next namespace as a side effect of importing
 * this module (spec 0013). `en.ts`/`it.ts` already sit at the engineering
 * file-size limit and are owned by another teammate's in-flight change: every
 * other domain module merges into the single default `translation`
 * namespace, but this one registers itself as an additional namespace
 * instead, so loading the migrations feature never requires editing those
 * two shared files. Imported once by `migrations-page.tsx` and
 * `import-dialog.tsx` (the feature's two render entry points); ES module
 * caching makes the registration idempotent.
 */
i18n.addResourceBundle('en', 'migrations', migrationsEn)
i18n.addResourceBundle('it', 'migrations', migrationsIt)
