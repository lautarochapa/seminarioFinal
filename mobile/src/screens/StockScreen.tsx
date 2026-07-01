import React, { useCallback } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useStock } from '@/hooks/useStock';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { FamilyGroupSelector } from '@/components/FamilyGroupSelector';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { AppButton } from '@/components/AppButton';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { StockItem } from '@/types/stock';

function expirationLabel(date: string | null): { label: string; color: string } | null {
  if (!date) return null;
  const exp = new Date(date);
  const today = new Date();
  const diffDays = Math.ceil((exp.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
  if (diffDays < 0) return { label: 'Vencido', color: COLORS.error };
  if (diffDays <= 3) return { label: `Vence en ${diffDays}d`, color: COLORS.error };
  if (diffDays <= 7) return { label: `Vence en ${diffDays}d`, color: COLORS.warning };
  const d = exp.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' });
  return { label: `Vence ${d}`, color: COLORS.textHint };
}

function StockItemCard({ item, onPress }: { item: StockItem; onPress: () => void }) {
  const expInfo = expirationLabel(item.expiration_date);

  return (
    <Pressable
      style={({ pressed }) => [styles.card, pressed && styles.cardPressed]}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`Ver detalle de ${item.product?.name ?? 'item'}`}
    >
      <View style={styles.cardIcon}>
        <MaterialCommunityIcons name="package-variant" size={22} color={COLORS.primary} />
      </View>
      <View style={styles.cardBody}>
        <Text style={styles.cardName} numberOfLines={2}>
          {item.product?.name ?? `Producto #${item.product_id}`}
        </Text>
        <View style={styles.cardMeta}>
          <Text style={styles.cardQty}>
            {item.quantity} {item.unit?.symbol ?? ''}
          </Text>
          {item.location?.name ? (
            <Text style={styles.cardLocation} numberOfLines={1}>{item.location.name}</Text>
          ) : null}
        </View>
        {expInfo ? (
          <Text style={[styles.cardExp, { color: expInfo.color }]}>{expInfo.label}</Text>
        ) : null}
      </View>
      <MaterialCommunityIcons name="chevron-right" size={20} color={COLORS.textHint} />
    </Pressable>
  );
}

export function StockScreen() {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { data, meta, loading, loadingMore, error, refresh, loadMore } = useStock(groupId);

  const handleEndReached = useCallback(() => {
    loadMore();
  }, [loadMore]);

  const renderItem = useCallback(({ item }: { item: StockItem }) => (
    <StockItemCard
      item={item}
      onPress={() => router.push({ pathname: '/(app)/stock/[id]' as never, params: { id: String(item.id) } })}
    />
  ), [router]);

  const renderFooter = useCallback(() => {
    if (!loadingMore) return null;
    return (
      <View style={styles.footer}>
        <ActivityIndicator size="small" color={COLORS.primary} />
      </View>
    );
  }, [loadingMore]);

  return (
    <View style={styles.container}>
      <FamilyGroupSelector />

      {!selectedGroup ? (
        <EmptyState
          icon="account-group-outline"
          message="Seleccioná un grupo familiar para ver el stock."
          actionTitle="Ver grupos"
          onAction={() => router.push('/(app)/groups')}
        />
      ) : loading && data.length === 0 ? (
        <View style={styles.loadingWrap}>
          <ActivityIndicator size="large" color={COLORS.primary} />
        </View>
      ) : error ? (
        <ErrorState
          message={friendlyMessage(error)}
          traceId={error.traceId}
          onRetry={refresh}
          type="server"
        />
      ) : (
        <>
          {/* Header bar */}
          <View style={styles.barRow}>
            {meta ? (
              <Text style={styles.countText}>
                {meta.total} {meta.total === 1 ? 'item' : 'items'}
              </Text>
            ) : <View />}
            <AppButton
              title="Agregar"
              variant="outline"
              onPress={() => router.push('/(app)/stock/create')}
              style={styles.addBtn}
            />
          </View>

          <FlatList
            data={data}
            keyExtractor={(item) => String(item.id)}
            renderItem={renderItem}
            ListFooterComponent={renderFooter}
            ListEmptyComponent={
              <EmptyState
                icon="package-variant-closed"
                message="Sin items en el stock de este grupo."
                actionTitle="Agregar item"
                onAction={() => router.push('/(app)/stock/create')}
              />
            }
            onEndReached={handleEndReached}
            onEndReachedThreshold={0.3}
            refreshControl={
              <RefreshControl
                refreshing={loading && data.length > 0}
                onRefresh={refresh}
                tintColor={COLORS.primary}
              />
            }
            contentContainerStyle={[styles.list, data.length === 0 && styles.listEmpty]}
            showsVerticalScrollIndicator={false}
          />
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  loadingWrap: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  barRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
    backgroundColor: COLORS.surface,
  },
  countText: {
    fontSize: FONT.captionSize,
    color: COLORS.textSecondary,
  },
  addBtn: {
    paddingHorizontal: SPACING.md,
    height: 36,
  },
  list: {
    padding: SPACING.md,
    paddingBottom: SPACING.xxl,
  },
  listEmpty: {
    flexGrow: 1,
    justifyContent: 'center',
  },
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.md,
    marginBottom: SPACING.sm,
    ...SHADOW.sm,
  },
  cardPressed: {
    opacity: 0.85,
  },
  cardIcon: {
    width: 44,
    height: 44,
    borderRadius: RADIUS.md,
    backgroundColor: COLORS.primarySurface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardBody: {
    flex: 1,
    gap: 3,
  },
  cardName: {
    fontSize: FONT.bodySize,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  cardMeta: {
    flexDirection: 'row',
    gap: SPACING.sm,
    alignItems: 'center',
  },
  cardQty: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.primary,
    fontWeight: '600',
  },
  cardLocation: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.textSecondary,
  },
  cardExp: {
    fontSize: FONT_SIZE.xs,
    fontWeight: '500',
  },
  footer: {
    padding: SPACING.md,
    alignItems: 'center',
  },
});
