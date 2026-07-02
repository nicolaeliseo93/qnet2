/**
 * Standard response envelope returned by the backend (BaseApiController).
 * Every successful endpoint wraps its payload in this shape.
 */
export interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

/**
 * Error payload returned by the backend on validation/failure responses.
 */
export interface ApiErrorResponse {
  success: false
  message: string
  errors?: Record<string, string[]>
}
