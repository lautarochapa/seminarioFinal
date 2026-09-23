import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { PlanningScreen } from '../src/screens/PlanningScreen';
import { MealPlansScreen } from '../src/screens/MealPlansScreen';
import { weekRange, entriesInWeek } from '../src/utils/mealPlan';
import type { MealPlan, MealPlanEntry } from '../src/types/mealPlan';

const mockRouter = { push: jest.fn(), replace: jest.fn() };
const mockCreate = jest.fn();
let mockPlans: MealPlan[] = [];
const mockPlanning = jest.fn();
jest.mock('expo-router', () => ({ useRouter: () => mockRouter, useFocusEffect: jest.fn(), useLocalSearchParams: () => ({ addRecipeId: '12', addRecipeName: 'Tarta' }) }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));
jest.mock('../src/components/AppHeader', () => ({ AppHeader: 'AppHeader' }));
jest.mock('../src/components/FamilyGroupSelector', () => ({ FamilyGroupSelector: 'FamilyGroupSelector' }));
jest.mock('../src/auth/FamilyGroupContext', () => ({ useFamilyGroupContext: () => ({ selectedGroup: { id: 4 } }) }));
jest.mock('../src/hooks/useMealPlans', () => ({ useMealPlans: () => ({ data: mockPlans, loading: false, error: null, refresh: jest.fn() }) }));
jest.mock('../src/hooks/usePlanning', () => ({ usePlanning: (...args: unknown[]) => { mockPlanning(...args); return { data: mockPlans, loading: false, error: null, refresh: jest.fn() }; } }));
jest.mock('../src/api/endpoints', () => ({ mealPlansApi: { createPlan: (...args: unknown[]) => mockCreate(...args) } }));

const entry = (id: number, date: string, name: string): MealPlanEntry => ({ id, date, meal_type_id: 1, recipe_id: id, recipe: { id, name }, free_meal_description: null, is_eating_out: false, servings_total: '1.50', notes: null, status: 'planned' });
const plan = (id: number, entries: MealPlanEntry[], offset = 0): MealPlan => ({ id, family_group_id: 4, created_by: 1, period_type: 'weekly', mode: 'manual', status: 'draft', ...weekRange(offset), items: entries });
beforeEach(() => { jest.clearAllMocks(); mockPlans = []; mockCreate.mockResolvedValue({ data: { id: 9 } }); });

it('changes actual meals with the selected week and shows empty weeks', async () => {
  const current = weekRange(); const next = weekRange(1);
  mockPlans = [plan(1, [entry(1, current.start_date, 'Arroz'), entry(2, next.start_date, 'Tarta')]), plan(2, [entry(3, current.end_date, 'Sopa')])];
  const screen = await render(<PlanningScreen />);
  expect(screen.getByText('Arroz')).toBeTruthy(); expect(screen.getByText('Sopa')).toBeTruthy(); expect(screen.queryByText('Tarta')).toBeNull();
  await fireEvent.press(screen.getByLabelText('Semana siguiente'));
  expect(screen.getByText('Tarta')).toBeTruthy(); expect(screen.queryByText('Arroz')).toBeNull();
  expect(mockPlanning).toHaveBeenLastCalledWith(4, next.start_date, next.end_date);
  await fireEvent.press(screen.getByLabelText('Semana siguiente'));
  expect(screen.getByText('No hay comidas planificadas.')).toBeTruthy();
  await fireEvent.press(screen.getByLabelText('Semana actual'));
  expect(screen.getByText('Arroz')).toBeTruthy();
});

it('creates the first weekly plan and preserves the recipe selection', async () => {
  const screen = await render(<MealPlansScreen />);
  await fireEvent.press(screen.getByRole('button', { name: 'Crear plan semanal' }));
  await waitFor(() => expect(mockCreate).toHaveBeenCalledWith(4, { period_type: 'weekly', ...weekRange() }));
  expect(mockRouter.push).toHaveBeenCalledWith({ pathname: '/(app)/meal-plans/[id]', params: { id: '9', addRecipeId: '12', addRecipeName: 'Tarta' } });
});

it('opens an existing week without creating a duplicate', async () => {
  mockPlans = [plan(8, [])];
  const screen = await render(<MealPlansScreen />);
  await fireEvent.press(screen.getByRole('button', { name: 'Crear plan semanal' }));
  expect(mockCreate).not.toHaveBeenCalled();
  expect(mockRouter.push).toHaveBeenCalledWith(expect.objectContaining({ params: expect.objectContaining({ id: '8' }) }));
});

it('handles Sunday and year boundaries in local calendar dates', () => {
  expect(weekRange(0, new Date(2027, 0, 3, 1))).toEqual({ start_date: '2026-12-28', end_date: '2027-01-03' });
  expect(entriesInWeek([plan(1, [entry(1, '2026-12-27', 'Fuera'), entry(2, '2027-01-01', 'Dentro')])], '2026-12-28', '2027-01-03').map(([date]) => date)).toEqual(['2027-01-01']);
});
