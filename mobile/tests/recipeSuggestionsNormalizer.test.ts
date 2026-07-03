import { normalizeRecipeSuggestions } from '@/utils/recipeSuggestions';

describe('normalizeRecipeSuggestions', () => {
  it('normalizes the real backend flat data shape', () => {
    const result = normalizeRecipeSuggestions({
      data: [
        { id: 1, name: 'Arroz con pollo', score: 1, reasons: ['official_recipe'] },
      ],
      meta: { current_page: 1, per_page: 5, total: 1, last_page: 1 },
      trace_id: 'trace',
    });

    expect(result.invalidCount).toBe(0);
    expect(result.response.data[0].recipe.id).toBe(1);
    expect(result.response.data[0].recipe.name).toBe('Arroz con pollo');
    expect(result.response.data[0].reason).toBe('official_recipe');
  });

  it('normalizes data.data payloads', () => {
    const result = normalizeRecipeSuggestions({
      data: {
        data: [{ id: 2, name: 'Tortilla' }],
        meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
      },
    });

    expect(result.response.data).toHaveLength(1);
    expect(result.response.data[0].recipe.id).toBe(2);
  });

  it('normalizes nested recipe payloads', () => {
    const result = normalizeRecipeSuggestions({
      data: [{ recipe: { id: 3, name: 'Bizcochuelo' }, reason: 'available' }],
    });

    expect(result.response.data[0].recipe.id).toBe(3);
    expect(result.response.data[0].reason).toBe('available');
  });

  it('normalizes suggestion.recipe payloads', () => {
    const result = normalizeRecipeSuggestions({
      data: [{ suggestion: { recipe: { id: 4, name: 'Sopa' } }, score: 2 }],
    });

    expect(result.response.data[0].recipe.id).toBe(4);
    expect(result.response.data[0].score).toBe(2);
  });

  it('drops null and undefined items without crashing', () => {
    const result = normalizeRecipeSuggestions({
      data: [null, undefined, { id: 5, name: 'Valida' }],
    });

    expect(result.invalidCount).toBe(2);
    expect(result.response.data).toHaveLength(1);
    expect(result.response.data[0].recipe.id).toBe(5);
  });

  it('drops recipe null and recipe without id', () => {
    const result = normalizeRecipeSuggestions({
      data: [{ recipe: null }, { recipe: { name: 'Sin id' } }, { id: 6, name: 'Valida' }],
    });

    expect(result.invalidCount).toBe(2);
    expect(result.response.data.map((item) => item.recipe.id)).toEqual([6]);
  });

  it('returns empty valid list for an empty response', () => {
    const result = normalizeRecipeSuggestions({ data: [] });

    expect(result.invalidCount).toBe(0);
    expect(result.response.data).toEqual([]);
    expect(result.response.meta.total).toBe(0);
  });

  it('preserves a stable recipe id key source', () => {
    const result = normalizeRecipeSuggestions({ data: [{ id: 7, name: 'Key estable' }] });

    expect(`recipe-${result.response.data[0].recipe.id}`).toBe('recipe-7');
  });
});
