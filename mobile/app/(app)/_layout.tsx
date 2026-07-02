import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import { COLORS } from '@/utils/theme';

export default function AppLayout() {
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
      <Tabs.Screen
        name="index"
        options={{
          title: 'Inicio',
          headerShown: false,
          tabBarIcon: ({ color, size }) => (
            <MaterialCommunityIcons name="home-outline" color={color} size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="catalog"
        options={{
          title: 'Catálogo',
          tabBarIcon: ({ color, size }) => (
            <MaterialCommunityIcons name="book-open-outline" color={color} size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="stock"
        options={{
          title: 'Stock',
          tabBarIcon: ({ color, size }) => (
            <MaterialCommunityIcons name="package-variant-closed" color={color} size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: 'Perfil',
          headerShown: false,
          tabBarIcon: ({ color, size }) => (
            <MaterialCommunityIcons name="account-circle-outline" color={color} size={size} />
          ),
        }}
      />

      {/* Stack screens — hidden from tab bar */}
      <Tabs.Screen
        name="groups/index"
        options={{
          href: null,
          headerShown: false,
          tabBarStyle: { display: 'none' },
          title: 'Grupos familiares',
        }}
      />
      <Tabs.Screen
        name="groups/[id]"
        options={{
          href: null,
          headerShown: false,
          tabBarStyle: { display: 'none' },
          title: 'Detalle del grupo',
        }}
      />
      <Tabs.Screen
        name="products/[id]"
        options={{
          href: null,
          headerShown: false,
          tabBarStyle: { display: 'none' },
          title: 'Producto',
        }}
      />
      <Tabs.Screen
        name="stock/create"
        options={{
          href: null,
          headerShown: false,
          tabBarStyle: { display: 'none' },
          title: 'Agregar al stock',
        }}
      />
      <Tabs.Screen
        name="stock/[id]"
        options={{
          href: null,
          headerShown: false,
          tabBarStyle: { display: 'none' },
          title: 'Detalle de stock',
        }}
      />
      <Tabs.Screen
        name="stock/[id]/edit"
        options={{
          href: null,
          headerShown: false,
          tabBarStyle: { display: 'none' },
          title: 'Editar stock',
        }}
      />

      {/* Shopping lists */}
      <Tabs.Screen
        name="shopping-lists/index"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Listas de compras' }}
      />
      <Tabs.Screen
        name="shopping-lists/create"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Nueva lista' }}
      />
      <Tabs.Screen
        name="shopping-lists/[id]"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Detalle de lista' }}
      />
      <Tabs.Screen
        name="shopping-lists/[id]/edit"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Editar lista' }}
      />

      {/* Shopping session */}
      <Tabs.Screen
        name="shopping-session/[listId]"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Sesión de compra' }}
      />

      {/* Purchases */}
      <Tabs.Screen
        name="purchases/index"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Historial de compras' }}
      />
      <Tabs.Screen
        name="purchases/[id]"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Detalle de compra' }}
      />

      {/* Budgets */}
      <Tabs.Screen
        name="budgets/index"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Presupuesto' }}
      />
      <Tabs.Screen
        name="budgets/[id]"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Detalle de presupuesto' }}
      />

      {/* Recipes and planning */}
      <Tabs.Screen
        name="recipes"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Recetas' }}
      />
      <Tabs.Screen
        name="recipes/[id]"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Detalle de receta' }}
      />
      <Tabs.Screen
        name="recipe-favorites"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Favoritos' }}
      />
      <Tabs.Screen
        name="recipe-suggestions"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Sugerencias' }}
      />
      <Tabs.Screen
        name="planning"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Planificación' }}
      />
      <Tabs.Screen
        name="meal-plans/index"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Meal plans' }}
      />
      <Tabs.Screen
        name="meal-plans/[id]"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Detalle de meal plan' }}
      />

      {/* Supermarkets, prices, notifications and reports */}
      <Tabs.Screen
        name="supermarkets"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Supermercados' }}
      />
      <Tabs.Screen
        name="supermarkets/[id]"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Detalle de supermercado' }}
      />
      <Tabs.Screen
        name="branches"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Sucursales' }}
      />
      <Tabs.Screen
        name="branches/[id]"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Detalle de sucursal' }}
      />
      <Tabs.Screen
        name="price-comparison"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Comparacion de precios' }}
      />
      <Tabs.Screen
        name="promotions"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Promociones' }}
      />
      <Tabs.Screen
        name="payment-methods"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Metodos de pago' }}
      />
      <Tabs.Screen
        name="notifications"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Notificaciones' }}
      />
      <Tabs.Screen
        name="reports"
        options={{ href: null, headerShown: false, tabBarStyle: { display: 'none' }, title: 'Reportes' }}
      />
    </Tabs>
  );
}
