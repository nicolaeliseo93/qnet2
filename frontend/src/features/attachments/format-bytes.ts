const BYTES_PER_KB = 1024
const BYTES_PER_MB = BYTES_PER_KB * 1024

/** Human-readable file size for a document tile (display only). */
export function formatBytes(bytes: number): string {
  if (bytes >= BYTES_PER_MB) {
    return `${(bytes / BYTES_PER_MB).toFixed(1)} MB`
  }
  return `${Math.max(1, Math.round(bytes / BYTES_PER_KB))} KB`
}
