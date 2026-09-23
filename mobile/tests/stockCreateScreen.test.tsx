import React from 'react';
import { act, fireEvent, render, waitFor } from '@testing-library/react-native';
import { Keyboard, Platform, StyleSheet } from 'react-native';
import { COLORS, SPACING } from '../src/utils/theme';
import { StockCreateScreen } from '../src/screens/StockCreateScreen';
import { ApiError } from '../src/api/client';

const mockCreate = jest.fn();
const mockCreateManual = jest.fn();
const mockUnits = jest.fn();
const mockReplace = jest.fn();
let mockGroup: { id: number; name: string } | null;
jest.mock('../src/api/endpoints', () => ({
  stockApi: { create: (...args: unknown[]) => mockCreate(...args), createManualProduct: (...args: unknown[]) => mockCreateManual(...args) },
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
  mockCreateManual.mockResolvedValue({ data: { id: 11 } });
});

async function openForm(quantity = '500') {
  const screen = await render(<StockCreateScreen prefilledProductId={1} prefilledProductName="Arroz" />);
  await waitFor(() => expect(screen.getByRole('button', { name: 'Gramos' })).toBeTruthy());
  await fireEvent.press(screen.getByRole('button', { name: 'Gramos' }));
  await fireEvent.changeText(screen.getByLabelText('Cantidad *'), quantity);
  return screen;
}

it('preserves decimal commas in quantity and price when creating stock', async () => {
  const screen = await openForm('0,25');
  await fireEvent.changeText(screen.getByLabelText('Fecha de vencimiento'), '2026-09-24');
  await fireEvent.changeText(screen.getByLabelText('Precio de compra'), '250,50');
  await fireEvent.press(screen.getByText('Guardar'));
  await waitFor(() => expect(mockCreate).toHaveBeenCalledWith(7, expect.objectContaining({ quantity: 0.25, purchase_price: 250.5, expiration_date: '2026-09-24' })));
});

it('shows impossible dates and invalid prices without sending the form', async () => {
  const screen = await openForm();
  await fireEvent.changeText(screen.getByLabelText('Fecha de vencimiento'), '2026-02-31');
  await fireEvent.changeText(screen.getByLabelText('Precio de compra'), '-5');
  await fireEvent.press(screen.getByText('Guardar'));
  expect(screen.getByText('Ingresá una fecha válida con formato YYYY-MM-DD.')).toBeTruthy();
  expect(screen.getByText('Ingresá un precio válido, mayor o igual a cero.')).toBeTruthy();
  expect(mockCreate).not.toHaveBeenCalled();
});

it('renders date and price validation errors returned by the server', async () => {
  mockCreate.mockRejectedValueOnce(new ApiError({ status: 422, code: 'VALIDATION_ERROR', message: 'Revisar', fieldErrors: { expiration_date: ['Fecha rechazada.'], purchase_price: ['Precio rechazado.'] }, traceId: 'qa', isNetworkError: false, isTimeoutError: false }));
  const screen = await openForm();
  await fireEvent.press(screen.getByText('Guardar'));
  await waitFor(() => expect(screen.getByText('Fecha rechazada.')).toBeTruthy());
  expect(screen.getByText('Precio rechazado.')).toBeTruthy();
  expect(mockReplace).not.toHaveBeenCalled();
});

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

it('preserves the zero-stock option, zero price and a valid leap-day date', async () => {
  const screen = await openForm('0');
  await fireEvent.changeText(screen.getByLabelText('Fecha de vencimiento'), '2028-02-29');
  await fireEvent.changeText(screen.getByLabelText('Precio de compra'), '0,00');
  await fireEvent.press(screen.getByText('Guardar'));
  expect(mockCreate).toHaveBeenCalledWith(7, expect.objectContaining({ quantity: 0, expiration_date: '2028-02-29', purchase_price: 0 }));
});

it('sends only once while a create request is pending', async () => {
  let resolve!: (value: unknown) => void;
  mockCreate.mockReturnValueOnce(new Promise((done) => { resolve = done; }));
  const screen = await openForm();
  await fireEvent.press(screen.getByLabelText('Guardar'));
  expect(screen.getByLabelText('Guardar').props.accessibilityState.disabled).toBe(true);
  await fireEvent.press(screen.getByLabelText('Guardar'));
  expect(mockCreate).toHaveBeenCalledTimes(1);
  await act(async () => resolve({ data: { id: 10 } }));
  expect(mockReplace).toHaveBeenCalledTimes(1);
});

it('protects the form from the Android keyboard and bottom navigation inset', async () => {
  jest.replaceProperty(Platform, 'OS', 'android');
  const listener = jest.spyOn(Keyboard, 'addListener');
  try {
    const screen = await openForm();
    await fireEvent(screen.getByTestId('stock-create-keyboard'), 'layout', { persist: jest.fn(), nativeEvent: { layout: { x: 0, y: 100, width: 400, height: 700 } } });
    await fireEvent(screen.getByLabelText('Precio de compra'), 'focus', { nativeEvent: {} });
    expect(StyleSheet.flatten(screen.getByLabelText('Precio de compra').props.style).borderColor).toBe(COLORS.primary);
    await act(async () => {
      for (const [event, handler] of listener.mock.calls) {
        if (event === 'keyboardDidShow') handler({ endCoordinates: { screenX: 0, screenY: 500, width: 400, height: 300 }, duration: 0, easing: 'keyboard' } as never);
      }
    });
    expect(StyleSheet.flatten(screen.getByTestId('stock-create-keyboard').props.style).height).toBe(400);
    const style = StyleSheet.flatten(screen.getByTestId('stock-create-form').props.contentContainerStyle);
    expect(style.paddingBottom).toBe(SPACING.xxl + 24);
    expect(screen.getByLabelText('Precio de compra').props.onFocus).toEqual(expect.any(Function));
    await fireEvent(screen.getByLabelText('Precio de compra'), 'blur', { nativeEvent: {} });
    expect(StyleSheet.flatten(screen.getByLabelText('Precio de compra').props.style).borderColor).toBe(COLORS.border);
  } finally {
    jest.restoreAllMocks();
  }
});

it('uses decimal commas for quick-created product stock too', async () => {
  const screen = await render(<StockCreateScreen />);
  await fireEvent.changeText(screen.getByLabelText('Precio de compra'), '250,50');
  await fireEvent.press(screen.getByLabelText('Seleccionar producto'));
  await fireEvent.changeText(screen.getByPlaceholderText('Buscar...'), 'Producto casero');
  await fireEvent.press(screen.getByText('Cargar producto manualmente'));
  await fireEvent.changeText(screen.getByLabelText('Cantidad en stock *'), '0,25');
  await fireEvent.press(screen.getByRole('button', { name: 'Gramos' }));
  await fireEvent.press(screen.getByText('Cargar producto y stock'));
  await waitFor(() => expect(mockCreateManual).toHaveBeenCalledWith(7, {
    product: { name: 'Producto casero', unit_id: 1 },
    stock: { quantity: 0.25, unit_id: 1, stock_location_id: null, expiration_date: null, purchase_price: 250.5 },
  }));
  expect(mockCreate).not.toHaveBeenCalled();
});
