import React from 'react';
import { render, waitFor } from '@testing-library/react-native';
import { StockScreen } from '../src/screens/StockScreen';

const mockList = jest.fn();
let mockFocused = true;
let mockGroup: { id: number; name: string } | null;
jest.mock('../src/api/endpoints', () => ({ stockApi: { list: (...args: unknown[]) => mockList(...args) } }));
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: mockGroup }) }));
jest.mock('../src/components/FamilyGroupSelector', () => ({ FamilyGroupSelector: 'FamilyGroupSelector' }));
jest.mock('../src/components/StockAlertsBanner', () => ({ StockAlertsBanner: 'StockAlertsBanner' }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn() }),
  useFocusEffect: (cb: () => void | (() => void)) => {
    jest.requireActual<typeof import('react')>('react').useEffect(() => {
      if (mockFocused) return cb();
    }, [cb, mockFocused]);
  },
}));

const EMPTY = { data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 20 } };
const FILLED = {
  data: [{ id: 40, product_id: 12, product: { id: 12, name: 'QA arroz' }, quantity: 1, unit: { symbol: 'kg' }, expiration_date: null }],
  meta: { current_page: 1, last_page: 1, total: 1, per_page: 20 },
};

beforeEach(() => {
  jest.clearAllMocks();
  mockFocused = true;
  mockGroup = { id: 7, name: 'Hogar QA' };
  mockList.mockResolvedValue(EMPTY);
});

it('loads the first stock page only once when opened', async () => {
  const screen = await render(<StockScreen />);
  await waitFor(() => expect(screen.getByText('0 items')).toBeTruthy());
  expect(mockList).toHaveBeenCalledTimes(1);
});

it('shows the saved item when returning from creation without a manual refresh', async () => {
  const screen = await render(<StockScreen />);
  await waitFor(() => expect(screen.getByText('0 items')).toBeTruthy());
  mockFocused = false;
  await screen.rerender(<StockScreen />);
  mockList.mockResolvedValue(FILLED);
  mockFocused = true;
  await screen.rerender(<StockScreen />);
  await waitFor(() => expect(screen.getByText('QA arroz')).toBeTruthy());
  expect(screen.getByText('1 item')).toBeTruthy();
  expect(mockList).toHaveBeenLastCalledWith(7, { page: 1 });
  expect(mockList).toHaveBeenCalledTimes(2);
});

it('does not fetch household stock without a selected group', async () => {
  mockGroup = null;
  const screen = await render(<StockScreen />);
  mockFocused = false;
  await screen.rerender(<StockScreen />);
  mockFocused = true;
  await screen.rerender(<StockScreen />);
  expect(mockList).not.toHaveBeenCalled();
});
