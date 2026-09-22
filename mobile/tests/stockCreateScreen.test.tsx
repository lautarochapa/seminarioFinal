import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { StockCreateScreen } from '../src/screens/StockCreateScreen';
import { ApiError } from '../src/api/client';

const mockCreate = jest.fn();
const mockUnits = jest.fn();
const mockReplace = jest.fn();
let mockGroup: { id: number; name: string } | null;
jest.mock('../src/api/endpoints', () => ({
  stockApi: { create: (...args: unknown[]) => mockCreate(...args) },
  unitsApi: { list: () => mockUnits() },
}));
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: mockGroup }) }));
jest.mock('../src/hooks/useStockLocations', () => ({ useStockLocations: () => ({ data: [], loading: false }) }));
jest.mock('../src/hooks/useProducts', () => ({ useProducts: () => ({ data: [], loading: false, setFilters: jest.fn() }) }));
jest.mock('../src/utils/barcodeScanResult', () => ({ consumePendingScanResult: () => null }));
jest.mock('../src/components/AppHeader', () => ({ AppHeader: 'AppHeader' }));
jest.mock('../src/components/FamilyGroupSelector', () => ({ FamilyGroupSelector: 'FamilyGroupSelector' }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('expo-router', () => ({
  useRouter: () => ({ replace: mockReplace, push: jest.fn() }),
  useFocusEffect: (cb: () => void) => { jest.requireActual<typeof import('react')>('react').useEffect(cb, [cb]); },
}));

beforeEach(() => {
  jest.clearAllMocks();
  mockGroup = { id: 7, name: 'Hogar QA' };
  mockUnits.mockResolvedValue({ data: [{ id: 1, code: 'g', symbol: 'g', name: 'Gramos' }] });
  mockCreate.mockResolvedValue({ data: { id: 10 } });
});

async function openForm(quantity = '500') {
  const screen = await render(<StockCreateScreen prefilledProductId={1} prefilledProductName="Arroz" />);
  await waitFor(() => expect(screen.getByRole('button', { name: 'Gramos' })).toBeTruthy());
  await fireEvent.press(screen.getByRole('button', { name: 'Gramos' }));
  await fireEvent.changeText(screen.getByLabelText('Cantidad *'), quantity);
  return screen;
}

it('adds 500 grams to the selected household without forcing optional date or price', async () => {
  const screen = await openForm();
  await fireEvent.press(screen.getByText('Guardar'));
  await waitFor(() => expect(mockCreate).toHaveBeenCalledWith(7, { product_id: 1, stock_location_id: null, quantity: 500, unit_id: 1, expiration_date: null, purchase_price: null }));
  await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/(app)/stock'));
  expect(mockCreate).toHaveBeenCalledTimes(1);
});

it.each(['-1', 'abc', ''])('rejects invalid quantity %s without sending or navigating', async (quantity) => {
  const screen = await openForm(quantity);
  await fireEvent.press(screen.getByText('Guardar'));
  expect(screen.getByText(/cantidad v/)).toBeTruthy();
  expect(mockCreate).not.toHaveBeenCalled();
  expect(mockReplace).not.toHaveBeenCalled();
});

it('keeps data after a server validation failure and allows a corrected retry', async () => {
  mockCreate.mockRejectedValueOnce(new ApiError({ status: 422, code: 'VALIDATION_ERROR', message: 'Cantidad fuera de rango.', fieldErrors: { quantity: ['Cantidad fuera de rango.'] }, traceId: 'qa', isNetworkError: false, isTimeoutError: false }));
  const screen = await openForm();
  await fireEvent.press(screen.getByText('Guardar'));
  await waitFor(() => expect(screen.getByText('Cantidad fuera de rango.')).toBeTruthy());
  expect(screen.getByLabelText('Cantidad *').props.value).toBe('500');
  expect(mockReplace).not.toHaveBeenCalled();
  await fireEvent.changeText(screen.getByLabelText('Cantidad *'), '250');
  await fireEvent.press(screen.getByText('Guardar'));
  await waitFor(() => expect(mockReplace).toHaveBeenCalledTimes(1));
  expect(mockCreate).toHaveBeenLastCalledWith(7, expect.objectContaining({ quantity: 250 }));
});

it('asks for a household and offers no save action when none is selected', async () => {
  mockGroup = null;
  const screen = await render(<StockCreateScreen />);
  await waitFor(() => expect(screen.getByText(/grupo familiar para agregar al stock/)).toBeTruthy());
  expect(screen.queryByText('Guardar')).toBeNull();
  expect(mockCreate).not.toHaveBeenCalled();
});
