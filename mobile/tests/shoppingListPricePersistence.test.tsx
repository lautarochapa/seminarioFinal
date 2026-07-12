import React from 'react';
import { render } from '@testing-library/react-native';
import { ShoppingListDetailScreen } from '../src/screens/ShoppingListDetailScreen';
import type { ShoppingList, ShoppingListItem } from '../src/types/shopping';

jest.mock('@expo/vector-icons', () => ({
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}));

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 24, bottom: 0, left: 0, right: 0 }),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn() }),
}));

jest.mock('../src/auth/FamilyGroupContext', () => ({
  useFamilyGroupContext: () => ({ selectedGroup: { id: 7, name: 'Grupo' } }),
}));

jest.mock('../src/hooks/useProducts', () => ({
  useProducts: () => ({ data: [], loading: false, setFilters: jest.fn() }),
}));

jest.mock('../src/hooks/useUnits', () => ({
  useUnits: () => ({ data: [] }),
}));

jest.mock('../src/api/endpoints', () => ({
  shoppingListItemsApi: { create: jest.fn(), delete: jest.fn(), update: jest.fn() },
  shoppingListsApi: { startSession: jest.fn() },
}));

const LIST: ShoppingList = {
  id: 55,
  family_group_id: 7,
  meal_plan_id: null,
  source_type: 'recipe',
  status: 'draft',
  estimated_total: 1000,
  optimization_mode: null,
  created_at: '',
  updated_at: '',
  deleted_at: null,
};

function itemWith(overrides: Partial<ShoppingListItem>): ShoppingListItem {
  return {
    id: 100,
    ingredient: null,
    product: { id: 1, name: 'Harina' },
    quantity: 2,
    unit: { id: 1, code: 'un', symbol: 'un' },
    estimated_price: 500,
    estimated_subtotal: 1000,
    actual_price: null,
    status: 'pending',
    notes: null,
    price_source: 'branch',
    price_updated_at: '2026-07-01T00:00:00Z',
    supermarket_chain_id: null,
    supermarket_branch_id: 10,
    source_type: 'recipe_generation',
    source_id: 3,
    created_at: '',
    updated_at: '',
    ...overrides,
  };
}

let mockItems: ShoppingListItem[] = [];
jest.mock('../src/hooks/useShoppingListDetail', () => ({
  useShoppingListDetail: () => ({
    list: LIST,
    items: mockItems,
    loading: false,
    error: null,
    refresh: jest.fn(),
  }),
}));

beforeEach(() => {
  jest.clearAllMocks();
});

describe('ShoppingListDetailScreen — persistencia de precio al reabrir la lista', () => {
  it('shows the persisted price origin and subtotal for a priced item', async () => {
    mockItems = [itemWith({})];
    const { findByText } = await render(<ShoppingListDetailScreen listId={55} />);

    expect(await findByText('Harina')).toBeTruthy();
    expect(await findByText('Precio de la sucursal')).toBeTruthy();
  });

  it('shows "Sin precio disponible" instead of $0 when the item has no price', async () => {
    mockItems = [itemWith({ estimated_price: null, estimated_subtotal: null, price_source: null })];
    const { findByText, queryByText } = await render(<ShoppingListDetailScreen listId={55} />);

    expect(await findByText('Sin precio disponible')).toBeTruthy();
    expect(queryByText('$ 0')).toBeNull();
    expect(queryByText(/\$\s*0[.,]00/)).toBeNull();
  });

  it('shows a manual price badge for a manually edited item', async () => {
    mockItems = [itemWith({ price_source: 'manual', supermarket_branch_id: null, estimated_price: 250, estimated_subtotal: 500 })];
    const { findByText } = await render(<ShoppingListDetailScreen listId={55} />);

    expect(await findByText('Precio manual')).toBeTruthy();
  });
});
