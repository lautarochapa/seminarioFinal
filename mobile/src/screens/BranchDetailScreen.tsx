import React from 'react';
import { Linking, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import { AppButton } from '@/components/AppButton';
import { AppHeader } from '@/components/AppHeader';
import { DataOriginBadge } from '@/components/DataOriginBadge';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { PriceCard } from '@/components/PriceCard';
import { PromotionCard } from '@/components/PromotionCard';
import { ServiceBadge } from '@/components/ServiceBadge';
import { useBranchDetail } from '@/hooks/useBranchDetail';
import { usePaginatedRetailList } from '@/hooks/useRetailList';
import { usePromotions } from '@/hooks/usePromotions';
import { branchesApi } from '@/api/endpoints';
import { friendlyMessage } from '@/utils/errorParser';
import { goBackOrHome } from '@/utils/navigation';
import { mapsUrl } from '@/utils/retail';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';
import type { SupermarketProduct } from '@/types/retail';

export function BranchDetailScreen() {
  const params = useLocalSearchParams<{ id?: string }>();
  const branchId = Number(params.id);
  const { data: branch, loading, error, refresh } = useBranchDetail(Number.isFinite(branchId) ? branchId : null);
  const products = usePaginatedRetailList<SupermarketProduct, { page?: number }>(
    (filters) => branchesApi.products(branchId, filters),
    { page: 1 },
  );
  const promotions = usePromotions(Number.isFinite(branchId) ? branchId : null);

  if (loading) {
    return <View style={styles.fill}><AppHeader title="Sucursal" showBack onBack={goBackOrHome} /><LoadingScreen message="Cargando sucursal..." /></View>;
  }
  if (error || !branch) {
    return <View style={styles.fill}><AppHeader title="Sucursal" showBack onBack={goBackOrHome} /><ErrorState message={error ? friendlyMessage(error) : 'Sucursal no encontrada.'} traceId={error?.traceId} onRetry={refresh} type="server" /></View>;
  }

  return (
    <View style={styles.fill}>
      <AppHeader title={branch.name} subtitle={branch.chain?.name || undefined} showBack onBack={goBackOrHome} />
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.card}>
          <Text style={styles.title}>{branch.name}</Text>
          <Text style={styles.meta}>{branch.chain?.name || 'Cadena sin informar'} - {branch.city?.name || 'Ciudad sin informar'}</Text>
          <Text style={styles.meta}>{branch.address || 'Sin direccion'}</Text>
          <Text style={styles.meta}>Horario: {branch.opening_hours || 'Sin informar'}</Text>
          <View style={styles.badges}>
            <ServiceBadge label="Delivery" enabled={branch.delivery_available} />
            <ServiceBadge label="Pickup" enabled={branch.pickup_available} />
          </View>
          <Text style={styles.meta}>Coordenadas: {branch.latitude ?? 's/d'}, {branch.longitude ?? 's/d'}</Text>
          <AppButton title="Como llegar" onPress={() => Linking.openURL(mapsUrl(branch))} fullWidth />
        </View>

        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Promociones</Text>
        </View>
        {promotions.data.length === 0 ? <EmptyState icon="ticket-percent-outline" message="No hay promociones vigentes para esta sucursal." /> : promotions.data.map((item) => <PromotionCard key={item.id} item={item} />)}

        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Productos y precios</Text>
          <Pressable onPress={() => router.push({ pathname: '/(app)/price-comparison' as never })}><Text style={styles.link}>Comparar</Text></Pressable>
        </View>
        {products.error ? <ErrorState message={friendlyMessage(products.error)} traceId={products.error.traceId} onRetry={products.refresh} type="server" /> : null}
        {products.data.length === 0 && !products.loading ? <EmptyState icon="package-variant" message="No hay productos publicados para esta sucursal." /> : null}
        {products.data.map((item) => <PriceCard key={item.id} item={item} />)}
        <DataOriginBadge origin="demo" />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  content: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, gap: SPACING.sm },
  title: { fontSize: FONT.titleSize, fontWeight: FONT.titleWeight, color: COLORS.textPrimary },
  meta: { fontSize: FONT.captionSize + 1, color: COLORS.textSecondary },
  badges: { flexDirection: 'row', gap: SPACING.xs, flexWrap: 'wrap' },
  sectionHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  sectionTitle: { fontSize: FONT.subtitleSize, fontWeight: '800', color: COLORS.textPrimary },
  link: { fontSize: FONT.captionSize, color: COLORS.primary, fontWeight: '800' },
});
