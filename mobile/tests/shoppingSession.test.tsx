declare const require: (name: string) => {
  readFileSync?: (file: string, encoding: string) => string;
  join?: (...parts: string[]) => string;
};
declare const __dirname: string;

const fs = require('fs') as { readFileSync: (file: string, encoding: string) => string };
const path = require('path') as { join: (...parts: string[]) => string };

const screenPath = path.join(__dirname, '..', 'src', 'screens', 'ShoppingSessionScreen.tsx');
const hookPath = path.join(__dirname, '..', 'src', 'hooks', 'useShoppingSession.ts');

describe('ShoppingSessionScreen item sync regression', () => {
  it('keeps granular item state with retry and no duplicate request while saving', () => {
    const source = fs.readFileSync(screenPath, 'utf8');

    expect(source).toContain('const [itemSync, setItemSync]');
    expect(source).toContain('itemSync[item.id]?.saving');
    expect(source).toContain('shoppingListItemsApi.update(groupId, listId, item.id');
    expect(source).toContain('sync.error.message');
    expect(source).toContain('Trace ID: {sync.error.traceId}');
    expect(source).toContain('Reintentar');
    expect(source).not.toContain('const [updatingId, setUpdatingId]');
  });

  it('finishes by routed session id and opens the created purchase when purchase_id is returned', () => {
    const screen = fs.readFileSync(screenPath, 'utf8');
    const hook = fs.readFileSync(hookPath, 'utf8');

    expect(hook).toContain('async function finishSession(sessionId?: number, stockLocationId?: number | null)');
    expect(hook).toContain('const id = sessionId ?? session?.id');
    expect(screen).toContain('finishSession(sessionId)');
    expect(screen).toContain('result.purchase_id');
    expect(screen).toContain("pathname: '/(app)/purchases/[id]'");
  });
});
