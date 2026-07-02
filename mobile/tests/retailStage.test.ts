declare const require: (moduleName: string) => {
  readFileSync?: (file: string, encoding: string) => string;
  existsSync?: (file: string) => boolean;
  join?: (...parts: string[]) => string;
  resolve?: (...parts: string[]) => string;
};
declare const __dirname: string;

const fs = require('fs') as {
  readFileSync: (file: string, encoding: string) => string;
  existsSync: (file: string) => boolean;
};
const path = require('path') as {
  join: (...parts: string[]) => string;
  resolve: (...parts: string[]) => string;
};

const root = path.resolve(__dirname, '..');

function read(file: string): string {
  return fs.readFileSync(path.join(root, file), 'utf8');
}

describe('retail mobile stage', () => {
  it('registers stack route files without dynamic index routes', () => {
    [
      'app/(app)/supermarkets.tsx',
      'app/(app)/supermarkets/[id].tsx',
      'app/(app)/branches.tsx',
      'app/(app)/branches/[id].tsx',
      'app/(app)/price-comparison.tsx',
      'app/(app)/promotions.tsx',
      'app/(app)/payment-methods.tsx',
      'app/(app)/notifications.tsx',
      'app/(app)/reports.tsx',
    ].forEach((file) => expect(fs.existsSync(path.join(root, file))).toBe(true));
    const layout = read('app/(app)/_layout.tsx');
    expect(layout).toContain('name="branches/[id]"');
    expect(layout).toContain('name="supermarkets/[id]"');
    expect(layout).not.toContain('branches/[id]/index');
    expect(layout).not.toContain('supermarkets/[id]/index');
  });

  it('exposes retail api modules and hooks', () => {
    const endpoints = read('src/api/endpoints.ts');
    [
      'supermarketsApi',
      'branchesApi',
      'pricesApi',
      'promotionsApi',
      'paymentMethodsApi',
      'notificationsApi',
      'reportsApi',
    ].forEach((name) => expect(endpoints).toContain(`export const ${name}`));

    [
      'useSupermarkets',
      'useBranches',
      'useBranchDetail',
      'useNearbyBranches',
      'usePriceComparison',
      'usePriceHistory',
      'usePromotions',
      'usePaymentMethods',
      'useNotifications',
      'useNotificationPreferences',
      'useReports',
    ].forEach((name) => expect(fs.existsSync(path.join(root, `src/hooks/${name}.ts`))).toBe(true));
  });

  it('keeps explicit data origin and location handling in UI code', () => {
    expect(read('src/components/DataOriginBadge.tsx')).toContain('Origen:');
    expect(read('src/screens/BranchesScreen.tsx')).toContain('La ubicacion se solicita solo al tocar el boton.');
    expect(read('src/hooks/useNearbyBranches.ts')).toContain('LOCATION_PERMISSION_DENIED');
    expect(read('src/hooks/useNearbyBranches.ts')).toContain('LOCATION_TIMEOUT');
  });

  it('documents unsupported public price history endpoint as blocked', () => {
    const endpoints = read('src/api/endpoints.ts');
    expect(endpoints).toContain('No hay endpoint publico de historial de precios.');
  });
});
