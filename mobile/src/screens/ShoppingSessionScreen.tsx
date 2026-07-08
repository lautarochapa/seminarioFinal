import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { StatusBadge } from '@/components/StatusBadge';
import { MoneyText } from '@/components/MoneyText';
import { LoadingScreen } from '@/components/LoadingScreen';
import { ErrorState } from '@/components/ErrorState';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useShoppingListDetail } from '@/hooks/useShoppingListDetail';
import { useShoppingSession } from '@/hooks/useShoppingSession';
import { shoppingListItemsApi, budgetsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { goBackOrHome } from '@/utils/navigation';
import { formatMoney } from '@/utils/retail';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING, TOUCH_TARGET } from '@/utils/theme';
import type { NormalizedError } from '@/types/api';
import type { ShoppingListItem } from '@/types/shopping';

interface Props {
  listId: number;
  sessionId: number;
}

export function ShoppingSessionScreen({ listId, sessionId }: Props) {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { list, items, loading, error, refresh } = useShoppingListDetail(groupId, listId);
  const { finishing, error: sessionError, finishSession, finishSummary } = useShoppingSession(groupId);

  const [itemSync, setItemSync] = useState<Record<number, {
    saving?: boolean;
    saved?: boolean;
    error?: NormalizedError;
  }>>({});
  const [priceDrafts, setPriceDrafts] = useState<Record<number, string>>({});

  useEffect(() => {
    if (!list) return;
    if (list.status === 'completed' || list.status === 'cancelled') {
      Alert.alert('Lista finalizada', 'Esta lista ya está cerrada.');
    }
  }, [list]);

  async function handleToggle(item: ShoppingListItem) {
    if (!groupId) return;
    if (itemSync[item.id]?.saving) return;

    const newStatus = item.status === 'purchased' ? 'pending' : 'purchased';
    setItemSync((current) => ({
      ...current,
      [item.id]: { saving: true, saved: false, error: undefined },
    }));

    try {
      await shoppingListItemsApi.update(groupId, listId, item.id, { status: newStatus });
      await refresh();
      setItemSync((current) => ({
        ...current,
        [item.id]: { saving: false, saved: true, error: undefined },
      }));
      setTimeout(() => {
        setItemSync((current) => {
          const next = { ...current };
          if (next[item.id]?.saved) delete next[item.id];
          return next;
        });
      }, 1800);
    } catch (err: unknown) {
      const fallback: NormalizedError = {
        status: 0,
        code: 'NETWORK_ERROR',
        message: 'No se pudo guardar este item.',
        fieldErrors: {},
        traceId: '',
        isNetworkError: true,
        isTimeoutError: false,
      };
      setItemSync((current) => ({
        ...current,
        [item.id]: {
          saving: false,
          saved: false,
          error: err instanceof ApiError ? err.normalized : fallback,
        },
      }));
    }
  }

  async function handleSavePrice(item: ShoppingListItem) {
    if (!groupId) return;
    const draft = priceDrafts[item.id];
    if (draft === undefined) return;
    const parsed = draft.trim() === '' ? null : Number(draft);
    if (parsed !== null && (isNaN(parsed) || parsed < 0)) return;

    setItemSync((current) => ({ ...current, [item.id]: { saving: true, saved: false, error: undefined } }));
    try {
      await shoppingListItemsApi.update(groupId, listId, item.id, { actual_price: parsed });
      await refresh();
      setItemSync((current) => ({ ...current, [item.id]: { saving: false, saved: true, error: undefined } }));
      setTimeout(() => {
        setItemSync((current) => {
          const next = { ...current };
          if (next[item.id]?.saved) delete next[item.id];
          return next;
        });
      }, 1800);
    } catch (err: unknown) {
      const fallback: NormalizedError = {
        status: 0, code: 'NETWORK_ERROR', message: 'No se pudo guardar el precio.',
        fieldErrors: {}, traceId: '', isNetworkError: true, isTimeoutError: false,
      };
      setItemSync((current) => ({
        ...current,
        [item.id]: { saving: false, saved: false, error: err instanceof ApiError ? err.normalized : fallback },
      }));
    }
  }

  function finishSummaryMessage(summary: NonNullable<ReturnType<typeof useShoppingSession>['finishSummary']>): string {
    const parts = [`${summary.stock_created_count} producto(s) agregados al stock`];
    if (summary.stock_updated_count > 0) parts.push(`${summary.stock_updated_count} actualizados en stock`);
    if (summary.stock_skipped_count > 0) parts.push(`${summary.stock_skipped_count} no se pudieron agregar`);
    return parts.join('\n');
  }

  async function budgetImpactMessage(): Promise<string | null> {
    if (!groupId) return null;
    try {
      const res = await budgetsApi.current(groupId);
      const budget = res.data;
      if (!budget || budget.used_amount === undefined || budget.available_amount === undefined) return null;
      return `Presupuesto actualizado\nGastado este mes: ${formatMoney(budget.used_amount)}\nDisponible: ${formatMoney(budget.available_amount)}`;
    } catch {
      // El presupuesto es informativo; si falla la consulta no debe bloquear el cierre de la compra.
      return null;
    }
  }

  async function handleFinish() {
    Alert.alert(
      'Finalizar compra',
      `¿Terminar la sesión? Quedan ${items.filter((i) => i.status === 'pending').length} items pendientes.`,
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Finalizar',
          onPress: async () => {
            const result = await finishSession(sessionId);
            if (result) {
              const budgetLine = await budgetImpactMessage();
              const parts = [finishSummary ? finishSummaryMessage(finishSummary) : 'Sesión finalizada.'];
              if (budgetLine) parts.push(budgetLine);
              Alert.alert(
                'Compra finalizada',
                parts.join('\n\n'),
                [{
                  text: 'OK',
                  onPress: () => {
                    if (result.purchase_id) {
                      router.replace({ pathname: '/(app)/purchases/[id]' as never, params: { id: String(result.purchase_id) } });
                    } else {
                      goBackOrHome();
                    }
                  },
                }],
              );
            } else if (sessionError) {
              Alert.alert('Error', sessionError.message ?? 'No se pudo finalizar.');
            }
          },
        },
      ],
    );
  }

  if (loading) return <LoadingScreen message="Cargando lista..." />;

  if (error) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Compra en curso" showBack onBack={goBackOrHome} />
        <ErrorState message={error.message} onRetry={refresh} type="server" />
      </View>
    );
  }

  const pendingItems = items.filter((i) => i.status === 'pending');
  const purchasedItems = items.filter((i) => i.status === 'purchased');
  const runningTotal = purchasedItems.reduce((acc, i) => acc + (i.actual_price ?? i.estimated_price ?? 0), 0);

  return (
    <View style={styles.fill}>
      <AppHeader
        title="Compra en curso"
        subtitle={`${purchasedItems.length}/${items.length} comprados`}
        showBack
        onBack={goBackOrHome}
      />
      <View style={styles.totalBar}>
        <Text style={styles.totalLabel}>Total parcial:</Text>
        <MoneyText amount={runningTotal} style={styles.totalValue} />
      </View>

      <FlatList
        data={[...pendingItems, ...purchasedItems]}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => {
          const sync = itemSync[item.id];
          const priceDraft = priceDrafts[item.id] ?? (item.actual_price !== null ? String(item.actual_price) : '');
          return (
            <View style={[styles.itemCard, sync?.error && styles.itemCardError]}>
              <Pressable
                style={styles.checkWrap}
                onPress={() => handleToggle(item)}
                accessibilityRole="checkbox"
                accessibilityLabel={`Marcar ${item.product?.name ?? item.ingredient?.name ?? 'item'} como ${item.status === 'purchased' ? 'pendiente' : 'comprado'}`}
                disabled={Boolean(sync?.saving)}
              >
                {sync?.saving ? (
                  <ActivityIndicator size="small" color={COLORS.primary} />
                ) : (
                  <MaterialCommunityIcons
                    name={item.status === 'purchased' ? 'check-circle' : 'circle-outline'}
                    size={28}
                    color={item.status === 'purchased' ? COLORS.success : COLORS.textHint}
                  />
                )}
              </Pressable>
              <View style={styles.itemBody}>
                <Text style={[styles.itemName, item.status === 'purchased' && styles.strikethrough]} numberOfLines={2}>
                  {item.product?.name ?? item.ingredient?.name ?? 'Item'}
                </Text>
                <Text style={styles.itemMeta}>
                  {item.quantity} {item.unit?.symbol ?? ''}
                  {item.estimated_price !== null ? ` · Estimado: $${item.estimated_price}` : ' · Sin precio estimado'}
                </Text>
                <View style={styles.priceRow}>
                  <TextInput
                    style={styles.priceInput}
                    value={priceDraft}
                    onChangeText={(text) => setPriceDrafts((current) => ({ ...current, [item.id]: text }))}
                    placeholder="Precio real"
                    placeholderTextColor={COLORS.textHint}
                    keyboardType="decimal-pad"
                    accessibilityLabel={`Precio real de ${item.product?.name ?? item.ingredient?.name ?? 'item'}`}
                  />
                  <Pressable
                    onPress={() => handleSavePrice(item)}
                    accessibilityRole="button"
                    accessibilityLabel="Guardar precio real"
                    style={styles.priceSaveBtn}
                    disabled={Boolean(sync?.saving)}
                  >
                    <MaterialCommunityIcons name="content-save-outline" size={20} color={COLORS.primary} />
                  </Pressable>
                </View>
                {sync?.saving ? <Text style={styles.syncSaving}>Guardando...</Text> : null}
                {sync?.saved ? <Text style={styles.syncSaved}>Guardado</Text> : null}
                {sync?.error ? (
                  <View style={styles.itemErrorBox}>
                    <Text style={styles.itemErrorText}>{sync.error.message}</Text>
                    {__DEV__ && sync.error.traceId ? (
                      <Text style={styles.itemTraceText}>Trace ID: {sync.error.traceId}</Text>
                    ) : null}
                    <Pressable
                      onPress={() => handleToggle(item)}
                      accessibilityRole="button"
                      accessibilityLabel="Reintentar item"
                      style={styles.retryBtn}
                    >
                      <Text style={styles.retryText}>Reintentar</Text>
                    </Pressable>
                  </View>
                ) : null}
              </View>
              <StatusBadge status={item.status} />
            </View>
          );
        }}
        contentContainerStyle={styles.list}
        onRefresh={refresh}
        refreshing={loading}
      />

      <View style={styles.footer}>
        <AppButton
          title={finishing ? 'Finalizando...' : 'Finalizar compra'}
          onPress={handleFinish}
          loading={finishing}
          fullWidth
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  totalBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: COLORS.dark,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
  },
  totalLabel: { fontSize: FONT_SIZE.xs, color: 'rgba(255,255,255,0.7)' },
  totalValue: { fontSize: FONT.subtitleSize, fontWeight: '700', color: '#fff' },
  list: { padding: SPACING.md, gap: SPACING.sm, paddingBottom: SPACING.xxl },
  itemCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.sm,
    padding: SPACING.md,
    gap: SPACING.md,
    ...SHADOW.sm,
    minHeight: TOUCH_TARGET + 8,
  },
  itemCardError: { borderWidth: 1, borderColor: COLORS.error },
  checkWrap: { width: 32, alignItems: 'center' },
  itemBody: { flex: 1, gap: 3 },
  itemName: { fontSize: FONT.bodySize, fontWeight: '600', color: COLORS.textPrimary },
  strikethrough: { textDecorationLine: 'line-through', color: COLORS.textHint },
  itemMeta: { fontSize: FONT_SIZE.xs, color: COLORS.textSecondary },
  priceRow: { flexDirection: 'row', alignItems: 'center', gap: SPACING.xs, marginTop: 4 },
  priceInput: {
    flex: 1,
    minHeight: 36,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.sm,
    paddingHorizontal: SPACING.sm,
    fontSize: FONT_SIZE.sm,
    color: COLORS.textPrimary,
  },
  priceSaveBtn: { padding: SPACING.xs, minWidth: TOUCH_TARGET - 12, minHeight: TOUCH_TARGET - 12, alignItems: 'center', justifyContent: 'center' },
  syncSaving: { fontSize: FONT_SIZE.xs, color: COLORS.primary, fontWeight: '600' },
  syncSaved: { fontSize: FONT_SIZE.xs, color: COLORS.success, fontWeight: '600' },
  itemErrorBox: { marginTop: SPACING.xs, gap: 4 },
  itemErrorText: { fontSize: FONT_SIZE.xs, color: COLORS.error },
  itemTraceText: { fontSize: FONT_SIZE.xs, color: COLORS.textHint },
  retryBtn: { alignSelf: 'flex-start', paddingVertical: 4, paddingRight: SPACING.sm },
  retryText: { color: COLORS.primary, fontSize: FONT_SIZE.xs, fontWeight: '700' },
  footer: { padding: SPACING.md, backgroundColor: COLORS.surface, borderTopWidth: 1, borderTopColor: COLORS.border },
});
