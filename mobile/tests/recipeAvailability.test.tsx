import React from 'react';
import { render } from '@testing-library/react-native';
import { AvailabilityPanel } from '../src/screens/RecipeDetailScreen';
import type { RecipeAvailability } from '../src/types/recipe';

jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('expo-router', () => ({ useRouter: () => ({}), useFocusEffect: jest.fn() }));

function availability(overrides: Partial<RecipeAvailability> = {}): RecipeAvailability {
  return { recipe_id: 1, status: 'not_possible', required_servings: 2, base_servings: 2, max_possible_servings: 0, coverage_percentage: 0, required_ingredients_count: 1, available_ingredients_count: 0, missing_ingredients_count: 1, can_cook: false, warnings: [], ingredients: [{ ingredient_id: 1, ingredient_name: 'Pollo', required_quantity: 500, available_quantity: 0, missing_quantity: 500, unit_id: 1, unit_name: 'gramos', unit_symbol: 'g', unit_compatible: true, status: 'missing', is_available: false }], ...overrides };
}

describe('Recipe availability panel', () => {
  it('shows complete availability', async () => { const screen = await render(<AvailabilityPanel availability={availability({ status: 'possible', can_cook: true, available_ingredients_count: 1, missing_ingredients_count: 0, ingredients: [{ ...availability().ingredients[0], available_quantity: 500, missing_quantity: 0, status: 'available', is_available: true }] })} />); expect(screen.getByText('Tenés todos los ingredientes.')).toBeTruthy(); expect(screen.getByText('1 de 1 ingredientes disponibles')).toBeTruthy(); });
  it('shows missing quantities and blocks conceptually', async () => { const screen = await render(<AvailabilityPanel availability={availability()} />); expect(screen.getByText('No podés cocinar esta receta todavía.')).toBeTruthy(); expect(screen.getByText(/Faltan 500/)).toBeTruthy(); });
  it('shows incompatible unit warning', async () => { const value = availability(); value.ingredients[0].unit_compatible = false; const screen = await render(<AvailabilityPanel availability={value} />); expect(screen.getByText('La unidad del stock no es compatible.')).toBeTruthy(); });
});
