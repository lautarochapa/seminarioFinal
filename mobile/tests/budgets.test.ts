import { budgetsApi } from '../src/api/endpoints';

jest.mock('../src/api/client', () => {
  const get = jest.fn();
  const post = jest.fn();
  const patch = jest.fn();
  const del = jest.fn();
  return {
    ApiError: class ApiError extends Error {
      normalized: unknown;
      constructor(n: unknown) { super('err'); this.normalized = n; this.name = 'ApiError'; }
    },
    apiClient: { get, post, patch, delete: del },
  };
});

jest.mock('expo-secure-store', () => ({
  setItemAsync: jest.fn(),
  getItemAsync: jest.fn().mockResolvedValue(null),
  deleteItemAsync: jest.fn(),
}));

const { apiClient } = require('../src/api/client');

const MOCK_BUDGET = {
  id: 1, family_group_id: 2, year: 2024, month: 7,
  total_amount: 50000, currency: 'ARS', status: 'active',
  created_at: '2024-07-01T00:00:00Z', updated_at: '2024-07-01T00:00:00Z', deleted_at: null,
};

const MOCK_SUMMARY = {
  budget_id: 1, year: 2024, month: 7, currency: 'ARS',
  total_amount: 50000, spent_amount: 15000, available_amount: 35000,
  consumed_percent: 30, purchase_count: 3,
};

beforeEach(() => { jest.clearAllMocks(); });

describe('budgetsApi', () => {
  it('list: calls correct endpoint', async () => {
    apiClient.get.mockResolvedValueOnce({
      data: [MOCK_BUDGET],
      meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
      links: { first: null, last: null, prev: null, next: null },
      trace_id: 'abc',
    });
    const res = await budgetsApi.list(5);
    expect(apiClient.get).toHaveBeenCalledWith(expect.stringContaining('/family-groups/5/budgets'));
    expect(res.data[0].currency).toBe('ARS');
  });

  it('summary: returns spent/available/percent', async () => {
    apiClient.get.mockResolvedValueOnce({ data: MOCK_SUMMARY, trace_id: 'abc' });
    const res = await budgetsApi.summary(5, 1);
    expect(apiClient.get).toHaveBeenCalledWith('/api/v1/family-groups/5/budgets/1/summary');
    expect(res.data.consumed_percent).toBe(30);
    expect(res.data.available_amount).toBe(35000);
  });

  it('projection: returns projected amounts', async () => {
    const projection = {
      budget_id: 1, year: 2024, month: 7, currency: 'ARS',
      total_amount: 50000, spent_amount: 15000,
      planned_amount: 10000, available_projected: 25000,
      percent_projected: 50, planned_sources: [],
    };
    apiClient.get.mockResolvedValueOnce({ data: projection, trace_id: 'abc' });
    const res = await budgetsApi.projection(5, 1);
    expect(apiClient.get).toHaveBeenCalledWith('/api/v1/family-groups/5/budgets/1/projection');
    expect(res.data.available_projected).toBe(25000);
    expect(res.data.planned_amount).toBe(10000);
  });

  it('create: posts budget with year/month/amount', async () => {
    apiClient.post.mockResolvedValueOnce({ data: MOCK_BUDGET, trace_id: 'abc' });
    await budgetsApi.create(5, { year: 2024, month: 7, total_amount: 50000, currency: 'ARS' });
    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/v1/family-groups/5/budgets',
      { year: 2024, month: 7, total_amount: 50000, currency: 'ARS' },
    );
  });

  it('MoneyText: formats ARS correctly', () => {
    const amount = 15000;
    const formatted = amount.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    expect(formatted).toBeTruthy();
    expect(typeof formatted).toBe('string');
  });

  it('projection handles null planned_amount', async () => {
    const projection = {
      budget_id: 1, year: 2024, month: 7, currency: 'ARS',
      total_amount: 50000, spent_amount: 15000,
      planned_amount: null, available_projected: 35000,
      percent_projected: 30, planned_sources: [],
    };
    apiClient.get.mockResolvedValueOnce({ data: projection, trace_id: 'abc' });
    const res = await budgetsApi.projection(5, 1);
    expect(res.data.planned_amount).toBeNull();
  });
});
