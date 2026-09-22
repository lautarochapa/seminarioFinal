import React from 'react';
import { StyleSheet } from 'react-native';
import { fireEvent, render } from '@testing-library/react-native';
import { ShoppingListDetailScreen } from '../src/screens/ShoppingListDetailScreen';

let mockStatus = 'in_progress';
let mockInsets = { top: 24, bottom: 48, left: 0, right: 0 };
jest.mock('../src/api/endpoints', () => ({ shoppingListItemsApi: {}, shoppingListsApi: {} }));
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: { id: 7, name: 'Hogar QA' } }) }));
jest.mock('../src/hooks/useShoppingListDetail', () => ({
  useShoppingListDetail: () => ({
    list: { id: 5, status: mockStatus },
    items: [{ id: 10, status: 'purchased', product: { id: 12, name: 'QA arroz' }, quantity: 2, unit: { symbol: 'kg' } }],
    loading: false, error: null, refresh: jest.fn(),
  }),
}));
jest.mock('../src/hooks/useProducts', () => ({ useProducts: () => ({ data: [], loading: false, setFilters: jest.fn() }) }));
jest.mock('../src/hooks/useUnits', () => ({ useUnits: () => ({ data: [] }) }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('react-native-safe-area-context', () => ({ useSafeAreaInsets: () => mockInsets }));
jest.mock('expo-router', () => ({ useRouter: () => ({ push: jest.fn() }) }));

beforeEach(() => {
  mockStatus = 'in_progress';
  mockInsets = { top: 24, bottom: 48, left: 0, right: 0 };
});

it.each(['complete', 'add'])('keeps the %s modal outside the status and navigation bars', async (mode) => {
  mockStatus = mode === 'complete' ? 'in_progress' : 'active';
  const screen = await render(<ShoppingListDetailScreen listId={5} />);
  await fireEvent.press(screen.getByLabelText(mode === 'complete' ? 'Finalizar compra' : 'Agregar item'));
  const modalContent = screen.getByLabelText('Cerrar').parent?.parent;
  expect(modalContent).toBeTruthy();
  const style = StyleSheet.flatten(modalContent!.props.style);
  expect(style.paddingTop).toBe(24);
  expect(style.paddingBottom).toBe(48);
});

it('updates modal padding when the safe area changes', async () => {
  const screen = await render(<ShoppingListDetailScreen listId={5} />);
  await fireEvent.press(screen.getByLabelText('Finalizar compra'));
  mockInsets = { top: 0, bottom: 16, left: 0, right: 0 };
  await screen.rerender(<ShoppingListDetailScreen listId={5} />);
  const modalContent = screen.getByLabelText('Cerrar').parent?.parent;
  const style = StyleSheet.flatten(modalContent!.props.style);
  expect(style.paddingTop).toBe(0);
  expect(style.paddingBottom).toBe(16);
});
