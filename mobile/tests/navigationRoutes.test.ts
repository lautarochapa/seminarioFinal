declare const require: (name: string) => {
  readFileSync?: (file: string, encoding: string) => string;
  join?: (...parts: string[]) => string;
};
declare const __dirname: string;

const fs = require('fs') as { readFileSync: (file: string, encoding: string) => string };
const path = require('path') as { join: (...parts: string[]) => string };

const APP_DIR = path.join(__dirname, '..', 'app', '(app)');
const LAYOUT = path.join(APP_DIR, '_layout.tsx');

function read(relativePath: string): string {
  return fs.readFileSync(path.join(APP_DIR, relativePath), 'utf8');
}

describe('Expo Router dynamic route registration', () => {
  it('registers shopping-lists/[id] without the physical index suffix', () => {
    const layout = fs.readFileSync(LAYOUT, 'utf8');

    expect(layout).toContain('name="shopping-lists/[id]"');
    expect(layout).not.toContain('name="shopping-lists/[id]/index"');
  });

  it('does not register dynamic index routes that Expo Router exposes as their parent path', () => {
    const layout = fs.readFileSync(LAYOUT, 'utf8');

    expect(layout).not.toMatch(/name="(?:shopping-lists|products|stock|groups)\/\[id\]\/index"/);
  });

  it('navigates list cards to the real shopping list detail route', () => {
    const screen = fs.readFileSync(path.join(__dirname, '..', 'src', 'screens', 'ShoppingListsScreen.tsx'), 'utf8');

    expect(screen).toContain("pathname: '/(app)/shopping-lists/[id]'");
    expect(screen).not.toContain('/(app)/shopping-lists/[id]/index');
  });

  it('passes the id param to ShoppingListDetailScreen', () => {
    const route = read(path.join('shopping-lists', '[id].tsx'));

    expect(route).toContain('useLocalSearchParams<{ id: string }>()');
    expect(route).toContain('<ShoppingListDetailScreen listId={parseInt(id, 10)} />');
  });

  it('keeps edit route registered separately', () => {
    const layout = fs.readFileSync(LAYOUT, 'utf8');

    expect(layout).toContain('name="shopping-lists/[id]/edit"');
  });
});
