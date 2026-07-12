import React, { useMemo, useState } from 'react';
import { FlatList, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { PriceCard } from '@/components/PriceCard';
import { PriceHistoryRow } from '@/components/PriceHistoryRow';
import { usePriceComparison } from '@/hooks/usePriceComparison';
import { usePriceHistory } from '@/hooks/usePriceHistory';
import { useProducts } from '@/hooks/useProducts';
import { friendlyMessage } from '@/utils/errorParser';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';
import type { ProductSummary } from '@/types/product';

export function PriceComparisonScreen() {
  const [search, setSearch] = useState('');
  const [selectedProduct, setSelectedProduct] = useState<ProductSummary | null>(null);
  const products = useProducts({ search, per_page: 10 });
  const comparison = usePriceComparison(selectedProduct?.id ?? null);
  const history = usePriceHistory(selectedProduct?.id ?? null);
  const sortedPrices = useMemo(() => {
    const prices = comparison.data?.prices ?? [];
    return [...prices].filter((item) => !item.current_price?.valid_to).sort((a, b) => Number(a.current_price?.price ?? 0) - Number(b.current_price?.price ?? 0));
  }, [comparison.data]);
  const bestId = comparison.data?.best?.id ?? sortedPrices[0]?.id;

  return (
    <View style={styles.fill}>
      <AppHeader title="Comparar precios" showBack onBack={goBackOrHome} />
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <View style={styles.search}>
          <MaterialCommunityIcons name="magnify" size={18} color={COLORS.textHint} />
          <TextInput style={styles.input} value={search} onChangeText={setSearch} placeholder="Buscar producto" placeholderTextColor={COLORS.textHint} />
        </View>
        {selectedProduct ? <Text style={styles.selected}>Producto: {selectedProduct.name}</Text> : null}
        {search.length > 1 && !selectedProduct ? (
          <FlatList
            data={products.data.slice(0, 6)}
            keyExtractor={(item) => String(item.id)}
            scrollEnabled={false}
            renderItem={({ item }) => (
              <Pressable style={styles.productRow} onPress={() => setSelectedProduct(item)}>
                <Text style={styles.productText}>{item.name}</Text>
              </Pressable>
            )}
          />
        ) : null}
        {!selectedProduct ? <EmptyState icon="cart-outline" message="Selecciona un producto para comparar precios vigentes." /> : null}
        {comparison.loading && selectedProduct ? <LoadingScreen message="Comparando precios..." /> : null}
        {comparison.error ? <ErrorState message={friendlyMessage(comparison.error)} traceId={comparison.error.traceId} onRetry={comparison.refresh} type="server" /> : null}
        {selectedProduct && !comparison.loading && sortedPrices.length === 0 ? <EmptyState icon="currency-usd-off" message="Este producto no tiene precios vigentes publicados." /> : null}
        {sortedPrices.map((item) => <PriceCard key={item.id} item={item} best={item.id === bestId} />)}
        {comparison.data?.partial_errors.length ? (
          <Text style={styles.warning}>Algunas fuentes fallaron. Los resultados visibles siguen disponibles.</Text>
        ) : null}

        {selectedProduct ? (
          <View style={styles.historySection}>
            <Text style={styles.historyTitle}>Historial de precios</Text>
            {history.loading ? <LoadingScreen message="Cargando historial..." /> : null}
            {history.error ? <ErrorState message={friendlyMessage(history.error)} traceId={history.error.traceId} onRetry={history.refresh} type="server" /> : null}
            {!history.loading && history.data.length === 0 ? <EmptyState icon="chart-box-outline" message="Sin historial de precios para este producto." /> : null}
            {history.data.map((item) => <PriceHistoryRow key={item.id} item={item} />)}
          </View>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  content: { padding: SPACING.md, gap: SPACING.md },
  search: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border, borderRadius: RADIUS.md, paddingHorizontal: SPACING.sm },
  input: { flex: 1, minHeight: 44, color: COLORS.textPrimary },
  selected: { fontSize: FONT.bodySize, fontWeight: '700', color: COLORS.textPrimary },
  historySection: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, gap: SPACING.xs },
  historyTitle: { fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary, marginBottom: SPACING.xs },
  productRow: { backgroundColor: COLORS.surface, padding: SPACING.md, borderBottomWidth: 1, borderBottomColor: COLORS.borderLight },
  productText: { color: COLORS.textPrimary, fontSize: FONT.bodySize },
  warning: { color: COLORS.warning, fontSize: FONT.captionSize, fontWeight: '700' },
});
