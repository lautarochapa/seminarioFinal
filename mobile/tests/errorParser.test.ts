import { parseApiError, makeNetworkError, makeTimeoutError, friendlyMessage } from '../src/utils/errorParser';

function makeResponse(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  const headerMap = new Headers(headers);
  return {
    status,
    ok: status >= 200 && status < 300,
    headers: headerMap,
    json: async () => body,
  } as unknown as Response;
}

describe('parseApiError', () => {
  it('parses standard API error body', async () => {
    const res = makeResponse(404, {
      error: { code: 'PRODUCT_NOT_FOUND', message: 'No existe.', details: [], field_errors: {} },
      trace_id: 'abc-123',
    });
    const err = await parseApiError(res);
    expect(err.status).toBe(404);
    expect(err.code).toBe('PRODUCT_NOT_FOUND');
    expect(err.message).toBe('No existe.');
    expect(err.traceId).toBe('abc-123');
    expect(err.isNetworkError).toBe(false);
  });

  it('parses validation field errors', async () => {
    const res = makeResponse(422, {
      error: {
        code: 'VALIDATION_ERROR',
        message: 'Datos inválidos.',
        details: [],
        field_errors: { email: ['El campo email es obligatorio.'] },
      },
      trace_id: 'xyz',
    });
    const err = await parseApiError(res);
    expect(err.status).toBe(422);
    expect(err.fieldErrors.email).toEqual(['El campo email es obligatorio.']);
  });

  it('parses Retry-After header on 429', async () => {
    const res = makeResponse(429, {
      error: { code: 'TOO_MANY_REQUESTS', message: 'Muchos intentos.', details: [], field_errors: {} },
      trace_id: '',
    }, { 'Retry-After': '30' });
    const err = await parseApiError(res);
    expect(err.retryAfter).toBe(30);
  });

  it('handles non-JSON body gracefully', async () => {
    const res = {
      status: 500,
      ok: false,
      headers: new Headers(),
      json: async () => { throw new Error('not json'); },
    } as unknown as Response;
    const err = await parseApiError(res);
    expect(err.status).toBe(500);
    expect(err.code).toBe('INTERNAL_ERROR');
  });
});

describe('makeNetworkError', () => {
  it('produces a network error with correct flags', () => {
    const err = makeNetworkError();
    expect(err.status).toBe(0);
    expect(err.isNetworkError).toBe(true);
    expect(err.isTimeoutError).toBe(false);
  });
});

describe('makeTimeoutError', () => {
  it('produces a timeout error with correct flags', () => {
    const err = makeTimeoutError();
    expect(err.status).toBe(0);
    expect(err.isTimeoutError).toBe(true);
    expect(err.isNetworkError).toBe(false);
  });
});

describe('friendlyMessage', () => {
  it('returns session expired for 401', () => {
    const msg = friendlyMessage({ status: 401, code: '', message: '', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false });
    expect(msg).toContain('sesión');
  });

  it('returns connectivity message for network error', () => {
    const msg = friendlyMessage({ status: 0, code: '', message: '', fieldErrors: {}, traceId: '', isNetworkError: true, isTimeoutError: false });
    expect(msg).toContain('conectar');
  });

  it('returns server error for 500', () => {
    const msg = friendlyMessage({ status: 500, code: '', message: '', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false });
    expect(msg).toContain('servidor');
  });
});
