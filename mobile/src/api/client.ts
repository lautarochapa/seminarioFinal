import { ENV } from '@/config/env';
import { sessionStorage } from '@/storage/sessionStorage';
import {
  makeNetworkError,
  makeTimeoutError,
  parseApiError,
} from '@/utils/errorParser';
import type { NormalizedError } from '@/types/api';

const TIMEOUT_MS = 15_000;

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

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  options: { skipAuth?: boolean } = {},
): Promise<T> {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), TIMEOUT_MS);

  const url = `${ENV.API_URL}${path}`;
  const headers = await buildHeaders(!options.skipAuth);

  let response: Response;
  try {
    response = await fetch(url, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });
  } catch (err: unknown) {
    clearTimeout(timeoutId);
    if (err instanceof Error && err.name === 'AbortError') {
      throw new ApiError(makeTimeoutError());
    }
    throw new ApiError(makeNetworkError());
  } finally {
    clearTimeout(timeoutId);
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

  return response.json() as Promise<T>;
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
