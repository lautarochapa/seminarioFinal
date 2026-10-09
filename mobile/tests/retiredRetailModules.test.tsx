import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { BranchDetailScreen } from '../src/screens/BranchDetailScreen';
import RetiredPromotionsRoute from '../app/(app)/promotions';
import RetiredPaymentMethodsRoute from '../app/(app)/payment-methods';
import { formatMoney } from '../src/utils/retail';

const mockGet = jest.fn();
const mockPush = jest.fn();
const mockRedirect = jest.fn();

jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('expo-router', () => ({
  useLocalSearchParams: () => ({ id: '12' }),
  router: { push: (...args: unknown[]) => mockPush(...args) },
  Redirect: ({ href }: { href: string }) => { mockRedirect(href); return null; },
}));
jest.mock('../src/utils/navigation', () => ({ goBackOrHome: jest.fn() }));
jest.mock('../src/components/AppHeader', () => ({ AppHeader: 'AppHeader' }));
jest.mock('../src/components/LoadingScreen', () => ({ LoadingScreen: 'LoadingScreen' }));
jest.mock('../src/components/ErrorState', () => ({ ErrorState: 'ErrorState' }));
jest.mock('../src/api/client', () => ({
  ApiError: class ApiError extends Error {},
  apiClient: { get: (...args: unknown[]) => mockGet(...args) },
}));

const branch = {
  id: 12, name: 'Sucursal QA', address: 'Dirección local', latitude: null, longitude: null,
  opening_hours: null, delivery_available: false, pickup_available: true, status: 'active',
  chain: { id: 3, name: 'Supermercado QA' }, city: { id: 1, name: 'Bariloche' },
};
const product = {
  id: 7, external_sku: 'qa-local', source_url: null, source_name: null, last_scraped_at: null,
  status: 'active', product: { id: 5, name: 'Arroz QA' }, branch,
  current_price: { price: '1250', currency: 'ARS', is_current: true, source: 'manual', valid_to: null },
};

describe('retired promotions and payment methods', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockGet.mockImplementation((url: string) => {
      if (url === '/api/v1/supermarket-branches/12') return Promise.resolve({ data: branch });
      if (url.startsWith('/api/v1/supermarket-branches/12/products?')) {
        return Promise.resolve({ data: [product], meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 } });
      }
      throw new Error('Unexpected request: ' + url);
    });
  });

  it('loads branch information and current prices without calling retired endpoints', async () => {
    const screen = await render(<BranchDetailScreen />);
    await waitFor(() => expect(screen.getByText('Arroz QA')).toBeTruthy());
    expect(screen.getByText(formatMoney('1250', 'ARS'))).toBeTruthy();
    expect(screen.getByText('Productos y precios')).toBeTruthy();
    expect(screen.queryByText('Promociones')).toBeNull();
    expect(screen.queryByText(/metodos de pago/i)).toBeNull();
    expect(mockGet).toHaveBeenCalledTimes(2);
    expect(mockGet.mock.calls.map(([url]) => url)).toEqual([
      '/api/v1/supermarket-branches/12',
      expect.stringMatching(/^\/api\/v1\/supermarket-branches\/12\/products\?/),
    ]);
    fireEvent.press(screen.getByText('Comparar'));
    expect(mockPush).toHaveBeenCalledWith({ pathname: '/(app)/price-comparison' });
  });

  it('redirects an old promotions link to branches without fetching data', async () => {
    await render(<RetiredPromotionsRoute />);
    expect(mockRedirect).toHaveBeenCalledWith('/(app)/branches');
    expect(mockGet).not.toHaveBeenCalled();
  });

  it('redirects an old payment-methods link to profile without fetching data', async () => {
    await render(<RetiredPaymentMethodsRoute />);
    expect(mockRedirect).toHaveBeenCalledWith('/(app)/profile');
    expect(mockGet).not.toHaveBeenCalled();
  });
});
