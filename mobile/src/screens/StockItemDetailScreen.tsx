import React, { useState } from 'react';
import {
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useRouter } from 'expo-router';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { LoadingScreen } from '@/components/LoadingScreen';
import { ErrorState } from '@/components/ErrorState';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { stockApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { useStock } from '@/hooks/useStock';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING } from '@/utils/theme';

interface StockItemDetailScreenProps {
  stockItemId: number;
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

export function StockItemDetailScreen({ stockItemId }: StockItemDetailScreenProps) {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { data, loading, error, refresh } = useStock(groupId);
  const [deleting, setDeleting] = useState(false);

  const item = data.find((i) => i.id === stockItemId) ?? null;

  if (loading && !item) return <LoadingScreen message="Cargando..." />;
  if (error && !item) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Detalle de stock" showBack onBack={goBackOrHome} />
        <ErrorState
          message={friendlyMessage(error)}
          traceId={error.traceId}
          onRetry={refresh}
          type="server"
        />
      </View>
    );
  }
  if (!item) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Detalle de stock" showBack onBack={goBackOrHome} />
        <ErrorState message="Item no encontrado." onRetry={refresh} type="generic" />
      </View>
    );
  }

  async function handleDelete() {
    Alert.alert(
      'Eliminar item',
      `¿Eliminás "${item?.product?.name ?? 'este item'}" del stock?`,
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Eliminar',
          style: 'destructive',
          onPress: async () => {
            if (!groupId || !item) return;
            setDeleting(true);
            try {
              await stockApi.delete(groupId, item.id);
              router.replace('/(app)/stock');
            } catch (err) {
              let msg = 'Error al eliminar.';
              if (err instanceof ApiError) {
                if (err.normalized.status === 403) msg = 'Sin permiso para eliminar.';
                else if (err.normalized.status === 404) msg = 'Item no encontrado.';
                else msg = err.normalized.message;
              }
              Alert.alert('Error', msg);
              setDeleting(false);
            }
          },
        },
      ],
    );
  }

  const expDate = item.expiration_date
    ? new Date(item.expiration_date).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' })
    : null;

  return (
    <View style={styles.fill}>
      <AppHeader
        title={item.product?.name ?? `Item #${item.id}`}
        subtitle={item.location?.name}
        showBack
        onBack={goBackOrHome}
      />
      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl refreshing={loading} onRefresh={refresh} tintColor={COLORS.primary} />
        }
        showsVerticalScrollIndicator={false}
      >
        {/* Hero */}
        <View style={styles.hero}>
          <View style={styles.heroIcon}>
            <MaterialCommunityIcons name="package-variant" size={40} color={COLORS.primary} />
          </View>
          <Text style={styles.heroName}>{item.product?.name ?? `Producto #${item.product_id}`}</Text>
          <Text style={styles.heroQty}>
            {item.quantity} {item.unit?.symbol ?? ''}
          </Text>
        </View>

        {/* Details */}
        <View style={styles.card}>
          <DetailRow label="Ubicación" value={item.location?.name} />
          <DetailRow label="Unidad" value={item.unit?.name} />
          <DetailRow label="Vencimiento" value={expDate} />
          <DetailRow
            label="Precio de compra"
            value={item.purchase_price != null ? `$${item.purchase_price}` : null}
          />
          <DetailRow
            label="Estado"
            value={item.status === 'active' ? 'Activo' : item.status === 'inactive' ? 'Inactivo' : item.status}
          />
        </View>

        {/* Actions */}
        <View style={styles.actions}>
          <AppButton
            title="Editar"
            variant="outline"
            onPress={() => router.push({ pathname: '/(app)/stock/[id]/edit' as never, params: { id: String(item.id) } })}
            fullWidth
          />
          <AppButton
            title="Eliminar"
            variant="danger"
            onPress={handleDelete}
            loading={deleting}
            fullWidth
          />
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  scroll: { flex: 1, backgroundColor: COLORS.background },
  content: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  hero: {
    alignItems: 'center',
    paddingVertical: SPACING.lg,
    gap: SPACING.sm,
  },
  heroIcon: {
    width: 72,
    height: 72,
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
  heroQty: {
    fontSize: FONT.subtitleSize,
    color: COLORS.primary,
    fontWeight: '700',
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
  actions: {
    gap: SPACING.sm,
    marginTop: SPACING.sm,
  },
});
