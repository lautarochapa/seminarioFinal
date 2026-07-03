declare const require: (name: string) => {
  readFileSync?: (file: string, encoding: string) => string;
  existsSync?: (file: string) => boolean;
  join?: (...parts: string[]) => string;
};
declare const __dirname: string;

const fs = require('fs') as { readFileSync: (file: string, encoding: string) => string; existsSync: (file: string) => boolean };
const path = require('path') as { join: (...parts: string[]) => string };
const root = path.join(__dirname, '..');

function read(rel: string): string {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

describe('recipes, planning and meal plans mobile integration', () => {
  it('adds Expo Router files and avoids dynamic /index route names', () => {
    expect(fs.existsSync(path.join(root, 'app', '(app)', 'recipes.tsx'))).toBe(true);
    expect(fs.existsSync(path.join(root, 'app', '(app)', 'recipes', '[id].tsx'))).toBe(true);
    expect(fs.existsSync(path.join(root, 'app', '(app)', 'planning.tsx'))).toBe(true);
    expect(fs.existsSync(path.join(root, 'app', '(app)', 'meal-plans', '[id].tsx'))).toBe(true);

    const layout = read(path.join('app', '(app)', '_layout.tsx'));
    expect(layout).toContain('name="recipes/[id]"');
    expect(layout).toContain('name="meal-plans/[id]"');
    expect(layout).not.toMatch(/name=".*\/\[id\]\/index"/);
  });

  it('uses real backend endpoints through API modules', () => {
    const endpoints = read(path.join('src', 'api', 'endpoints.ts'));

    expect(endpoints).toContain('/api/v1/recipes');
    expect(endpoints).toContain('/api/v1/recipes/search');
    expect(endpoints).toContain('/api/v1/users/me/favorite-recipes');
    expect(endpoints).toContain('/api/v1/recipes/${recipeId}/favorite');
    expect(endpoints).toContain('/api/v1/recipes/suggestions');
    expect(endpoints).toContain('/api/v1/family-groups/${groupId}/meal-plans');
    expect(endpoints).toContain('/generate-shopping-list');
  });

  it('generates a shopping list directly from a recipe via the real endpoint', () => {
    const endpoints = read(path.join('src', 'api', 'endpoints.ts'));
    expect(endpoints).toContain('/api/v1/family-groups/${groupId}/recipes/${recipeId}/shopping-list');
    expect(endpoints).toContain('recipeShoppingListApi');

    const detail = read(path.join('src', 'screens', 'RecipeDetailScreen.tsx'));
    expect(detail).toContain('recipeShoppingListApi.generate');
    expect(detail).not.toContain('No hay endpoint directo para generar lista desde receta');
  });
});
