import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import { COLORS } from '@/utils/theme';

export default function AppLayout() {
  const hidden = { href: null, headerShown: false, tabBarStyle: { display: 'none' as const } };

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: COLORS.primary,
        tabBarInactiveTintColor: COLORS.textHint,
        tabBarStyle: {
          backgroundColor: COLORS.surface,
          borderTopColor: COLORS.border,
          borderTopWidth: 1,
        },
        tabBarLabelStyle: { fontSize: 11, fontWeight: '500' },
        headerStyle: { backgroundColor: COLORS.surface },
        headerTitleStyle: { fontWeight: '700', color: COLORS.textPrimary },
        headerTintColor: COLORS.primary,
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Inicio', headerShown: false, tabBarIcon: ({ color, size }) => <MaterialCommunityIcons name="home-outline" color={color} size={size} /> }} />
      <Tabs.Screen name="stock" options={{ title: 'Mi cocina', tabBarIcon: ({ color, size }) => <MaterialCommunityIcons name="package-variant-closed" color={color} size={size} /> }} />
      <Tabs.Screen name="recipes" options={{ title: 'Recetas', headerShown: false, tabBarIcon: ({ color, size }) => <MaterialCommunityIcons name="silverware-fork-knife" color={color} size={size} /> }} />
      <Tabs.Screen name="planning" options={{ title: 'Plan', headerShown: false, tabBarIcon: ({ color, size }) => <MaterialCommunityIcons name="calendar-month-outline" color={color} size={size} /> }} />
      <Tabs.Screen name="shopping-lists/index" options={{ title: 'Compras', headerShown: false, tabBarIcon: ({ color, size }) => <MaterialCommunityIcons name="cart-outline" color={color} size={size} /> }} />

      <Tabs.Screen name="catalog" options={{ ...hidden, title: 'Catalogo' }} />
      <Tabs.Screen name="profile" options={{ ...hidden, title: 'Perfil' }} />
      <Tabs.Screen name="goals" options={{ ...hidden, title: 'Mis objetivos' }} />
      <Tabs.Screen name="dietary-preferences" options={{ ...hidden, title: 'Preferencias alimentarias' }} />
      <Tabs.Screen name="restrictions" options={{ ...hidden, title: 'Restricciones y alergias' }} />
      <Tabs.Screen name="groups/index" options={{ ...hidden, title: 'Grupos familiares' }} />
      <Tabs.Screen name="groups/[id]" options={{ ...hidden, title: 'Detalle del grupo' }} />
      <Tabs.Screen name="products/[id]" options={{ ...hidden, title: 'Producto' }} />
      <Tabs.Screen name="stock/create" options={{ ...hidden, title: 'Agregar al stock' }} />
      <Tabs.Screen name="stock/[id]" options={{ ...hidden, title: 'Detalle de stock' }} />
      <Tabs.Screen name="stock/[id]/edit" options={{ ...hidden, title: 'Editar stock' }} />
      <Tabs.Screen name="shopping-lists/create" options={{ ...hidden, title: 'Nueva lista' }} />
      <Tabs.Screen name="shopping-lists/[id]" options={{ ...hidden, title: 'Detalle de lista' }} />
      <Tabs.Screen name="shopping-lists/[id]/edit" options={{ ...hidden, title: 'Editar lista' }} />
      <Tabs.Screen name="shopping-session/[listId]" options={{ ...hidden, title: 'Sesion de compra' }} />
      <Tabs.Screen name="purchases/index" options={{ ...hidden, title: 'Historial de compras' }} />
      <Tabs.Screen name="purchases/[id]" options={{ ...hidden, title: 'Detalle de compra' }} />
      <Tabs.Screen name="budgets/index" options={{ ...hidden, title: 'Presupuesto' }} />
      <Tabs.Screen name="budgets/[id]" options={{ ...hidden, title: 'Detalle de presupuesto' }} />
      <Tabs.Screen name="recipes/[id]" options={{ ...hidden, title: 'Detalle de receta' }} />
      <Tabs.Screen name="recipe-favorites" options={{ ...hidden, title: 'Favoritos' }} />
      <Tabs.Screen name="recipe-suggestions" options={{ ...hidden, title: 'Sugerencias' }} />
      <Tabs.Screen name="meal-plans/index" options={{ ...hidden, title: 'Planes' }} />
      <Tabs.Screen name="meal-plans/[id]" options={{ ...hidden, title: 'Detalle de plan' }} />
      <Tabs.Screen name="supermarkets" options={{ ...hidden, title: 'Supermercados' }} />
      <Tabs.Screen name="supermarkets/[id]" options={{ ...hidden, title: 'Detalle de supermercado' }} />
      <Tabs.Screen name="branches" options={{ ...hidden, title: 'Sucursales' }} />
      <Tabs.Screen name="branches/[id]" options={{ ...hidden, title: 'Detalle de sucursal' }} />
      <Tabs.Screen name="price-comparison" options={{ ...hidden, title: 'Comparacion de precios' }} />
      <Tabs.Screen name="promotions" options={{ ...hidden, title: 'Promociones' }} />
      <Tabs.Screen name="payment-methods" options={{ ...hidden, title: 'Metodos de pago' }} />
      <Tabs.Screen name="notifications" options={{ ...hidden, title: 'Notificaciones' }} />
      <Tabs.Screen name="reports" options={{ ...hidden, title: 'Reportes' }} />
      <Tabs.Screen name="settings" options={{ ...hidden, title: 'Ajustes' }} />
      <Tabs.Screen name="barcode-scanner" options={{ ...hidden, title: 'Escanear codigo' }} />
    </Tabs>
  );
}
