import * as SecureStore from 'expo-secure-store';
import { apiClient, ApiError } from '../src/api/client';
import { __resetNetworkStateForTests, getNetworkState, reportRequestFailure } from '../src/utils/networkStatus';

// We test api/client indirectly through fetch mocks
const mockFetch = jest.fn();
(globalThis as typeof globalThis & { fetch: typeof fetch }).fetch = mockFetch;

const mockGetToken = SecureStore.getItemAsync as jest.Mock;

beforeEach(() => {
  jest.clearAllMocks();
  __resetNetworkStateForTests();
});

describe('apiClient Bearer token', () => {
  it('adds Authorization header when token is stored', async () => {
    mockGetToken.mockResolvedValueOnce('my-bearer-token');
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ data: { test: true }, trace_id: 'x' }),
    });

    await apiClient.get('/api/v1/test');

    const callArgs = mockFetch.mock.calls[0];
    const headers = callArgs[1].headers;
    expect(headers['Authorization']).toBe('Bearer my-bearer-token');
  });

  it('does NOT add Authorization header when skipAuth is true', async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ data: {}, trace_id: 'x' }),
    });

    await apiClient.post('/api/v1/auth/login', { email: 'x', password: 'y' }, { skipAuth: true });

    const callArgs = mockFetch.mock.calls[0];
    const headers = callArgs[1].headers;
    expect(headers['Authorization']).toBeUndefined();
  });

  it('throws ApiError on 401', async () => {
    mockGetToken.mockResolvedValueOnce('expired-token');
    mockFetch.mockResolvedValueOnce({
      ok: false,
      status: 401,
      headers: new Headers(),
      json: async () => ({
        error: { code: 'AUTH_INVALID_TOKEN', message: 'Token inválido.', details: [], field_errors: {} },
        trace_id: 'err-trace',
      }),
    });

    await expect(apiClient.get('/api/v1/protected')).rejects.toThrow(ApiError);
  });

  it('throws ApiError with isNetworkError on fetch failure', async () => {
    mockGetToken.mockResolvedValue(null);
    mockFetch.mockRejectedValue(new TypeError('Network request failed'));

    try {
      await apiClient.get('/api/v1/test');
      fail('should have thrown');
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError);
      expect((err as InstanceType<typeof ApiError>).normalized.isNetworkError).toBe(true);
    }
  }, 10000);

  it('returns undefined on 204', async () => {
    mockGetToken.mockResolvedValueOnce('token');
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 204,
      headers: new Headers(),
    });

    const result = await apiClient.post('/api/v1/auth/logout');
    expect(result).toBeUndefined();
  });
});

describe('apiClient 429 handling', () => {
  it('throws ApiError with status 429 and retryAfter', async () => {
    mockGetToken.mockResolvedValueOnce('token');
    const headers = new Headers({ 'Retry-After': '60' });
    mockFetch.mockResolvedValueOnce({
      ok: false,
      status: 429,
      headers,
      json: async () => ({
        error: { code: 'TOO_MANY_REQUESTS', message: 'Rate limited.', details: [], field_errors: {} },
        trace_id: '',
      }),
    });

    try {
      await apiClient.post('/api/v1/auth/login', {}, { skipAuth: true });
      fail('should throw');
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError);
      expect((err as InstanceType<typeof ApiError>).normalized.status).toBe(429);
      expect((err as InstanceType<typeof ApiError>).normalized.retryAfter).toBe(60);
    }
  });
});

describe('apiClient network resilience', () => {
  it('retries a GET after one transient network failure and succeeds', async () => {
    mockGetToken.mockResolvedValue(null);
    mockFetch
      .mockRejectedValueOnce(new TypeError('Network request failed'))
      .mockRejectedValueOnce(new TypeError('Heartbeat failed'))
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: { ok: true }, trace_id: 'x' }),
      });

    const result = await apiClient.get('/api/v1/test');

    expect(result).toEqual({ data: { ok: true }, trace_id: 'x' });
    expect(mockFetch).toHaveBeenCalledTimes(3);
  }, 10000);

  it('does not retry mutating requests on failure', async () => {
    mockGetToken.mockResolvedValue(null);
    mockFetch.mockRejectedValue(new TypeError('Network request failed'));

    await expect(apiClient.post('/api/v1/family-groups', {})).rejects.toBeInstanceOf(ApiError);
    expect(mockFetch).toHaveBeenCalledTimes(2);
  });

  it('blocks a mutation immediately once the network state is offline, without calling fetch', async () => {
    mockGetToken.mockResolvedValue(null);
    const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

    // reportRequestFailure() moves 'online' -> 'reconnecting' and fires a background
    // heartbeat fetch; make that heartbeat fail too so the state settles on 'offline'.
    mockFetch.mockRejectedValue(new TypeError('Network request failed'));
    reportRequestFailure();
    await flush();
    await flush();

    expect(getNetworkState()).toBe('offline');

    mockFetch.mockClear();
    await expect(apiClient.post('/api/v1/family-groups', {})).rejects.toMatchObject({
      normalized: { code: 'OFFLINE', isNetworkError: true },
    });
    expect(mockFetch).not.toHaveBeenCalled();
  });

  it('marks the network back online after a successful request', async () => {
    mockGetToken.mockResolvedValue(null);

    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ data: {}, trace_id: 'x' }),
    });
    await apiClient.get('/api/v1/test');

    expect(getNetworkState()).toBe('online');
  });
});
