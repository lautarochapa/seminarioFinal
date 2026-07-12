import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useAuth } from '@/auth/AuthContext';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { AppLogo } from '@/components/AppLogo';
import { UserAccountMenu } from '@/components/UserAccountMenu';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { homeApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING } from '@/utils/theme';

type HomeSummary = Awaited<ReturnType<typeof homeApi.summary>>['data'];

export function HomeScreen() {
  const { user, logout } = useAuth();
  const { selectedGroup } = useFamilyGroupContext();
  const selectedGroupId = selectedGroup?.id ?? null;
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [summary, setSummary] = useState<HomeSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<ApiError | null>(null);
  const [accountMenuOpen, setAccountMenuOpen] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await homeApi.summary(selectedGroupId);
      setSummary(response.data);
    } catch (err) {
      setError(err instanceof ApiError ? err : null);
    } finally {
      setLoading(false);
    }
  }, [selectedGroupId]);

  useEffect(() => {
    const timer = setTimeout(() => { void load(); }, 0);
    return () => clearTimeout(timer);
  }, [load]);

  const displayName = user ? (user.name || user.lastname ? `${user.name} ${user.lastname}`.trim() : user.email) : '';
  const initials = user ? `${(user.name || '?').charAt(0)}${(user.lastname || '').charAt(0)}`.toUpperCase() : '?';
  const stock = summary?.stock ?? { products: 0, low_stock: 0, expiring: 0, expired: 0 };
  const recipes = summary?.recipes ?? { available: 0 };

  function confirmLogout() {
    Alert.alert('Cerrar sesión', '¿Querés cerrar sesión?', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Cerrar sesión', style: 'destructive', onPress: () => { void logout(); } },
    ]);
  }

  return (
    <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={[styles.header, { paddingTop: insets.top + SPACING.sm }]}>
        <View style={styles.headerTop}>
          <AppLogo variant="small" inverted />
          <Pressable style={({ pressed }) => [styles.avatar, pressed && styles.avatarPressed]} onPress={() => setAccountMenuOpen(true)} accessibilityRole="button" accessibilityLabel="Abrir menú de usuario" accessibilityHint="Muestra perfil, grupo, planes, listas y configuración">
            <Text style={styles.avatarText}>{initials}</Text>
          </Pressable>
        </View>
        <Text style={styles.greeting}>Hola, <Text style={styles.greetingName}>{displayName}</Text></Text>
        {selectedGroup ? <Text style={styles.groupName}>{selectedGroup.name}</Text> : null}
      </View>

      <View style={styles.body}>
        <Text style={styles.title}>Que resolvemos hoy?</Text>
        <View style={styles.quickGrid}>
          <QuickAction icon="plus-circle-outline" label="Agregar producto" onPress={() => router.push('/(app)/stock/create' as never)} />
          <QuickAction icon="barcode-scan" label="Escanear codigo" onPress={() => router.push('/(app)/barcode-scanner' as never)} />
          <QuickAction icon="silverware-fork-knife" label="Qué puedo cocinar hoy" onPress={() => router.push('/(app)/recipe-suggestions' as never)} />
          <QuickAction icon="cart-plus" label="Lista de compras" onPress={() => router.push('/(app)/shopping-lists/create' as never)} />
        </View>

        {loading ? <ActivityIndicator color={COLORS.primary} style={styles.loader} /> : null}
        {error ? <ErrorState message="No pudimos cargar tu inicio. Revisa tu conexion e intenta nuevamente." traceId={error.normalized.traceId} onRetry={load} type={error.normalized.isNetworkError ? 'network' : 'server'} /> : null}

        {!loading && !error && stock.products === 0 ? (
          <EmptyState icon="package-variant" message="Todavia no cargaste productos. Agrega lo que tenes en tu cocina para que podamos recomendarte recetas." />
        ) : null}

        {!loading && !error ? (
          <View style={styles.summaryGrid}>
            <SummaryCard value={stock.products} label="productos en tu cocina" onPress={() => router.push('/(app)/stock' as never)} />
            <SummaryCard value={stock.expiring} label="por vencer" onPress={() => router.push({ pathname: '/(app)/stock' as never, params: { filter: 'expiring' } })} />
            <SummaryCard value={stock.low_stock} label="con poco stock" onPress={() => router.push({ pathname: '/(app)/stock' as never, params: { filter: 'low_stock' } })} />
            <SummaryCard value={recipes.available} label="recetas posibles" onPress={() => router.push('/(app)/recipe-suggestions' as never)} />
          </View>
        ) : null}

        {!loading && !error ? (
          <View style={styles.panel}>
            <Text style={styles.panelTitle}>Para resolver</Text>
            {summary?.actions.length ? summary.actions.map((action) => <Text key={action.type} style={styles.todo}>- {action.message}</Text>) : <Text style={styles.muted}>No tenes pendientes importantes por ahora.</Text>}
          </View>
        ) : null}
      </View>
      <UserAccountMenu visible={accountMenuOpen} user={user} group={selectedGroup} onClose={() => setAccountMenuOpen(false)} onNavigate={(route) => router.push(route as never)} onLogout={confirmLogout} />
    </ScrollView>
  );
}

function QuickAction({ icon, label, onPress }: { icon: React.ComponentProps<typeof MaterialCommunityIcons>['name']; label: string; onPress: () => void }) {
  return (
    <Pressable style={styles.quickAction} onPress={onPress} accessibilityRole="button">
      <MaterialCommunityIcons name={icon} size={24} color={COLORS.primary} />
      <Text style={styles.quickLabel}>{label}</Text>
    </Pressable>
  );
}

function SummaryCard({ value, label, onPress }: { value: number; label: string; onPress: () => void }) {
  return (
    <Pressable style={({ pressed }) => [styles.summaryCard, pressed && styles.cardPressed]} onPress={onPress} accessibilityRole="button" accessibilityLabel={`${value} ${label}`}>
      <Text style={styles.summaryValue}>{value}</Text>
      <Text style={styles.summaryLabel}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  scroll: { flex: 1, backgroundColor: COLORS.background },
  content: { flexGrow: 1, paddingBottom: SPACING.xxl },
  header: { backgroundColor: COLORS.dark, paddingBottom: SPACING.md, paddingHorizontal: SPACING.lg, borderBottomLeftRadius: RADIUS.xl, borderBottomRightRadius: RADIUS.xl, gap: SPACING.sm, marginBottom: SPACING.md },
  headerTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: SPACING.sm },
  avatar: { width: 40, height: 40, borderRadius: RADIUS.full, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  avatarPressed: { opacity: 0.7, transform: [{ scale: 0.96 }] },
  avatarText: { fontSize: FONT.labelSize, fontWeight: '700', color: '#fff' },
  greeting: { fontSize: FONT.titleSize, fontWeight: '400', color: 'rgba(255,255,255,0.8)' },
  greetingName: { fontWeight: FONT.titleWeight, color: '#fff' },
  groupName: { color: COLORS.primaryLight, fontWeight: '700' },
  body: { paddingHorizontal: SPACING.md, gap: SPACING.md },
  title: { fontSize: FONT.subtitleSize, fontWeight: FONT.subtitleWeight, color: COLORS.textPrimary },
  quickGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: SPACING.sm },
  quickAction: { width: '48%', minHeight: 82, backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, gap: SPACING.xs, ...SHADOW.sm },
  quickLabel: { color: COLORS.textPrimary, fontWeight: '700', fontSize: FONT_SIZE.sm },
  loader: { marginVertical: SPACING.lg },
  summaryGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: SPACING.sm },
  summaryCard: { width: '48%', backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, ...SHADOW.sm },
  cardPressed: { opacity: 0.72 },
  summaryValue: { fontSize: 26, fontWeight: '800', color: COLORS.textPrimary },
  summaryLabel: { fontSize: FONT_SIZE.xs, color: COLORS.textSecondary },
  panel: { backgroundColor: COLORS.surface, borderRadius: RADIUS.sm, padding: SPACING.md, gap: SPACING.xs, ...SHADOW.sm },
  panelTitle: { fontSize: FONT.bodySize, fontWeight: '800', color: COLORS.textPrimary },
  todo: { color: COLORS.textPrimary, fontSize: FONT_SIZE.sm },
  muted: { color: COLORS.textSecondary, fontSize: FONT_SIZE.sm },
});
