import React from 'react';
import {
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useRouter } from 'expo-router';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useProductDetail } from '@/hooks/useProductDetail';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { LoadingScreen } from '@/components/LoadingScreen';
import { ErrorState } from '@/components/ErrorState';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING } from '@/utils/theme';

interface ProductDetailScreenProps {
  productId: number;
}

function DetailRow({ label, value }: { label: string; value: string | null | undefined }) {
  if (!value) return null;
  return (
    <View style={styles.detailRow}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue}>{value}</Text>
    </View>
  );
}

export function ProductDetailScreen({ productId }: ProductDetailScreenProps) {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const { data, loading, error, refresh } = useProductDetail(productId);

  if (loading) return <LoadingScreen message="Cargando producto..." />;
  if (error) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Producto" showBack onBack={goBackOrHome} />
        <ErrorState
          message={friendlyMessage(error)}
          traceId={error.traceId}
          onRetry={refresh}
          type={error.status === 404 ? 'generic' : 'server'}
        />
      </View>
    );
  }
  if (!data) return null;

  return (
    <View style={styles.fill}>
      <AppHeader title={data.name} subtitle={data.brand?.name} showBack onBack={goBackOrHome} />
      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl refreshing={loading} onRefresh={refresh} tintColor={COLORS.primary} />
        }
        showsVerticalScrollIndicator={false}
      >
        {/* Icon header */}
        <View style={styles.hero}>
          <View style={styles.heroIcon}>
            <MaterialCommunityIcons name="package-variant-closed" size={48} color={COLORS.primary} />
          </View>
          <Text style={styles.heroName}>{data.name}</Text>
          {data.brand?.name ? (
            <Text style={styles.heroBrand}>{data.brand.name}</Text>
          ) : null}
          {data.barcode ? (
            <View style={styles.barcodePill}>
              <MaterialCommunityIcons name="barcode" size={14} color={COLORS.textSecondary} />
              <Text style={styles.barcodeText}>{data.barcode}</Text>
            </View>
          ) : null}
        </View>

        {/* Info card */}
        <View style={styles.card}>
          <DetailRow label="Categoría" value={data.category?.name} />
          <DetailRow label="Ingrediente principal" value={data.ingredient?.name} />
          <DetailRow
            label="Presentación"
            value={
              data.net_quantity != null && data.unit
                ? `${data.net_quantity} ${data.unit.symbol}`
                : null
            }
          />
          <DetailRow label="Unidad base" value={data.unit?.name} />
          <DetailRow label="Descripción" value={data.description} />
          <DetailRow
            label="Estado"
            value={data.status === 'active' ? 'Activo' : data.status === 'inactive' ? 'Inactivo' : data.status}
          />
        </View>

        {/* Add to stock action */}
        {selectedGroup ? (
          <AppButton
            title="Agregar al stock"
            onPress={() =>
              router.push({
                pathname: '/(app)/stock/create' as never,
                params: { product_id: String(data.id), product_name: data.name },
              })
            }
            fullWidth
            style={styles.addBtn}
          />
        ) : (
          <View style={styles.noGroupCard}>
            <MaterialCommunityIcons name="account-group-outline" size={20} color={COLORS.textSecondary} />
            <Text style={styles.noGroupText}>Seleccioná un grupo familiar para agregar al stock.</Text>
            <AppButton
              title="Seleccionar grupo"
              variant="outline"
              onPress={() => router.push('/(app)/groups' as never)}
            />
          </View>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  scroll: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  content: {
    padding: SPACING.md,
    paddingBottom: SPACING.xxl,
    gap: SPACING.md,
  },
  hero: {
    alignItems: 'center',
    paddingVertical: SPACING.lg,
    gap: SPACING.sm,
  },
  heroIcon: {
    width: 80,
    height: 80,
    borderRadius: RADIUS.xl,
    backgroundColor: COLORS.primarySurface,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: SPACING.xs,
  },
  heroName: {
    fontSize: FONT.titleSize,
    fontWeight: FONT.titleWeight,
    color: COLORS.textPrimary,
    textAlign: 'center',
  },
  heroBrand: {
    fontSize: FONT.bodySize,
    color: COLORS.textSecondary,
    textAlign: 'center',
  },
  barcodePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.xs,
    backgroundColor: COLORS.surfaceElevated,
    borderRadius: RADIUS.full,
    paddingHorizontal: SPACING.sm,
    paddingVertical: 4,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  barcodeText: {
    fontSize: FONT.captionSize,
    color: COLORS.textSecondary,
    fontFamily: 'monospace',
  },
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: SPACING.sm,
    ...SHADOW.sm,
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: SPACING.sm,
    paddingVertical: SPACING.xs,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.borderLight,
  },
  detailLabel: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.textSecondary,
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    flex: 1,
  },
  detailValue: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.textPrimary,
    flex: 2,
    textAlign: 'right',
  },
  addBtn: {
    marginTop: SPACING.sm,
  },
  noGroupCard: {
    backgroundColor: COLORS.surfaceElevated,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: SPACING.sm,
    alignItems: 'center',
  },
  noGroupText: {
    fontSize: FONT.captionSize + 1,
    color: COLORS.textSecondary,
    textAlign: 'center',
  },
});
