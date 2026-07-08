import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import { RecipeDetailScreen } from '../src/screens/RecipeDetailScreen';
import type { RecipeShoppingListResult } from '../src/types/recipe';

jest.mock('@expo/vector-icons', () => ({
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}));

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 24, bottom: 0, left: 0, right: 0 }),
}));

const mockPush = jest.fn();
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush, replace: jest.fn(), back: jest.fn() }),
}));

jest.mock('../src/auth/FamilyGroupContext', () => ({
  useFamilyGroupContext: () => ({ selectedGroup: { id: 7, name: 'Grupo' } }),
}));

const RECIPE = {
  id: 3,
  name: 'Tarta',
  description: null,
  servings: 4,
  prep_time_minutes: 10,
  cook_time_minutes: 20,
  category: null,
  tags: [],
  ingredients: [],
  steps: [],
};

jest.mock('../src/hooks/useRecipeDetail', () => ({
  useRecipeDetail: () => ({ data: RECIPE, loading: false, error: null, refresh: jest.fn() }),
}));

jest.mock('../src/hooks/useRecipeFavorites', () => ({
  useRecipeFavorites: () => ({ favoriteIds: new Set(), savingIds: {}, toggle: jest.fn().mockResolvedValue(undefined) }),
}));

jest.mock('../src/hooks/useSupermarkets', () => ({
  useSupermarkets: () => ({ data: [{ id: 1, name: 'Cadena A' }], loading: false }),
}));

jest.mock('../src/hooks/useBranches', () => ({
  useBranches: () => ({ data: [{ id: 10, name: 'Sucursal 1' }], loading: false }),
}));

const mockGenerate = jest.fn();
jest.mock('../src/api/endpoints', () => ({
  recipeShoppingListApi: { generate: (...args: unknown[]) => mockGenerate(...args) },
}));

function resultWith(overrides: Partial<RecipeShoppingListResult>): { data: RecipeShoppingListResult } {
  return {
    data: {
      shopping_list: {
        id: 55,
        family_group_id: 7,
        meal_plan_id: null,
        source_type: 'recipe',
        status: 'draft',
        estimated_total: null,
        optimization_mode: null,
        items: [
          { id: 100, ingredient: null, product: { id: 1, name: 'Harina' }, quantity: 2, unit: { id: 1, code: 'un', symbol: 'un' }, estimated_price: 500, estimated_subtotal: 1000, actual_price: null, status: 'pending', notes: null, price_source: 'best_available', price_updated_at: null, supermarket_chain_id: null, supermarket_branch_id: null, source_type: 'recipe_generation', source_id: 3, created_at: '', updated_at: '' },
        ],
        created_at: '',
        updated_at: '',
        deleted_at: null,
      },
      items_added: 1,
      items_skipped_duplicate: 0,
      unmapped_ingredients: [],
      priced_items: [{
        shopping_list_item_id: 100,
        ingredient_id: null,
        product_id: 1,
        requested_quantity: 2,
        requested_unit_id: 1,
        purchase_quantity: 2,
        purchase_unit_id: 1,
        estimated_unit_price: 500,
        estimated_subtotal: 1000,
        price_source: 'best_available',
        price_updated_at: null,
        supermarket_branch_id: null,
        supermarket_chain_id: null,
      }],
      estimated_total: 1000,
      items_without_price: 0,
      warnings: [],
      substitutions: [],
      ...overrides,
    },
  };
}

beforeEach(() => {
  jest.clearAllMocks();
});

describe('RecipeDetailScreen — generar lista desde receta', () => {
  it('generates a list with prices and shows the summary and priced item', async () => {
    mockGenerate.mockResolvedValue(resultWith({}));
    const { getByText, getAllByText, getByRole, findByText } = await render(<RecipeDetailScreen recipeId={3} />);

    await fireEvent.press(getByRole('button', { name: 'Generar lista de compras' }));

    expect(await findByText('Resumen de la generación')).toBeTruthy();
    expect(getByText('Harina')).toBeTruthy();
    expect(getAllByText(/ARS.*1.000|ARS.*1,000/).length).toBeGreaterThan(0);
  });

  it('shows "Sin precio disponible" when the item has no price', async () => {
    mockGenerate.mockResolvedValue(resultWith({
      priced_items: [{
        shopping_list_item_id: 100,
        ingredient_id: null,
        product_id: 1,
        requested_quantity: 2,
        requested_unit_id: 1,
        purchase_quantity: 2,
        purchase_unit_id: 1,
        estimated_unit_price: null,
        estimated_subtotal: null,
        price_source: null,
        price_updated_at: null,
        supermarket_branch_id: null,
        supermarket_chain_id: null,
      }],
      items_without_price: 1,
      estimated_total: 0,
    }));
    const { getByRole, findByText } = await render(<RecipeDetailScreen recipeId={3} />);

    await fireEvent.press(getByRole('button', { name: 'Generar lista de compras' }));

    expect(await findByText('Sin precio disponible')).toBeTruthy();
  });

  it('shows unmapped ingredients as warnings', async () => {
    mockGenerate.mockResolvedValue(resultWith({
      priced_items: [],
      items_added: 0,
      unmapped_ingredients: [{ ingredient_id: 9, ingredient_name: 'Sal', reason: 'INGREDIENT_OR_UNIT_MISSING' }],
    }));
    const { getByText, getByRole, findByText } = await render(<RecipeDetailScreen recipeId={3} />);

    await fireEvent.press(getByRole('button', { name: 'Generar lista de compras' }));

    expect(await findByText(/Sal no se pudo mapear/)).toBeTruthy();
  });

  it('shows a substitution warning when a substitute ingredient was used', async () => {
    mockGenerate.mockResolvedValue(resultWith({
      substitutions: [{
        original_ingredient_id: 5,
        resolved_ingredient_id: 6,
        resolved_ingredient_name: 'Harina integral',
        substitution_used: true,
        reason: 'Sustituto habitual',
      }],
    }));
    const { getByRole, findByText } = await render(<RecipeDetailScreen recipeId={3} />);

    await fireEvent.press(getByRole('button', { name: 'Generar lista de compras' }));

    expect(await findByText(/Se usará Harina integral como reemplazo/)).toBeTruthy();
  });

  it('sends the selected chain and branch when generating', async () => {
    mockGenerate.mockResolvedValue(resultWith({}));
    const { getByLabelText, getByRole } = await render(<RecipeDetailScreen recipeId={3} />);

    await fireEvent.press(getByLabelText('Cadena A'));
    await waitFor(() => expect(getByLabelText('Sucursal 1')).toBeTruthy());
    await fireEvent.press(getByLabelText('Sucursal 1'));
    await waitFor(() => {
      expect(getByLabelText('Sucursal 1').props.accessibilityState?.selected).toBe(true);
    });
    await fireEvent.press(getByRole('button', { name: 'Generar lista de compras' }));

    await waitFor(() => {
      expect(mockGenerate).toHaveBeenCalledWith(7, 3, {
        supermarket_chain_id: 1,
        supermarket_branch_id: 10,
      });
    });
  });

  it('navigates to the generated list when "Abrir lista" is pressed', async () => {
    mockGenerate.mockResolvedValue(resultWith({}));
    const { getByText, getByRole, findByText } = await render(<RecipeDetailScreen recipeId={3} />);

    await fireEvent.press(getByRole('button', { name: 'Generar lista de compras' }));
    await findByText('Resumen de la generación');
    await fireEvent.press(getByRole('button', { name: 'Abrir lista' }));

    expect(mockPush).toHaveBeenCalledWith({ pathname: '/(app)/shopping-lists/[id]', params: { id: '55' } });
  });
});
