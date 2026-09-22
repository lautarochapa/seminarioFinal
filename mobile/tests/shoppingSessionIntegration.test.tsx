import React from 'react';
import { Alert } from 'react-native';
import { act, fireEvent, render, waitFor } from '@testing-library/react-native';
import { ShoppingSessionScreen } from '../src/screens/ShoppingSessionScreen';
import { ApiError } from '../src/api/client';
import type { ShoppingListItem } from '../src/types/shopping';

const mockFinish = jest.fn();
const mockBudget = jest.fn();
const mockReplace = jest.fn();
const mockUpdate = jest.fn();
let mockItems: ShoppingListItem[];
jest.mock('../src/api/endpoints', () => ({
  shoppingSessionsApi: { finish: (...args: unknown[]) => mockFinish(...args) },
  shoppingListItemsApi: { update: (...args: unknown[]) => mockUpdate(...args) },
  budgetsApi: { current: (...args: unknown[]) => mockBudget(...args) },
}));
jest.mock('expo-router', () => ({ useRouter: () => ({ replace: mockReplace, push: jest.fn(), back: jest.fn() }) }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('../src/components/AppHeader', () => ({ AppHeader: 'AppHeader' }));
jest.mock('../src/components/MoneyText', () => {
  const { Text } = jest.requireActual<typeof import('react-native')>('react-native');
  return { MoneyText: ({ amount }: { amount: number }) => <Text testID="amount">{amount}</Text> };
});
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: { id: 7 } }) }));
jest.mock('../src/hooks/useShoppingListDetail', () => ({
  useShoppingListDetail: () => ({ list: { status: 'active' }, items: mockItems, loading: false, error: null, refresh: jest.fn() }),
}));

beforeEach(() => {
  jest.clearAllMocks();
  mockItems = [{ id: 1, product: { id: 1, name: 'Arroz' }, quantity: 2, unit: { id: 1, symbol: 'kg' }, status: 'purchased', actual_price: 300, estimated_price: 250 } as ShoppingListItem];
  mockBudget.mockResolvedValue({ data: null });
  mockUpdate.mockResolvedValue({ data: {} });
  mockFinish.mockResolvedValue({
    data: { id: 9, status: 'completed', purchase_id: 77 },
    summary: { purchase_id: 77, stock_created_count: 2, stock_updated_count: 1, stock_skipped_count: 1, stock_warnings: [] },
  });
  jest.spyOn(Alert, 'alert').mockImplementation(() => {});
});
afterEach(() => jest.restoreAllMocks());

it('shows an error for a negative price without sending it', async () => {
  const screen = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
  await fireEvent.changeText(screen.getByLabelText('Precio real de Arroz'), '-1');
  await fireEvent.press(screen.getByLabelText('Guardar precio real'));
  expect(screen.getByText('Ingresá un precio válido, mayor o igual a cero.')).toBeTruthy();
  expect(mockUpdate).not.toHaveBeenCalled();
});

it('accepts a decimal comma and retries the price operation, not the checkbox', async () => {
  mockUpdate.mockRejectedValueOnce(new Error('Offline'));
  const screen = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
  await fireEvent.changeText(screen.getByLabelText('Precio real de Arroz'), '12,50');
  await fireEvent.press(screen.getByLabelText('Guardar precio real'));
  await waitFor(() => expect(screen.getByText('No se pudo guardar el precio.')).toBeTruthy());
  await fireEvent.press(screen.getByLabelText('Reintentar item'));
  await waitFor(() => expect(mockUpdate).toHaveBeenCalledTimes(2));
  expect(mockUpdate).toHaveBeenNthCalledWith(1, 7, 55, 1, { actual_price: 12.5 });
  expect(mockUpdate).toHaveBeenNthCalledWith(2, 7, 55, 1, { actual_price: 12.5 });
});

it('does not finish while an edited price has not been saved', async () => {
  const screen = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
  await fireEvent.changeText(screen.getByLabelText('Precio real de Arroz'), '900');
  await fireEvent.press(screen.getByLabelText('Finalizar compra'));
  expect(Alert.alert).toHaveBeenCalledWith('Precios sin guardar', expect.any(String));
  expect(mockFinish).not.toHaveBeenCalled();
});

it('sends only one finish request when the confirmation callback is repeated', async () => {
  let resolveFinish!: (value: unknown) => void;
  mockFinish.mockImplementationOnce(() => new Promise((resolve) => { resolveFinish = resolve; }));
  const screen = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
  await fireEvent.press(screen.getByRole('button', { name: /Finalizar compra/ }));
  const confirm = jest.mocked(Alert.alert).mock.calls.find(([title]) => title === 'Finalizar compra')?.[2]?.find((button) => button.text === 'Finalizar');
  await act(() => { void confirm?.onPress?.(); void confirm?.onPress?.(); });
  expect(mockFinish).toHaveBeenCalledTimes(1);
  await act(async () => {
    resolveFinish({ data: { id: 9, status: 'completed', purchase_id: 77 }, summary: { purchase_id: 77 } });
  });
});

async function confirmFinish() {
  const screen = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
  await fireEvent.press(screen.getByRole('button', { name: /Finalizar compra/ }));
  const confirm = jest.mocked(Alert.alert).mock.calls.find(([title]) => title === 'Finalizar compra')?.[2]?.find((button) => button.text === 'Finalizar');
  expect(confirm).toBeTruthy();
  await act(async () => { await confirm?.onPress?.(); });
  return screen;
}

it('multiplies the unit price by the quantity and excludes pending items', async () => {
  mockItems.push({ ...mockItems[0], id: 2, status: 'pending', quantity: 5 });
  const screen = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
  expect(screen.getByTestId('amount').props.children).toBe(600);
});

it('uses estimated price only when actual price is missing, including fractional quantities', async () => {
  mockItems[0] = { ...mockItems[0], quantity: 0.5, actual_price: null };
  const screen = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
  expect(screen.getByTestId('amount').props.children).toBe(125);
});

it('does not invent a quantity when it is missing', async () => {
  mockItems[0] = { ...mockItems[0], quantity: null };
  const screen = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
  expect(screen.getByTestId('amount').props.children).toBe(0);
});

it('keeps an explicit zero actual price instead of replacing it with the estimate', async () => {
  mockItems[0] = { ...mockItems[0], actual_price: 0 };
  const screen = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
  expect(screen.getByTestId('amount').props.children).toBe(0);
});

it('shows stock counts returned by the real hook on the first completion and opens the purchase', async () => {
  await confirmFinish();
  expect(mockFinish).toHaveBeenCalledTimes(1);
  expect(mockFinish).toHaveBeenCalledWith(7, 9, undefined);
  const result = jest.mocked(Alert.alert).mock.calls.find(([title]) => title === 'Compra finalizada');
  expect(result?.[1]).toContain('2 producto(s) agregados al stock');
  expect(result?.[1]).toContain('1 actualizados en stock');
  expect(result?.[1]).toContain('1 no se pudieron agregar');
  await act(async () => { result?.[2]?.find((button) => button.text === 'OK')?.onPress?.(); });
  expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(app)/purchases/[id]', params: { id: '77' } });
});

it('shows the first finish failure immediately and never navigates or claims success', async () => {
  mockFinish.mockRejectedValue(new ApiError({ status: 409, code: 'SHOPPING_SESSION_ALREADY_FINISHED', message: 'La compra ya fue finalizada.', fieldErrors: {}, traceId: 'qa', isNetworkError: false, isTimeoutError: false }));
  await confirmFinish();
  await waitFor(() => expect(Alert.alert).toHaveBeenCalledWith('Error', 'La compra ya fue finalizada.'));
  expect(jest.mocked(Alert.alert).mock.calls.some(([title]) => title === 'Compra finalizada')).toBe(false);
  expect(mockReplace).not.toHaveBeenCalled();
  expect(mockBudget).not.toHaveBeenCalled();
});

it('keeps a successful purchase when the optional budget refresh fails', async () => {
  mockBudget.mockRejectedValue(new Error('Offline'));
  await confirmFinish();
  expect(jest.mocked(Alert.alert).mock.calls.find(([title]) => title === 'Compra finalizada')?.[1]).toContain('2 producto(s) agregados al stock');
  expect(mockFinish).toHaveBeenCalledTimes(1);
});
