import type { NormalizedError } from '@/types/api';

function parseRetryAfter(headers: Headers): number | undefined {
  const raw = headers.get('Retry-After');
  if (!raw) return undefined;
  const secs = parseInt(raw, 10);
  return isNaN(secs) ? undefined : secs;
}

export async function parseApiError(
  response: Response,
): Promise<NormalizedError> {
  let code = 'INTERNAL_ERROR';
  let message = 'Ocurrió un error inesperado.';
  let fieldErrors: Record<string, string[]> = {};
  let traceId = '';

  try {
    const body = await response.json();
    if (body?.error) {
      code = body.error.code ?? code;
      message = body.error.message ?? message;
      fieldErrors = body.error.field_errors ?? {};
    }
    traceId = body?.trace_id ?? '';
  } catch {
    // response body is not JSON
  }

  return {
    status: response.status,
    code,
    message,
    fieldErrors,
    traceId,
    isNetworkError: false,
    isTimeoutError: false,
    retryAfter: parseRetryAfter(response.headers),
  };
}

export function makeNetworkError(message = 'No se pudo conectar al servidor.'): NormalizedError {
  return {
    status: 0,
    code: 'NETWORK_ERROR',
    message,
    fieldErrors: {},
    traceId: '',
    isNetworkError: true,
    isTimeoutError: false,
  };
}

export function makeTimeoutError(): NormalizedError {
  return {
    status: 0,
    code: 'TIMEOUT_ERROR',
    message: 'La solicitud tardó demasiado. Intentá de nuevo.',
    fieldErrors: {},
    traceId: '',
    isNetworkError: false,
    isTimeoutError: true,
  };
}

export function friendlyMessage(error: NormalizedError): string {
  if (error.code === 'OFFLINE') return 'Sin conexión. Esta acción se podrá reintentar cuando vuelva la conexión.';
  if (error.isNetworkError) return 'No se pudo conectar al servidor. Verificá tu conexión.';
  if (error.isTimeoutError) return 'La solicitud tardó demasiado. Intentá de nuevo.';
  switch (error.status) {
    case 401: return 'Tu sesión venció. Iniciá sesión nuevamente.';
    case 403: return 'No tenés permiso para realizar esta acción.';
    case 404: return 'El recurso no fue encontrado.';
    case 409: return error.message;
    case 422: return error.message;
    case 429: return 'Demasiados intentos. Esperá un momento e intentá de nuevo.';
    case 500: return 'Error del servidor. Intentá más tarde.';
    default: return error.message;
  }
}
