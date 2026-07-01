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
      <Tabs.Screen
        name="groups/index"
        options={{
          href: null,
          title: 'Grupos familiares',
          tabBarStyle: { display: 'none' },
        }}
      />
      <Tabs.Screen
        name="groups/[id]"
        options={{
          href: null,
          title: 'Detalle del grupo',
          tabBarStyle: { display: 'none' },
        }}
      />
    </Tabs>
  );
}
