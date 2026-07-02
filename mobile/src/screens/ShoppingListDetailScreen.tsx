import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useRouter } from 'expo-router';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { StatusBadge } from '@/components/StatusBadge';
import { MoneyText } from '@/components/MoneyText';
import { FormError } from '@/components/FormError';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useShoppingListDetail } from '@/hooks/useShoppingListDetail';
import { useProducts } from '@/hooks/useProducts';
import { useUnits } from '@/hooks/useUnits';
import { shoppingListItemsApi, shoppingListsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING, TOUCH_TARGET } from '@/utils/theme';
import type { ShoppingListItem } from '@/types/shopping';
import type { ProductSummary } from '@/types/product';

interface Props {
  listId: number;
}

const DEBOUNCE_MS = 400;
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

export function ShoppingListDetailScreen({ listId }: Props) {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { list, items, loading, error, refresh } = useShoppingListDetail(groupId, listId);
  const { data: units } = useUnits();

  const [addModalVisible, setAddModalVisible] = useState(false);
  const [productSearch, setProductSearch] = useState('');
  const [selectedProduct, setSelectedProduct] = useState<ProductSummary | null>(null);
  const [itemQty, setItemQty] = useState('1');
  const [itemUnitId, setItemUnitId] = useState<number | null>(null);
  const [addError, setAddError] = useState<string | null>(null);
  const [addingItem, setAddingItem] = useState(false);
  const [deletingItemId, setDeletingItemId] = useState<number | null>(null);
  const [startingSession, setStartingSession] = useState(false);
  const { data: products, loading: loadingProducts, setFilters: setProductFilters } = useProducts();

  const handleProductSearch = useCallback((text: string) => {
    setProductSearch(text);
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      setProductFilters({ search: text || undefined });
    }, DEBOUNCE_MS);
  }, [setProductFilters]);

  const handleSelectProduct = useCallback((p: ProductSummary) => {
    setSelectedProduct(p);
    setItemUnitId(p.unit?.id ?? null);
    setAddError(null);
  }, []);

  async function handleAddItem() {
    if (!groupId || !selectedProduct) { setAddError('Seleccioná un producto.'); return; }
    if (!itemUnitId) { setAddError('El producto no tiene unidad.'); return; }
    const qty = Number(itemQty);
    if (!itemQty || isNaN(qty) || qty <= 0) { setAddError('Ingresá una cantidad válida.'); return; }

    setAddingItem(true);
    setAddError(null);
    try {
      await shoppingListItemsApi.create(groupId, listId, {
        product_id: selectedProduct.id,
        quantity: qty,
        unit_id: itemUnitId,
      });
      setAddModalVisible(false);
      setSelectedProduct(null);
      setItemQty('1');
      setProductSearch('');
      refresh();
    } catch (err) {
      if (err instanceof ApiError) {
        setAddError(err.normalized.message);
      } else {
        setAddError('Error al agregar el item.');
      }
    } finally {
      setAddingItem(false);
    }
  }

  async function handleDeleteItem(item: ShoppingListItem) {
    if (!groupId) return;
    Alert.alert('Eliminar item', `¿Eliminar "${item.product?.name ?? item.ingredient?.name ?? 'item'}"?`, [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Eliminar',
        style: 'destructive',
        onPress: async () => {
          setDeletingItemId(item.id);
          try {
            await shoppingListItemsApi.delete(groupId, listId, item.id);
            refresh();
          } catch {
            Alert.alert('Error', 'No se pudo eliminar el item.');
          } finally {
            setDeletingItemId(null);
          }
        },
      },
    ]);
  }

  async function handleTogglePurchased(item: ShoppingListItem) {
    if (!groupId) return;
    const newStatus = item.status === 'purchased' ? 'pending' : 'purchased';
    try {
      await shoppingListItemsApi.update(groupId, listId, item.id, { status: newStatus });
      refresh();
    } catch {
      Alert.alert('Error', 'No se pudo actualizar el item.');
    }
  }

  async function handleStartSession() {
    if (!groupId || !list) return;
    if (list.status === 'completed' || list.status === 'cancelled') {
      Alert.alert('Lista finalizada', 'Esta lista ya no puede iniciarse.');
      return;
    }
    setStartingSession(true);
    try {
      const res = await shoppingListsApi.startSession(groupId, listId);
      router.push({ pathname: '/(app)/shopping-session/[listId]' as never, params: { listId: String(listId), sessionId: String(res.data.id) } });
    } catch (err) {
      if (err instanceof ApiError && err.normalized.status === 409) {
        Alert.alert('Sesión activa', 'Ya existe una sesión activa para esta lista.');
      } else {
        Alert.alert('Error', 'No se pudo iniciar la sesión.');
      }
    } finally {
      setStartingSession(false);
    }
  }

  if (!selectedGroup) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Lista" showBack onBack={goBackOrHome} />
        <EmptyState icon="account-group-outline" message="Seleccioná un grupo familiar." />
      </View>
    );
  }

  if (loading) return <LoadingScreen message="Cargando lista..." />;

  if (error) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Lista" showBack onBack={goBackOrHome} />
        <ErrorState message={friendlyMessage(error)} traceId={error.traceId ?? ''} onRetry={refresh} type="server" />
      </View>
    );
  }

  if (!list) return null;

  const canEdit = list.status !== 'completed' && list.status !== 'cancelled';
  const purchased = items.filter((i) => i.status === 'purchased').length;
  const total = items.length;

  return (
    <View style={styles.fill}>
      <AppHeader
        title={`Lista #${list.id}`}
        subtitle={selectedGroup.name}
        showBack
        onBack={goBackOrHome}
        rightAction={
          canEdit ? (
            <Pressable
              onPress={() => setAddModalVisible(true)}
              accessibilityLabel="Agregar item"
              accessibilityRole="button"
              style={styles.addBtn}
            >
              <MaterialCommunityIcons name="plus" size={26} color={COLORS.textInverse} />
            </Pressable>
          ) : (
            <Pressable
              onPress={() => router.push({ pathname: '/(app)/shopping-lists/[id]/edit' as never, params: { id: String(listId) } })}
              accessibilityLabel="Editar lista"
              accessibilityRole="button"
              style={styles.addBtn}
            >
              <MaterialCommunityIcons name="pencil-outline" size={22} color={COLORS.textInverse} />
            </Pressable>
          )
        }
      />
      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
      >
        {/* Summary */}
        <View style={styles.summaryCard}>
          <View style={styles.row}>
            <StatusBadge status={list.status} />
            {list.estimated_total != null && (
              <MoneyText amount={list.estimated_total} style={styles.total} />
            )}
          </View>
          <Text style={styles.progress}>{purchased}/{total} items comprados</Text>
          {total > 0 && (
            <View style={styles.barBg}>
              <View style={[styles.barFill, { width: `${Math.round((purchased / total) * 100)}%` as `${number}%` }]} />
            </View>
          )}
        </View>

        {/* Items */}
        {items.length === 0 ? (
          <EmptyState icon="clipboard-list-outline" message="Sin items. Agregá productos a esta lista." />
        ) : (
          items.map((item) => (
            <View key={item.id} style={styles.itemCard}>
              <Pressable
                style={styles.itemCheck}
                onPress={() => handleTogglePurchased(item)}
                accessibilityRole="checkbox"
                accessibilityLabel={item.status === 'purchased' ? 'Desmarcar' : 'Marcar como comprado'}
              >
                <MaterialCommunityIcons
                  name={item.status === 'purchased' ? 'check-circle' : 'circle-outline'}
                  size={24}
                  color={item.status === 'purchased' ? COLORS.success : COLORS.textHint}
                />
              </Pressable>
              <View style={styles.itemBody}>
                <Text style={[styles.itemName, item.status === 'purchased' && styles.strikethrough]} numberOfLines={2}>
                  {item.product?.name ?? item.ingredient?.name ?? 'Item'}
                </Text>
                <Text style={styles.itemMeta}>
                  {item.quantity} {item.unit?.symbol ?? ''}{item.estimated_price != null ? ` · ` : ''}
                  {item.estimated_price != null && <MoneyText amount={item.estimated_price} />}
                </Text>
              </View>
              {canEdit && (
                deletingItemId === item.id ? (
                  <ActivityIndicator size="small" color={COLORS.error} />
                ) : (
                  <Pressable
                    onPress={() => handleDeleteItem(item)}
                    accessibilityLabel="Eliminar item"
                    accessibilityRole="button"
                    style={styles.deleteBtn}
                  >
                    <MaterialCommunityIcons name="trash-can-outline" size={20} color={COLORS.error} />
                  </Pressable>
                )
              )}
            </View>
          ))
        )}

        {/* Actions */}
        {canEdit && (
          <AppButton
            title={startingSession ? 'Iniciando...' : 'Iniciar compra'}
            onPress={handleStartSession}
            loading={startingSession}
            fullWidth
            style={styles.actionBtn}
          />
        )}
      </ScrollView>

      {/* Add item modal */}
      <Modal visible={addModalVisible} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setAddModalVisible(false)}>
        <View style={styles.modal}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Agregar producto</Text>
            <Pressable onPress={() => setAddModalVisible(false)} accessibilityLabel="Cerrar" style={styles.modalClose}>
              <MaterialCommunityIcons name="close" size={24} color={COLORS.textPrimary} />
            </Pressable>
          </View>

          <FormError message={addError} />

          {!selectedProduct ? (
            <>
              <View style={styles.modalSearch}>
                <MaterialCommunityIcons name="magnify" size={18} color={COLORS.textHint} />
                <TextInput
                  style={styles.modalSearchInput}
                  placeholder="Buscar producto..."
                  placeholderTextColor={COLORS.textHint}
                  value={productSearch}
                  onChangeText={handleProductSearch}
                  autoFocus
                  autoCapitalize="none"
                  autoCorrect={false}
                />
              </View>
              {loadingProducts ? (
                <ActivityIndicator style={{ margin: SPACING.xl }} color={COLORS.primary} />
              ) : (
                <FlatList
                  data={products}
                  keyExtractor={(p) => String(p.id)}
                  renderItem={({ item }) => (
                    <Pressable style={({ pressed }) => [styles.modalItem, pressed && { opacity: 0.7 }]} onPress={() => handleSelectProduct(item)}>
                      <Text style={styles.modalItemName} numberOfLines={1}>{item.name}</Text>
                      {item.brand?.name ? <Text style={styles.modalItemMeta}>{item.brand.name}</Text> : null}
                      {item.unit ? <Text style={styles.modalItemMeta}>{item.unit.symbol}</Text> : <Text style={[styles.modalItemMeta, { color: COLORS.error }]}>Sin unidad</Text>}
                    </Pressable>
                  )}
                  ListEmptyComponent={<Text style={styles.modalEmpty}>Sin resultados.</Text>}
                  keyboardShouldPersistTaps="handled"
                />
              )}
            </>
          ) : (
            <ScrollView style={{ flex: 1 }} contentContainerStyle={{ padding: SPACING.md, gap: SPACING.md }} keyboardShouldPersistTaps="handled">
              <View style={styles.selectedProduct}>
                <Text style={styles.selectedProductName}>{selectedProduct.name}</Text>
                {selectedProduct.brand?.name ? <Text style={styles.modalItemMeta}>{selectedProduct.brand.name}</Text> : null}
                <Pressable onPress={() => setSelectedProduct(null)} style={{ marginTop: 4 }}>
                  <Text style={{ color: COLORS.primary, fontSize: FONT_SIZE.xs }}>Cambiar producto</Text>
                </Pressable>
              </View>

              <View style={{ gap: 6 }}>
                <Text style={styles.fieldLabel}>Cantidad</Text>
                <TextInput
                  style={styles.input}
                  value={itemQty}
                  onChangeText={setItemQty}
                  keyboardType="decimal-pad"
                  placeholder="1"
                  placeholderTextColor={COLORS.textHint}
                />
              </View>

              {!itemUnitId && units.length > 0 && (
                <View style={{ gap: 6 }}>
                  <Text style={styles.fieldLabel}>Unidad</Text>
                  <ScrollView horizontal showsHorizontalScrollIndicator={false}>
                    <View style={{ flexDirection: 'row', gap: SPACING.xs }}>
                      {units.map((u) => (
                        <Pressable
                          key={u.id}
                          style={[styles.chip, itemUnitId === u.id && styles.chipSelected]}
                          onPress={() => setItemUnitId(u.id)}
                        >
                          <Text style={[styles.chipText, itemUnitId === u.id && styles.chipTextSelected]}>{u.symbol || u.code}</Text>
                        </Pressable>
                      ))}
                    </View>
                  </ScrollView>
                </View>
              )}

              {itemUnitId && (
                <Text style={styles.modalItemMeta}>
                  Unidad: {selectedProduct.unit?.symbol ?? units.find((u) => u.id === itemUnitId)?.symbol ?? String(itemUnitId)}
                </Text>
              )}

              <AppButton title="Agregar" onPress={handleAddItem} loading={addingItem} fullWidth />
            </ScrollView>
          )}
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  scroll: { flex: 1 },
  content: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  summaryCard: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    gap: SPACING.sm,
    ...SHADOW.sm,
  },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  total: { fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary },
  progress: { fontSize: FONT_SIZE.xs, color: COLORS.textSecondary },
  barBg: { height: 6, backgroundColor: COLORS.border, borderRadius: RADIUS.full, overflow: 'hidden' },
  barFill: { height: 6, backgroundColor: COLORS.primary, borderRadius: RADIUS.full },
  itemCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.sm,
    padding: SPACING.sm,
    gap: SPACING.sm,
    ...SHADOW.sm,
  },
  itemCheck: { padding: 4 },
  itemBody: { flex: 1, gap: 2 },
  itemName: { fontSize: FONT.bodySize, fontWeight: '500', color: COLORS.textPrimary },
  strikethrough: { textDecorationLine: 'line-through', color: COLORS.textHint },
  itemMeta: { fontSize: FONT_SIZE.xs, color: COLORS.textSecondary },
  deleteBtn: { padding: 8 },
  actionBtn: { marginTop: SPACING.sm },
  addBtn: { width: TOUCH_TARGET, height: TOUCH_TARGET, alignItems: 'center', justifyContent: 'center' },
  // Modal
  modal: { flex: 1, backgroundColor: COLORS.background },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: SPACING.md,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
    backgroundColor: COLORS.surface,
  },
  modalTitle: { fontSize: FONT.subtitleSize, fontWeight: FONT.subtitleWeight, color: COLORS.textPrimary },
  modalClose: { padding: SPACING.xs },
  modalSearch: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.xs,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
    backgroundColor: COLORS.surface,
  },
  modalSearchInput: { flex: 1, fontSize: FONT.bodySize, color: COLORS.textPrimary, minHeight: 36 },
  modalItem: {
    padding: SPACING.md,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.borderLight,
    gap: 2,
  },
  modalItemName: { fontSize: FONT.bodySize, fontWeight: '600', color: COLORS.textPrimary },
  modalItemMeta: { fontSize: FONT_SIZE.xs, color: COLORS.textSecondary },
  modalEmpty: { textAlign: 'center', padding: SPACING.xxl, color: COLORS.textHint },
  selectedProduct: {
    backgroundColor: COLORS.primarySurface,
    borderRadius: RADIUS.sm,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.primary,
  },
  selectedProductName: { fontSize: FONT.bodySize, fontWeight: '600', color: COLORS.textPrimary },
  fieldLabel: { fontSize: FONT.labelSize, fontWeight: FONT.labelWeight, color: COLORS.textSecondary },
  input: {
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.sm,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    fontSize: FONT.bodySize,
    color: COLORS.textPrimary,
    minHeight: 44,
  },
  chip: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.full,
    paddingHorizontal: SPACING.sm,
    paddingVertical: 6,
    backgroundColor: COLORS.surface,
  },
  chipSelected: { borderColor: COLORS.primary, backgroundColor: COLORS.primarySurface },
  chipText: { fontSize: FONT_SIZE.xs, color: COLORS.textSecondary, fontWeight: '500' },
  chipTextSelected: { color: COLORS.primary, fontWeight: '700' },
});
