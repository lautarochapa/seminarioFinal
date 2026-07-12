import { ENV } from '@/config/env';
import { sessionStorage } from '@/storage/sessionStorage';
import { offlineCache } from '@/storage/offlineCache';
import {
  makeNetworkError,
  makeTimeoutError,
  parseApiError,
} from '@/utils/errorParser';
import { getNetworkState, reportRequestFailure, reportRequestSuccess } from '@/utils/networkStatus';
import type { NormalizedError } from '@/types/api';

const TIMEOUT_MS = 15_000;
const GET_RETRY_DELAYS_MS = [500, 1500];

// Only cache reads for screens that explicitly need last-known-good data offline.
const CACHEABLE_GET_PREFIXES = [
  '/api/v1/users/me/profile',
  '/api/v1/family-groups',
  '/api/v1/products',
  '/api/v1/notifications',
];

function isCacheableGet(path: string): boolean {
  return CACHEABLE_GET_PREFIXES.some((prefix) => path.startsWith(prefix));
}

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

let onUnauthorized: (() => void) | null = null;
let unauthorizedInFlight = false;

export function registerUnauthorizedHandler(handler: () => void): void {
  onUnauthorized = handler;
}

function triggerUnauthorized(): void {
  if (unauthorizedInFlight) return;
  unauthorizedInFlight = true;
  onUnauthorized?.();
  // Reset flag after redirect is handled
  setTimeout(() => { unauthorizedInFlight = false; }, 2000);
}

async function buildHeaders(includeAuth: boolean): Promise<Record<string, string>> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };
  if (includeAuth) {
    const token = await sessionStorage.getToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
  }
  return headers;
}

export class ApiError extends Error {
  normalized: NormalizedError;
  constructor(normalized: NormalizedError) {
    super(normalized.message);
    this.normalized = normalized;
    this.name = 'ApiError';
  }
}

function makeOfflineError(): ApiError {
  return new ApiError({
    status: 0,
    code: 'OFFLINE',
    message: 'Sin conexión. Esta acción requiere conectarse a internet.',
    fieldErrors: {},
    traceId: '',
    isNetworkError: true,
    isTimeoutError: false,
  });
}

async function attemptFetch(
  method: string,
  url: string,
  headers: Record<string, string>,
  body: unknown,
): Promise<Response> {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), TIMEOUT_MS);
  try {
    const response = await fetch(url, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });
    reportRequestSuccess();
    return response;
  } catch (err: unknown) {
    reportRequestFailure();
    if (err instanceof Error && err.name === 'AbortError') {
      throw new ApiError(makeTimeoutError());
    }
    throw new ApiError(makeNetworkError());
  } finally {
    clearTimeout(timeoutId);
  }
}

async function fetchWithGetRetry(
  method: string,
  url: string,
  headers: Record<string, string>,
  body: unknown,
): Promise<Response> {
  for (let attempt = 0; ; attempt += 1) {
    try {
      return await attemptFetch(method, url, headers, body);
    } catch (err) {
      if (attempt >= GET_RETRY_DELAYS_MS.length) throw err;
      await delay(GET_RETRY_DELAYS_MS[attempt]);
    }
  }
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  options: { skipAuth?: boolean } = {},
): Promise<T> {
  const isSafe = method === 'GET';

  if (!isSafe && getNetworkState() === 'offline') {
    throw makeOfflineError();
  }

  const url = `${ENV.API_URL}${path}`;
  const headers = await buildHeaders(!options.skipAuth);
  const cacheable = isSafe && isCacheableGet(path);

  let response: Response;
  try {
    response = isSafe
      ? await fetchWithGetRetry(method, url, headers, body)
      : await attemptFetch(method, url, headers, body);
  } catch (err) {
    if (cacheable) {
      const cached = await offlineCache.read<T>(path);
      if (cached) return cached.data;
    }
    throw err;
  }

  if (response.status === 204) {
    return undefined as unknown as T;
  }

  if (!response.ok) {
    const normalized = await parseApiError(response);
    if (response.status === 401 && !options.skipAuth) {
      triggerUnauthorized();
    }
    throw new ApiError(normalized);
  }

  const parsed = (await response.json()) as T;
  if (cacheable) {
    void offlineCache.write(path, parsed);
  }
  return parsed;
}

export const apiClient = {
  get<T>(path: string, options?: { skipAuth?: boolean }): Promise<T> {
    return request<T>('GET', path, undefined, options);
  },
  post<T>(path: string, body?: unknown, options?: { skipAuth?: boolean }): Promise<T> {
    return request<T>('POST', path, body, options);
  },
  put<T>(path: string, body?: unknown, options?: { skipAuth?: boolean }): Promise<T> {
    return request<T>('PUT', path, body, options);
  },
  patch<T>(path: string, body?: unknown, options?: { skipAuth?: boolean }): Promise<T> {
    return request<T>('PATCH', path, body, options);
  },
  delete<T>(path: string, options?: { skipAuth?: boolean }): Promise<T> {
    return request<T>('DELETE', path, undefined, options);
  },
};
