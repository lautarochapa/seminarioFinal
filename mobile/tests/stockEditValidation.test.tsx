import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { StockItemEditScreen } from '../src/screens/StockItemEditScreen';
import { ApiError } from '../src/api/client';

const mockUpdate = jest.fn();
const mockBack = jest.fn();
const mockItem = { id: 40, product: { name: 'Avena' }, quantity: 1, stock_location_id: null, expiration_date: null, purchase_price: null };
jest.mock('../src/hooks/useStockItem', () => ({ useStockItem: () => ({ data: mockItem, loading: false, error: null }) }));
jest.mock('../src/hooks/useStockLocations', () => ({ useStockLocations: () => ({ data: [], loading: false }) }));
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: { id: 7 } }) }));
jest.mock('../src/api/endpoints', () => ({ stockApi: { update: (...args: unknown[]) => mockUpdate(...args) } }));
jest.mock('../src/utils/navigation', () => ({ goBackOrHome: () => mockBack() }));
jest.mock('../src/components/AppHeader', () => ({ AppHeader: 'AppHeader' }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
beforeEach(() => { jest.clearAllMocks(); mockUpdate.mockResolvedValue({ data: mockItem }); });

it('rejects an invalid calendar date and price visibly before submitting', async () => {
  const screen = await render(<StockItemEditScreen stockItemId={40} />);
  await fireEvent.changeText(screen.getByLabelText('Fecha de vencimiento'), '2026-99-99');
  await fireEvent.changeText(screen.getByLabelText('Precio de compra'), '-1');
  await fireEvent.press(screen.getByText('Guardar cambios'));
  expect(screen.getByText('Ingresá una fecha válida con formato YYYY-MM-DD.')).toBeTruthy();
  expect(screen.getByText('Ingresá un precio válido, mayor o igual a cero.')).toBeTruthy();
  expect(mockUpdate).not.toHaveBeenCalled();
});
it('saves ISO dates without timezone conversion and supports decimal commas', async () => {
  const screen = await render(<StockItemEditScreen stockItemId={40} />);
  await fireEvent.changeText(screen.getByLabelText('Fecha de vencimiento'), '2026-09-23');
  await fireEvent.changeText(screen.getByLabelText('Precio de compra'), '12,50');
  await fireEvent.press(screen.getByText('Guardar cambios'));
  await waitFor(() => expect(mockUpdate).toHaveBeenCalledWith(7, 40, { stock_location_id: null, quantity: 1, expiration_date: '2026-09-23', purchase_price: 12.5 }));
  expect(mockBack).toHaveBeenCalled();
});
it('renders server errors for the expiration and price fields', async () => {
  mockUpdate.mockRejectedValue(new ApiError({ status: 422, code: 'VALIDATION_ERROR', message: 'Revisar', fieldErrors: { expiration_date: ['Fecha rechazada.'], purchase_price: ['Precio rechazado.'] }, traceId: '', isNetworkError: false, isTimeoutError: false }));
  const screen = await render(<StockItemEditScreen stockItemId={40} />);
  await fireEvent.press(screen.getByText('Guardar cambios'));
  await waitFor(() => expect(screen.getByText('Fecha rechazada.')).toBeTruthy());
  expect(screen.getByText('Precio rechazado.')).toBeTruthy();
  expect(mockBack).not.toHaveBeenCalled();
});
