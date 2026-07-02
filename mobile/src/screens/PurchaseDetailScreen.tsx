import React from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { AppHeader } from '@/components/AppHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { MoneyText } from '@/components/MoneyText';
import { LoadingScreen } from '@/components/LoadingScreen';
import { ErrorState } from '@/components/ErrorState';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { usePurchaseDetail } from '@/hooks/usePurchaseDetail';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING } from '@/utils/theme';

interface Props {
  purchaseId: number;
}

export function PurchaseDetailScreen({ purchaseId }: Props) {
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { data: purchase, loading, error, refresh } = usePurchaseDetail(groupId, purchaseId);

  if (loading) return <LoadingScreen message="Cargando compra..." />;

  if (error) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Compra" showBack onBack={goBackOrHome} />
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId ?? ''} onRetry={refresh} type="server" />
      </View>
    );
  }

  if (!purchase) return null;

  const date = purchase.purchase_date
    ? new Date(purchase.purchase_date).toLocaleDateString('es-AR')
    : null;

  return (
    <View style={styles.fill}>
      <AppHeader title={`Compra #${purchase.id}`} subtitle={selectedGroup?.name} showBack onBack={goBackOrHome} />
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.card}>
          <View style={styles.row}>
            <StatusBadge status={purchase.status} />
            {date ? <Text style={styles.meta}>{date}</Text> : null}
          </View>
          <View style={styles.row}>
            <Text style={styles.label}>Total estimado</Text>
            <MoneyText amount={purchase.estimated_total} style={styles.value} />
          </View>
          <View style={styles.row}>
            <Text style={styles.label}>Total real</Text>
            <MoneyText amount={purchase.actual_total} style={styles.value} />
          </View>
        </View>

        {purchase.items && purchase.items.length > 0 && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Items</Text>
            {purchase.items.map((item) => (
              <View key={item.id} style={styles.itemCard}>
                <View style={styles.itemBody}>
                  <Text style={styles.itemName} numberOfLines={2}>{item.product_name ?? `Producto #${item.product_id}`}</Text>
                  <Text style={styles.itemMeta}>{item.quantity} {item.unit_name ?? ''}</Text>
                </View>
                <MoneyText amount={item.total_price} style={styles.itemPrice} />
              </View>
            ))}
          </View>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  scroll: { flex: 1 },
  content: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, gap: SPACING.sm, ...SHADOW.sm },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  label: { fontSize: FONT.labelSize, color: COLORS.textSecondary },
  value: { fontSize: FONT.bodySize, fontWeight: '600', color: COLORS.textPrimary },
  meta: { fontSize: FONT_SIZE.xs, color: COLORS.textHint },
  section: { gap: SPACING.sm },
  sectionTitle: { fontSize: FONT.subtitleSize, fontWeight: FONT.subtitleWeight, color: COLORS.textPrimary },
  itemCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.sm,
    padding: SPACING.md,
    gap: SPACING.sm,
    ...SHADOW.sm,
  },
  itemBody: { flex: 1, gap: 2 },
  itemName: { fontSize: FONT.bodySize, fontWeight: '500', color: COLORS.textPrimary },
  itemMeta: { fontSize: FONT_SIZE.xs, color: COLORS.textSecondary },
  itemPrice: { fontSize: FONT.bodySize, fontWeight: '700', color: COLORS.textPrimary },
});
