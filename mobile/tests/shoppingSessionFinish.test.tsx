import React from 'react';
import { Alert } from 'react-native';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import { ShoppingSessionScreen } from '../src/screens/ShoppingSessionScreen';
import type { ShoppingList, ShoppingListItem } from '../src/types/shopping';

jest.mock('@expo/vector-icons', () => ({
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}));

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 24, bottom: 0, left: 0, right: 0 }),
}));

const mockReplace = jest.fn();
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: mockReplace, back: jest.fn() }),
}));

jest.mock('../src/auth/FamilyGroupContext', () => ({
  useFamilyGroupContext: () => ({ selectedGroup: { id: 7, name: 'Grupo' } }),
}));

const LIST: ShoppingList = {
  id: 55,
  family_group_id: 7,
  meal_plan_id: null,
  source_type: 'recipe',
  status: 'active',
  estimated_total: 1000,
  optimization_mode: null,
  created_at: '',
  updated_at: '',
  deleted_at: null,
};

const ITEM: ShoppingListItem = {
  id: 100,
  ingredient: null,
  product: { id: 1, name: 'Harina' },
  quantity: 2,
  unit: { id: 1, code: 'un', symbol: 'un' },
  estimated_price: 500,
  estimated_subtotal: 1000,
  actual_price: null,
  status: 'purchased',
  notes: null,
  price_source: 'best_available',
  price_updated_at: null,
  supermarket_chain_id: null,
  supermarket_branch_id: null,
  source_type: null,
  source_id: null,
  created_at: '',
  updated_at: '',
};

const mockRefresh = jest.fn();
jest.mock('../src/hooks/useShoppingListDetail', () => ({
  useShoppingListDetail: () => ({
    list: LIST,
    items: [ITEM],
    loading: false,
    error: null,
    refresh: mockRefresh,
  }),
}));

const mockFinishSession = jest.fn();
let mockFinishSummary: unknown = null;
let mockSessionError: unknown = null;
jest.mock('../src/hooks/useShoppingSession', () => ({
  useShoppingSession: () => ({
    finishing: false,
    error: mockSessionError,
    finishSession: mockFinishSession,
    finishSummary: mockFinishSummary,
  }),
}));

const mockBudgetCurrent = jest.fn();
jest.mock('../src/api/endpoints', () => ({
  shoppingListItemsApi: { update: jest.fn() },
  budgetsApi: { current: (...args: unknown[]) => mockBudgetCurrent(...args) },
}));

beforeEach(() => {
  jest.clearAllMocks();
  mockFinishSummary = null;
  mockSessionError = null;
  mockBudgetCurrent.mockResolvedValue({ data: null, trace_id: 't1' });
});

function pressFinishAndConfirm() {
  const alertSpy = jest.spyOn(Alert, 'alert').mockImplementation((_title, _msg, buttons) => {
    const confirmButton = buttons?.find((b) => b.text === 'Finalizar');
    confirmButton?.onPress?.();
  });
  return alertSpy;
}

describe('ShoppingSessionScreen — finish()', () => {
  it('creates stock and shows a summary mentioning created items', async () => {
    mockFinishSummary = { purchase_id: 42, stock_created_count: 2, stock_updated_count: 0, stock_skipped_count: 0, stock_warnings: [] };
    mockFinishSession.mockResolvedValue({ id: 9, status: 'completed' });

    const alertSpy = pressFinishAndConfirm();
    const { getByRole } = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
    await fireEvent.press(getByRole('button', { name: /Finalizar compra/ }));

    await waitFor(() => expect(mockFinishSession).toHaveBeenCalledWith(9));

    const resultAlertCall = alertSpy.mock.calls.find((c) => c[0] === 'Compra finalizada');
    expect(resultAlertCall).toBeTruthy();
    expect(String(resultAlertCall?.[1])).toContain('2 producto(s) agregados al stock');
  });

  it('shows the updated budget spent/available amounts when a budget exists for the period', async () => {
    mockFinishSummary = { purchase_id: 42, stock_created_count: 1, stock_updated_count: 0, stock_skipped_count: 0, stock_warnings: [] };
    mockFinishSession.mockResolvedValue({ id: 9, status: 'completed' });
    mockBudgetCurrent.mockResolvedValue({
      data: { id: 1, family_group_id: 7, year: 2026, month: 7, total_amount: 10000, currency: 'ARS', status: 'active', used_amount: 3000, available_amount: 7000, consumed_percent: 30 },
      trace_id: 't1',
    });

    const alertSpy = pressFinishAndConfirm();
    const { getByRole } = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
    await fireEvent.press(getByRole('button', { name: /Finalizar compra/ }));

    await waitFor(() => expect(mockBudgetCurrent).toHaveBeenCalledWith(7));

    await waitFor(() => {
      const resultAlertCall = alertSpy.mock.calls.find((c) => c[0] === 'Compra finalizada');
      expect(resultAlertCall).toBeTruthy();
      expect(String(resultAlertCall?.[1])).toContain('Presupuesto actualizado');
      expect(String(resultAlertCall?.[1])).toMatch(/Gastado este mes.*3.000|Gastado este mes.*3,000/);
      expect(String(resultAlertCall?.[1])).toMatch(/Disponible.*7.000|Disponible.*7,000/);
    });
  });

  it('does not show a budget line when there is no budget for the period', async () => {
    mockFinishSummary = { purchase_id: 42, stock_created_count: 1, stock_updated_count: 0, stock_skipped_count: 0, stock_warnings: [] };
    mockFinishSession.mockResolvedValue({ id: 9, status: 'completed' });
    mockBudgetCurrent.mockResolvedValue({ data: null, trace_id: 't1' });

    const alertSpy = pressFinishAndConfirm();
    const { getByRole } = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
    await fireEvent.press(getByRole('button', { name: /Finalizar compra/ }));

    await waitFor(() => expect(mockBudgetCurrent).toHaveBeenCalled());

    await waitFor(() => {
      const resultAlertCall = alertSpy.mock.calls.find((c) => c[0] === 'Compra finalizada');
      expect(resultAlertCall).toBeTruthy();
      expect(String(resultAlertCall?.[1])).not.toContain('Presupuesto actualizado');
    });
  });

  it('reports updated stock counts in the summary', async () => {
    mockFinishSummary = { purchase_id: 42, stock_created_count: 0, stock_updated_count: 3, stock_skipped_count: 0, stock_warnings: [] };
    mockFinishSession.mockResolvedValue({ id: 9, status: 'completed' });

    const alertSpy = pressFinishAndConfirm();
    const { getByRole } = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
    await fireEvent.press(getByRole('button', { name: /Finalizar compra/ }));

    await waitFor(() => expect(mockFinishSession).toHaveBeenCalled());

    const resultAlertCall = alertSpy.mock.calls.find((c) => c[0] === 'Compra finalizada');
    expect(String(resultAlertCall?.[1])).toContain('3 actualizados en stock');
  });

  it('shows warnings for items skipped without stock impact', async () => {
    mockFinishSummary = {
      purchase_id: 42,
      stock_created_count: 0,
      stock_updated_count: 0,
      stock_skipped_count: 1,
      stock_warnings: [{ shopping_list_item_id: 100, reason: 'NO_PRODUCT_MATCH' }],
    };
    mockFinishSession.mockResolvedValue({ id: 9, status: 'completed' });

    const alertSpy = pressFinishAndConfirm();
    const { getByRole } = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
    await fireEvent.press(getByRole('button', { name: /Finalizar compra/ }));

    await waitFor(() => expect(mockFinishSession).toHaveBeenCalled());

    const resultAlertCall = alertSpy.mock.calls.find((c) => c[0] === 'Compra finalizada');
    expect(String(resultAlertCall?.[1])).toContain('1 no se pudieron agregar');
  });

  it('navigates to the created purchase when purchase_id is returned', async () => {
    mockFinishSummary = { purchase_id: 77, stock_created_count: 1, stock_updated_count: 0, stock_skipped_count: 0, stock_warnings: [] };
    mockFinishSession.mockResolvedValue({ id: 9, status: 'completed', purchase_id: 77 });

    const alertSpy = pressFinishAndConfirm();
    const { getByRole } = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
    await fireEvent.press(getByRole('button', { name: /Finalizar compra/ }));

    await waitFor(() => expect(mockFinishSession).toHaveBeenCalled());

    const resultAlertCall = alertSpy.mock.calls.find((c) => c[0] === 'Compra finalizada');
    const okButton = resultAlertCall?.[2]?.find((b: { text?: string }) => b.text === 'OK');
    okButton?.onPress?.();

    expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(app)/purchases/[id]', params: { id: '77' } });
  });

  it('shows an error message on 409 without navigating', async () => {
    mockFinishSession.mockResolvedValue(null);
    mockSessionError = { status: 409, code: 'SHOPPING_SESSION_ALREADY_FINISHED', message: 'La sesión ya fue finalizada.', fieldErrors: {}, traceId: 't1', isNetworkError: false, isTimeoutError: false };

    const alertSpy = pressFinishAndConfirm();
    const { getByRole } = await render(<ShoppingSessionScreen listId={55} sessionId={9} />);
    await fireEvent.press(getByRole('button', { name: /Finalizar compra/ }));

    await waitFor(() => expect(mockFinishSession).toHaveBeenCalled());

    const errorAlertCall = alertSpy.mock.calls.find((c) => c[0] === 'Error');
    expect(errorAlertCall).toBeTruthy();
    expect(String(errorAlertCall?.[1])).toContain('La sesión ya fue finalizada.');
    expect(mockReplace).not.toHaveBeenCalled();
  });
});
