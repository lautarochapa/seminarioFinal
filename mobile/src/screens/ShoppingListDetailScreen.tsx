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
import { PriceSourceBadge } from '@/components/PriceSourceBadge';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useShoppingListDetail } from '@/hooks/useShoppingListDetail';
import { useProducts } from '@/hooks/useProducts';
import { useUnits } from '@/hooks/useUnits';
import { shoppingListItemsApi, shoppingListsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING, TOUCH_TARGET } from '@/utils/theme';
import type { CompleteShoppingListItemRequest, ShoppingListItem } from '@/types/shopping';
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
  const [freeTextName, setFreeTextName] = useState('');
  const [selectedProduct, setSelectedProduct] = useState<ProductSummary | null>(null);
  const [itemQty, setItemQty] = useState('1');
  const [itemUnitId, setItemUnitId] = useState<number | null>(null);
  const [addError, setAddError] = useState<string | null>(null);
  const [addingItem, setAddingItem] = useState(false);
  const [deletingItemId, setDeletingItemId] = useState<number | null>(null);
  const [startingSession, setStartingSession] = useState(false);
  const [startingPurchase, setStartingPurchase] = useState(false);
  const [confirmingList, setConfirmingList] = useState(false);
  const { data: products, loading: loadingProducts, setFilters: setProductFilters } = useProducts();

  const [completeModalVisible, setCompleteModalVisible] = useState(false);
  const [completingPurchase, setCompletingPurchase] = useState(false);
  const [completeError, setCompleteError] = useState<string | null>(null);
  const [stockSelections, setStockSelections] = useState<Record<number, boolean>>({});

  const handleProductSearch = useCallback((text: string) => {
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
    const name = freeTextName.trim();
    if (!groupId) return;
    if (!selectedProduct && !name) { setAddError('Escribí qué necesitás comprar o seleccioná un producto.'); return; }
    if (selectedProduct && !itemUnitId) { setAddError('El producto no tiene unidad.'); return; }
    const qty = Number(itemQty);
    if (itemQty && (isNaN(qty) || qty <= 0)) { setAddError('Ingresá una cantidad válida.'); return; }

    setAddingItem(true);
    setAddError(null);
    try {
      await shoppingListItemsApi.create(groupId, listId, {
        product_id: selectedProduct ? selectedProduct.id : undefined,
        free_text_name: selectedProduct ? undefined : name,
        quantity: itemQty ? qty : undefined,
        unit_id: itemUnitId,
      });
      setAddModalVisible(false);
      setSelectedProduct(null);
      setFreeTextName('');
      setItemQty('1');
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
    Alert.alert('Eliminar item', `¿Eliminar "${item.display_name ?? item.product?.name ?? item.ingredient?.name ?? item.free_text_name ?? 'item'}"?`, [
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
    } catch (err) {
      // Show the real reason (e.g. transition rejected) instead of a generic message.
      if (err instanceof ApiError) {
        Alert.alert('No se pudo actualizar', err.normalized.message);
      } else {
        Alert.alert('Error', 'No se pudo actualizar el item.');
      }
    }
  }

  async function handleConfirmList() {
    if (!groupId) return;
    setConfirmingList(true);
    try {
      await shoppingListsApi.update(groupId, listId, { status: 'active' });
      refresh();
    } catch (err) {
      Alert.alert('Error', err instanceof ApiError ? err.normalized.message : 'No se pudo confirmar la lista.');
    } finally {
      setConfirmingList(false);
    }
  }

  async function handleStartPurchase() {
    if (!groupId) return;
    setStartingPurchase(true);
    try {
      await shoppingListsApi.start(groupId, listId);
      refresh();
    } catch (err) {
      Alert.alert('No se pudo comenzar la compra', err instanceof ApiError ? err.normalized.message : 'Intenta nuevamente.');
    } finally {
      setStartingPurchase(false);
    }
  }

  async function handlePausePurchase() {
    if (!groupId) return;
    try {
      await shoppingListsApi.update(groupId, listId, { status: 'active' });
      refresh();
    } catch (err) {
      Alert.alert('Error', err instanceof ApiError ? err.normalized.message : 'No se pudo pausar la compra.');
    }
  }

  async function handleStartSession() {
    if (!groupId || !list) return;
    if (list.status !== 'active' && list.status !== 'in_progress') {
      Alert.alert('No disponible', 'La lista debe estar "Lista para comprar" o "En compra" para usar el escaner.');
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

  const purchasedItems = items.filter((i) => i.status === 'purchased');

  function canAutoAssociate(item: ShoppingListItem): boolean {
    return !!item.product?.id;
  }

  function handleOpenComplete() {
    const initial: Record<number, boolean> = {};
    purchasedItems.forEach((item) => {
      // Only pre-check items whose product is already known; free-text/ingredient-only items
      // require an explicit user decision (create pending product) — never guessed automatically.
      initial[item.id] = canAutoAssociate(item);
    });
    setStockSelections(initial);
    setCompleteError(null);
    setCompleteModalVisible(true);
  }

  async function handleConfirmComplete() {
    if (!groupId) return;
    const requestItems: CompleteShoppingListItemRequest[] = purchasedItems
      .filter((item) => stockSelections[item.id])
      .map((item) => {
        if (canAutoAssociate(item)) {
          return { shopping_list_item_id: item.id, add_to_stock: true };
        }
        return {
          shopping_list_item_id: item.id,
          add_to_stock: true,
          create_pending_product: true,
          name: item.free_text_name ?? item.display_name ?? 'Articulo',
        };
      });

    setCompletingPurchase(true);
    setCompleteError(null);
    try {
      const res = await shoppingListsApi.complete(groupId, listId, { items: requestItems });
      setCompleteModalVisible(false);
      refresh();
      Alert.alert(
        'Compra finalizada',
        `Agregamos ${res.data.items_added_to_stock_count} producto(s) a Mi cocina.`,
        [
          { text: 'Ver Mi cocina', onPress: () => router.push('/(app)/stock' as never) },
          { text: 'Ver compra', onPress: () => router.push({ pathname: '/(app)/purchases/[id]' as never, params: { id: String(res.data.purchase.id) } }) },
          { text: 'Volver a Compras', onPress: () => router.push('/(app)/purchases' as never) },
        ]
      );
    } catch (err) {
      if (err instanceof ApiError && err.normalized.status === 409) {
        // Already completed by a previous attempt (e.g. retry after timeout): not an error, just refresh.
        setCompleteModalVisible(false);
        refresh();
        Alert.alert('Compra ya finalizada', 'Esta lista ya habia sido finalizada.');
      } else if (err instanceof ApiError) {
        setCompleteError(err.normalized.message);
      } else {
        setCompleteError('No pudimos finalizar la compra. Intenta nuevamente.');
      }
    } finally {
      setCompletingPurchase(false);
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

  const isDraft = list.status === 'draft';
  const isActive = list.status === 'active';
  const isInProgress = list.status === 'in_progress';
  const isClosed = list.status === 'completed' || list.status === 'cancelled';
  const canEditItems = isDraft || isActive; // add/remove articles before the purchase starts
  const canMarkPurchased = isInProgress; // checking items off only makes sense once the purchase is under way
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
          canEditItems ? (
            <Pressable
              onPress={() => setAddModalVisible(true)}
              accessibilityLabel="Agregar item"
              accessibilityRole="button"
              style={styles.addBtn}
            >
              <MaterialCommunityIcons name="plus" size={26} color={COLORS.textInverse} />
            </Pressable>
          ) : !isClosed ? (
            <Pressable
              onPress={() => router.push({ pathname: '/(app)/shopping-lists/[id]/edit' as never, params: { id: String(listId) } })}
              accessibilityLabel="Editar lista"
              accessibilityRole="button"
              style={styles.addBtn}
            >
              <MaterialCommunityIcons name="pencil-outline" size={22} color={COLORS.textInverse} />
            </Pressable>
          ) : undefined
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
          {isInProgress && (
            <>
              <Text style={styles.progress}>{purchased} de {total} articulos comprados</Text>
              {total > 0 && (
                <View style={styles.barBg}>
                  <View style={[styles.barFill, { width: `${Math.round((purchased / total) * 100)}%` as `${number}%` }]} />
                </View>
              )}
            </>
          )}
          {isDraft && <Text style={styles.itemMeta}>Todavia en edicion. Confirmala cuando este lista para comprar.</Text>}
          {isActive && <Text style={styles.itemMeta}>Lista confirmada. Toca &quot;Comenzar compra&quot; cuando salgas a comprar.</Text>}
        </View>

        {/* Items */}
        {items.length === 0 ? (
          <EmptyState icon="clipboard-list-outline" message="Sin items. Agregá productos a esta lista." />
        ) : (
          items.map((item) => (
            <View key={item.id} style={styles.itemCard}>
              <Pressable
                style={styles.itemCheck}
                onPress={() => canMarkPurchased && handleTogglePurchased(item)}
                disabled={!canMarkPurchased}
                accessibilityRole="checkbox"
                accessibilityState={{ disabled: !canMarkPurchased, checked: item.status === 'purchased' }}
                accessibilityLabel={item.status === 'purchased' ? 'Desmarcar' : 'Marcar como comprado'}
              >
                <MaterialCommunityIcons
                  name={item.status === 'purchased' ? 'check-circle' : 'circle-outline'}
                  size={24}
                  color={item.status === 'purchased' ? COLORS.success : (canMarkPurchased ? COLORS.textHint : COLORS.border)}
                />
              </Pressable>
              <View style={styles.itemBody}>
                <Text style={[styles.itemName, item.status === 'purchased' && styles.strikethrough]} numberOfLines={2}>
                  {item.display_name ?? item.product?.name ?? item.ingredient?.name ?? item.free_text_name ?? 'Item'}
                </Text>
                <Text style={styles.itemMeta}>
                  {item.quantity ?? ''} {item.unit?.symbol ?? ''}
                  {item.estimated_subtotal != null ? ' · ' : ''}
                  {item.estimated_subtotal != null && <MoneyText amount={item.estimated_subtotal} />}
                </Text>
                <PriceSourceBadge source={item.price_source} />
              </View>
              {canEditItems && (
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

        {/* Actions — depend strictly on the list's current lifecycle status */}
        {isDraft && (
          <AppButton
            title={confirmingList ? 'Confirmando...' : 'Confirmar lista'}
            onPress={handleConfirmList}
            loading={confirmingList}
            fullWidth
            style={styles.actionBtn}
            accessibilityLabel="Confirmar lista para poder comenzar la compra"
          />
        )}
        {isActive && (
          <>
            <AppButton
              title={startingPurchase ? 'Comenzando...' : 'Comenzar compra'}
              onPress={handleStartPurchase}
              loading={startingPurchase}
              fullWidth
              style={styles.actionBtn}
              accessibilityLabel="Comenzar compra"
            />
            <AppButton
              title={startingSession ? 'Iniciando...' : 'Comenzar compra con escaner'}
              onPress={handleStartSession}
              loading={startingSession}
              fullWidth
              variant="secondary"
              style={styles.actionBtn}
            />
          </>
        )}
        {isInProgress && (
          <>
            {purchased < total && (
              <Text style={[styles.itemMeta, { textAlign: 'center' }]}>
                Todavia quedan {total - purchased} articulo(s) sin comprar.
              </Text>
            )}
            {purchasedItems.length > 0 && (
              <AppButton
                title="Finalizar compra"
                onPress={handleOpenComplete}
                fullWidth
                style={styles.actionBtn}
                accessibilityLabel="Finalizar compra"
              />
            )}
            <AppButton
              title="Pausar compra"
              onPress={handlePausePurchase}
              fullWidth
              variant="secondary"
              style={styles.actionBtn}
            />
          </>
        )}
      </ScrollView>

      {/* Add item modal */}
      <Modal visible={addModalVisible} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setAddModalVisible(false)}>
        <View style={styles.modal}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Agregar artículo</Text>
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
                  placeholder="¿Qué necesitás comprar?"
                  placeholderTextColor={COLORS.textHint}
                  value={freeTextName}
                  onChangeText={(text) => { setFreeTextName(text); handleProductSearch(text); }}
                  autoFocus
                />
              </View>
              {freeTextName.trim() ? (
                <Pressable style={styles.freeTextAdd} onPress={handleAddItem} disabled={addingItem}>
                  <MaterialCommunityIcons name="plus" size={18} color={COLORS.primary} />
                  <Text style={styles.freeTextAddText}>Agregar {freeTextName.trim()} como artículo libre</Text>
                </Pressable>
              ) : null}
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

      {/* Complete purchase / add-to-stock review modal */}
      <Modal visible={completeModalVisible} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setCompleteModalVisible(false)}>
        <View style={styles.modal}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Finalizar compra</Text>
            <Pressable onPress={() => setCompleteModalVisible(false)} accessibilityLabel="Cerrar" style={styles.modalClose}>
              <MaterialCommunityIcons name="close" size={24} color={COLORS.textPrimary} />
            </Pressable>
          </View>

          <FormError message={completeError} />

          <ScrollView contentContainerStyle={{ padding: SPACING.md, gap: SPACING.sm }}>
            <Text style={styles.itemMeta}>
              Compraste {purchasedItems.length} articulo(s). Elegi cuales agregar a Mi cocina.
            </Text>
            {purchasedItems.map((item) => {
              const label = item.display_name ?? item.product?.name ?? item.ingredient?.name ?? item.free_text_name ?? 'Item';
              const autoAssociable = canAutoAssociate(item);
              const disabled = !!item.ingredient && !item.product; // recipe ingredient without a linked product: needs manual association elsewhere
              return (
                <Pressable
                  key={item.id}
                  style={[styles.itemCard, disabled && { opacity: 0.5 }]}
                  disabled={disabled}
                  onPress={() => setStockSelections((prev) => ({ ...prev, [item.id]: !prev[item.id] }))}
                  accessibilityRole="checkbox"
                >
                  <MaterialCommunityIcons
                    name={stockSelections[item.id] ? 'checkbox-marked' : 'checkbox-blank-outline'}
                    size={22}
                    color={disabled ? COLORS.textHint : COLORS.primary}
                  />
                  <View style={styles.itemBody}>
                    <Text style={styles.itemName}>{label}</Text>
                    <Text style={styles.itemMeta}>{item.quantity ?? ''} {item.unit?.symbol ?? ''}</Text>
                    {disabled && (
                      <Text style={[styles.itemMeta, { color: COLORS.error }]}>
                        Asocia un producto desde Mi cocina para poder sumarlo al stock.
                      </Text>
                    )}
                    {!disabled && !autoAssociable && (
                      <Text style={styles.itemMeta}>Se creara como producto pendiente de revision.</Text>
                    )}
                  </View>
                </Pressable>
              );
            })}
          </ScrollView>

          <View style={{ padding: SPACING.md }}>
            <AppButton
              title="Finalizar y actualizar Mi cocina"
              onPress={handleConfirmComplete}
              loading={completingPurchase}
              fullWidth
            />
          </View>
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
  freeTextAdd: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.xs,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.borderLight,
    backgroundColor: COLORS.primarySurface,
  },
  freeTextAddText: { flex: 1, color: COLORS.primary, fontWeight: '700', fontSize: FONT_SIZE.sm },
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
