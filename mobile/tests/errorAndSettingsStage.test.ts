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

describe('global error handling and settings stage', () => {
  it('registers a not-found route and an ErrorBoundary wrapping the app', () => {
    expect(fs.existsSync(path.join(root, 'app', '+not-found.tsx'))).toBe(true);
    expect(fs.existsSync(path.join(root, 'src', 'components', 'ErrorBoundary.tsx'))).toBe(true);

    const rootLayout = read(path.join('app', '_layout.tsx'));
    expect(rootLayout).toContain('<ErrorBoundary');
    expect(rootLayout).toContain('<Slot');
  });

  it('registers the settings route and screen', () => {
    expect(fs.existsSync(path.join(root, 'app', '(app)', 'settings.tsx'))).toBe(true);
    expect(fs.existsSync(path.join(root, 'src', 'screens', 'SettingsScreen.tsx'))).toBe(true);

    const layout = read(path.join('app', '(app)', '_layout.tsx'));
    expect(layout).toContain('name="settings"');
  });

  it('offers logout and version info from the settings screen', () => {
    const settings = read(path.join('src', 'screens', 'SettingsScreen.tsx'));
    expect(settings).toContain('Cerrar sesión');
    expect(settings).toContain('Constants.expoConfig?.version');
  });

  it('clears the active family group on logout', () => {
    const rootLayout = read(path.join('app', '_layout.tsx'));
    expect(rootLayout).toContain('clearGroup()');
  });
});
