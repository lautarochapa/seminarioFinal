import React from 'react';
import { render } from '@testing-library/react-native';
import { RecipeIngredientRow } from '../src/components/RecipeIngredientRow';
import type { RecipeIngredient } from '../src/types/recipe';

const ingredient = (values: Partial<RecipeIngredient> = {}): RecipeIngredient => ({
  id: 1, ingredient_id: 543, ingredient_name: 'Sal', quantity: '0.0000', unit_id: 5,
  unit_name: 'Unidad', is_optional: true, notes: 'Sal según necesites', sort_order: 0, ...values,
});

describe('Recipe ingredient quantity display', () => {
  it.each([0, '0.0000'])('shows free quantity for optional zero %s and preserves the original note', async (quantity) => {
    const screen = await render(<RecipeIngredientRow item={ingredient({ quantity })} />);
    expect(screen.getByText('A gusto / cantidad necesaria')).toBeTruthy();
    expect(screen.getByText('Sal según necesites')).toBeTruthy();
    expect(screen.queryByText(/0.*Unidad/)).toBeNull();
    expect(screen.queryByText(/opcional/)).toBeNull();
  });

  it('retains a measured optional ingredient and its preparation note', async () => {
    const screen = await render(<RecipeIngredientRow item={ingredient({ quantity: 2, unit_name: 'Gramo', notes: 'Picada' })} />);
    expect(screen.getByText('2 Gramo · opcional')).toBeTruthy();
    expect(screen.getByText('Picada')).toBeTruthy();
    expect(screen.queryByText('A gusto / cantidad necesaria')).toBeNull();
  });

  it.each([null, '', 0])('does not infer free quantity from a non-optional or missing value %s', async (quantity) => {
    const screen = await render(<RecipeIngredientRow item={ingredient({ quantity, is_optional: quantity === null || quantity === '', notes: null })} />);
    expect(screen.queryByText('A gusto / cantidad necesaria')).toBeNull();
  });
});
