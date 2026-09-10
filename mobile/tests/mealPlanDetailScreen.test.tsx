import React from 'react';
import { Alert } from 'react-native';
import { act, fireEvent, render, waitFor } from '@testing-library/react-native';
import { MealPlanDetailScreen } from '../src/screens/MealPlanDetailScreen';
import type { MealPlan } from '../src/types/mealPlan';

declare const require: (name: string) => { readFileSync: (f: string, e: string) => string; join: (...p: string[]) => string };
declare const __dirname: string;
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const readSrc = (rel: string): string => fs.readFileSync(path.join(root, rel), 'utf8');

const ENTRY = {
  id: 21, date: '2026-06-16', meal_type_id: 5,
  meal_type: { id: 5, code: 'lunch', name: 'Almuerzo' },
  recipe_id: 12, recipe: { id: 12, name: 'Tarta' },
  free_meal_description: null, is_eating_out: false, servings_total: 3, notes: null, status: 'planned',
} as const;

const mockPush = jest.fn();
const mockCreateItem = jest.fn();
const mockDeleteItem = jest.fn();
const mockMealTypesList = jest.fn();
const mockRefresh = jest.fn();
let mockDetail: { data: MealPlan | null; loading: boolean; error: null; refresh: jest.Mock };

jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('expo-router', () => ({ useRouter: () => ({ push: mockPush }) }));
jest.mock('../src/utils/navigation', () => ({ goBackOrHome: jest.fn() }));
jest.mock('../src/components/AppHeader', () => ({ AppHeader: 'AppHeader' }));
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: { id: 4, name: 'Casa' } }) }));
jest.mock('../src/hooks/useMealPlanDetail', () => ({ useMealPlanDetail: () => mockDetail }));
jest.mock('../src/api/endpoints', () => ({
  mealPlansApi: {
    createItem: (...args: unknown[]) => mockCreateItem(...args),
    deleteItem: (...args: unknown[]) => mockDeleteItem(...args),
    generateShoppingList: jest.fn(),
  },
  mealTypesApi: { list: (...args: unknown[]) => mockMealTypesList(...args) },
}));

function plan(overrides: Partial<MealPlan> = {}): MealPlan {
  return {
    id: 7,
    family_group_id: 4,
    created_by: 1,
    period_type: 'weekly',
    start_date: '2026-06-16',
    end_date: '2026-06-22',
    mode: 'manual',
    status: 'draft',
    items: [],
    ...overrides,
  };
}

describe('Phase 6 manual meal planning wiring', () => {
  it('exposes create/update/delete item and meal-types endpoints', () => {
    const endpoints = readSrc(path.join('src', 'api', 'endpoints.ts'));
    expect(endpoints).toContain('/api/v1/family-groups/${groupId}/meal-plans/${planId}/items');
    expect(endpoints).toContain('/api/v1/family-groups/${groupId}/meal-plans/${planId}/items/${itemId}');
    expect(endpoints).toContain("apiClient.get<ApiResponse<MealType[]>>('/api/v1/meal-types')");
    expect(endpoints).toMatch(/createItem\(groupId: number, planId: number/);
    expect(endpoints).toMatch(/updateItem\(groupId: number, planId: number, itemId: number/);
    expect(endpoints).toMatch(/deleteItem\(groupId: number, planId: number, itemId: number/);
  });

  it('adds the "Agregar al plan" CTA from the recipe detail that preselects the recipe', () => {
    const detail = readSrc(path.join('src', 'screens', 'RecipeDetailScreen.tsx'));
    expect(detail).toContain('Agregar al plan');
    expect(detail).toContain("pathname: '/(app)/meal-plans'");
    expect(detail).toContain('addRecipeId: String(recipeId)');
    expect(detail).toContain('addRecipeName: data.name');

    const plansScreen = readSrc(path.join('src', 'screens', 'MealPlansScreen.tsx'));
    expect(plansScreen).toContain('addRecipeId');
    const route = readSrc(path.join('app', '(app)', 'meal-plans', '[id].tsx'));
    expect(route).toContain('preselectRecipeId');
  });
});

describe('MealPlanDetailScreen manual planning flow', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockMealTypesList.mockResolvedValue({ data: [{ id: 5, code: 'lunch', name: 'Almuerzo' }] });
    mockCreateItem.mockResolvedValue({ data: { id: 99 } });
    mockDeleteItem.mockResolvedValue({ message: 'ok' });
    mockDetail = { data: plan({ items: [{ ...ENTRY }] }), loading: false, error: null, refresh: mockRefresh };
  });

  it('adds a recipe to a date and deletes an entry after confirmation', async () => {
    const alertSpy = jest.spyOn(Alert, 'alert').mockImplementation((_t, _m, buttons) => {
      const confirm = (buttons ?? []).find((b) => b.style === 'destructive');
      confirm?.onPress?.();
    });

    const screen = await render(<MealPlanDetailScreen planId={7} preselectRecipeId={12} preselectRecipeName="Tarta" />);
    await waitFor(() => expect(mockMealTypesList).toHaveBeenCalled());
    await act(async () => { await Promise.resolve(); });

    fireEvent.press(screen.getByText('Agregar comida'));
    await waitFor(() => expect(screen.getAllByText('Almuerzo').length).toBeGreaterThan(1));
    const chips = screen.getAllByText('Almuerzo');
    fireEvent.press(chips[chips.length - 1]);
    fireEvent.press(screen.getByText('Guardar'));

    await waitFor(() => expect(mockCreateItem).toHaveBeenCalledWith(4, 7, expect.objectContaining({
      date: '2026-06-16',
      meal_type_id: 5,
      recipe_id: 12,
    })));
    expect(mockCreateItem.mock.calls[0][2]).toHaveProperty('servings_total');

    fireEvent.press(screen.getByText('Quitar'));
    await waitFor(() => expect(mockDeleteItem).toHaveBeenCalledWith(4, 7, 21));
    expect(mockRefresh).toHaveBeenCalledTimes(2);
    alertSpy.mockRestore();
  });
});
