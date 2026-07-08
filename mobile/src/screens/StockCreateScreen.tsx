import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { AppInput } from '@/components/AppInput';
import { FormError } from '@/components/FormError';
import { FamilyGroupSelector } from '@/components/FamilyGroupSelector';
import { EmptyState } from '@/components/EmptyState';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useStockLocations } from '@/hooks/useStockLocations';
import { stockApi, productsApi, unitsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { goBackOrHome } from '@/utils/navigation';
import { useProducts } from '@/hooks/useProducts';
import { consumePendingScanResult } from '@/utils/barcodeScanResult';
import { COLORS, FONT, FONT_SIZE, RADIUS, SPACING, TOUCH_TARGET } from '@/utils/theme';
import type { ProductSummary } from '@/types/product';
import type { StockLocation } from '@/types/stock';
import type { Unit } from '@/types/unit';

const DEBOUNCE_MS = 400;

interface StockCreateScreenProps {
  prefilledProductId?: number;
  prefilledProductName?: string;
}

export function StockCreateScreen({ prefilledProductId, prefilledProductName }: StockCreateScreenProps) {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { data: locations, loading: loadingLocs } = useStockLocations(groupId);

  const [productId, setProductId] = useState<number | null>(prefilledProductId ?? null);
  const [productName, setProductName] = useState<string>(prefilledProductName ?? '');
  const [locationId, setLocationId] = useState<number | null>(null);
  const [quantity, setQuantity] = useState('');
  const [unitId, setUnitId] = useState<number | null>(null);
  const [unitDisplay, setUnitDisplay] = useState('');
  const [expirationDate, setExpirationDate] = useState('');
  const [purchasePrice, setPurchasePrice] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [manualSubmitting, setManualSubmitting] = useState(false);
  const [manualMessage, setManualMessage] = useState<string | null>(null);
  const [manualVisible, setManualVisible] = useState(false);
  const [manualName, setManualName] = useState('');
  const [manualUnitId, setManualUnitId] = useState<number | null>(null);
  const [manualQuantity, setManualQuantity] = useState('1');
  const [units, setUnits] = useState<Unit[]>([]);

  // Product search modal
  const [productModalVisible, setProductModalVisible] = useState(false);
  const [productSearch, setProductSearch] = useState('');
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const { data: products, loading: loadingProducts, setFilters: setProductFilters } = useProducts();

  const handleProductSearch = useCallback((text: string) => {
    setProductSearch(text);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      setProductFilters({ search: text || undefined, family_group_id: groupId || undefined });
    }, DEBOUNCE_MS);
  }, [groupId, setProductFilters]);

  useEffect(() => {
    unitsApi.list()
      .then((res) => {
        const loaded = res.data || [];
        setUnits(loaded);
        if (!manualUnitId && loaded[0]) setManualUnitId(loaded[0].id);
      })
      .catch(() => setUnits([]));
  }, [manualUnitId]);

  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  const handleSelectProduct = useCallback((p: ProductSummary) => {
    setProductId(p.id);
    setProductName(p.name);
    if (p.unit) {
      setUnitId(p.unit.id);
      setUnitDisplay(p.unit.symbol || p.unit.code || p.unit.name);
    }
    setProductModalVisible(false);
    setFieldErrors((e) => ({ ...e, product_id: '', unit_id: '' }));
  }, []);

  function openManualProduct() {
    const name = productSearch.trim();
    if (!name) return;
    setManualName(name);
    setManualQuantity(quantity || '1');
    setManualVisible(true);
    setManualMessage(null);
  }

  async function handleManualProductSubmit() {
    const name = manualName.trim();
    if (!groupId || !name || !manualUnitId || !manualQuantity || Number(manualQuantity) <= 0) return;
    setManualSubmitting(true);
    setManualMessage(null);
    try {
      await stockApi.createManualProduct(groupId, {
        product: {
          name,
          unit_id: manualUnitId,
        },
        stock: {
          quantity: Number(manualQuantity),
          unit_id: manualUnitId,
          stock_location_id: locationId,
          expiration_date: expirationDate || null,
          purchase_price: purchasePrice ? Number(purchasePrice) : null,
        },
      });
      setManualVisible(false);
      router.replace('/(app)/stock' as never);
    } catch (err) {
      if (err instanceof ApiError) {
        setManualMessage(err.normalized.message);
      } else {
        setManualMessage('No se pudo cargar el producto.');
      }
    } finally {
      setManualSubmitting(false);
    }
  }

  // Picks up the product resolved by the barcode scanner screen (if any) without
  // losing the fields the user already filled in on this screen.
  useFocusEffect(
    useCallback(() => {
      const pending = consumePendingScanResult();
      if (!pending) return;

      setProductId(pending.productId);
      setProductName(pending.productName);
      setFieldErrors((e) => ({ ...e, product_id: '' }));

      productsApi.get(pending.productId)
        .then((res) => {
          if (res.data.unit) {
            setUnitId(res.data.unit.id);
            setUnitDisplay(res.data.unit.symbol || res.data.unit.code || res.data.unit.name);
            setFieldErrors((e) => ({ ...e, unit_id: '' }));
          }
        })
        .catch(() => { /* el usuario puede completar la unidad buscando el producto manualmente */ });
    }, []),
  );

  async function handleSubmit() {
    const errors: Record<string, string> = {};
    if (!productId) errors.product_id = 'Seleccioná un producto.';
    if (!quantity || isNaN(Number(quantity)) || Number(quantity) < 0) {
      errors.quantity = 'Ingresá una cantidad válida.';
    }
    if (!unitId) errors.unit_id = 'El producto seleccionado no tiene unidad. Elegí otro.';
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      return;
    }
    if (!groupId) return;

    setSubmitting(true);
    setSubmitError(null);
    setFieldErrors({});

    try {
      await stockApi.create(groupId, {
        product_id: productId!,
        stock_location_id: locationId,
        quantity: Number(quantity),
        unit_id: unitId!,
        expiration_date: expirationDate || null,
        purchase_price: purchasePrice ? Number(purchasePrice) : null,
      });
      router.replace('/(app)/stock' as never);
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.normalized.status === 422 && Object.keys(err.normalized.fieldErrors).length > 0) {
          const fe: Record<string, string> = {};
          Object.entries(err.normalized.fieldErrors).forEach(([k, v]) => {
            fe[k] = v[0] ?? '';
          });
          setFieldErrors(fe);
        } else {
          setSubmitError(err.normalized.message);
        }
      } else {
        setSubmitError('Error al guardar. Intentá de nuevo.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  if (!selectedGroup) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Agregar al stock" showBack onBack={goBackOrHome} />
        <View style={styles.centered}>
          <FamilyGroupSelector />
          <EmptyState
            icon="account-group-outline"
            message="Seleccioná un grupo familiar para agregar al stock."
          />
        </View>
      </View>
    );
  }

  return (
    <View style={styles.fill}>
      <AppHeader title="Agregar al stock" subtitle={selectedGroup.name} showBack onBack={goBackOrHome} />
      <KeyboardAvoidingView
        style={styles.fill}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={styles.content}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
        >
          <FormError message={submitError} />

          {/* Product selector */}
          <View style={styles.field}>
            <Text style={styles.label}>Producto *</Text>
            <Pressable
              style={[styles.selector, fieldErrors.product_id ? styles.selectorError : null]}
              onPress={() => setProductModalVisible(true)}
              accessibilityRole="button"
              accessibilityLabel="Seleccionar producto"
            >
              {productName ? (
            <View style={styles.selectedProductText}>
              <Text style={styles.selectorValue} numberOfLines={1}>{productName}</Text>
            </View>
              ) : (
                <Text style={styles.selectorPlaceholder}>Buscar producto...</Text>
              )}
              <MaterialCommunityIcons name="magnify" size={20} color={COLORS.textHint} />
            </Pressable>
            {fieldErrors.product_id ? (
              <Text style={styles.fieldError}>{fieldErrors.product_id}</Text>
            ) : null}
            <Pressable
              style={styles.scanBtn}
              onPress={() => router.push('/(app)/barcode-scanner' as never)}
              accessibilityRole="button"
              accessibilityLabel="Escanear código de barras"
            >
              <MaterialCommunityIcons name="barcode-scan" size={18} color={COLORS.primary} />
              <Text style={styles.scanBtnText}>Escanear código</Text>
            </Pressable>
          </View>

          {/* Location */}
          <View style={styles.field}>
            <Text style={styles.label}>Ubicación</Text>
            {loadingLocs ? (
              <ActivityIndicator size="small" color={COLORS.primary} />
            ) : locations.length === 0 ? (
              <Text style={styles.hintText}>Sin ubicaciones. Se guardará sin ubicación.</Text>
            ) : (
              <View style={styles.chipRow}>
                <Pressable
                  style={[styles.locationChip, locationId === null && styles.locationChipSelected]}
                  onPress={() => setLocationId(null)}
                  accessibilityRole="button"
                  accessibilityLabel="Sin ubicación"
                >
                  <Text style={[styles.chipText, locationId === null && styles.chipTextSelected]}>Sin ubicación</Text>
                </Pressable>
                {locations.map((loc: StockLocation) => (
                  <Pressable
                    key={loc.id}
                    style={[styles.locationChip, locationId === loc.id && styles.locationChipSelected]}
                    onPress={() => setLocationId(loc.id)}
                    accessibilityRole="button"
                    accessibilityLabel={loc.name}
                  >
                    <Text style={[styles.chipText, locationId === loc.id && styles.chipTextSelected]} numberOfLines={1}>
                      {loc.name}
                    </Text>
                  </Pressable>
                ))}
              </View>
            )}
          </View>

          <AppInput
            label="Cantidad *"
            value={quantity}
            onChangeText={(t) => { setQuantity(t); setFieldErrors((e) => ({ ...e, quantity: '' })); }}
            keyboardType="decimal-pad"
            placeholder="0"
            error={fieldErrors.quantity}
          />

          {unitDisplay ? (
            <View style={styles.readonlyField}>
              <Text style={styles.label}>Unidad</Text>
              <Text style={styles.readonlyValue}>{unitDisplay}</Text>
            </View>
          ) : productId ? (
            <View style={styles.readonlyField}>
              <Text style={styles.label}>Unidad</Text>
              <Text style={[styles.readonlyValue, { color: COLORS.error }]}>
                {fieldErrors.unit_id || 'Producto sin unidad. Elegí otro producto.'}
              </Text>
            </View>
          ) : null}

          <AppInput
            label="Fecha de vencimiento"
            value={expirationDate}
            onChangeText={setExpirationDate}
            placeholder="YYYY-MM-DD"
            keyboardType="numbers-and-punctuation"
          />

          <AppInput
            label="Precio de compra"
            value={purchasePrice}
            onChangeText={setPurchasePrice}
            keyboardType="decimal-pad"
            placeholder="0.00"
          />

          <AppButton
            title="Guardar"
            onPress={handleSubmit}
            loading={submitting}
            fullWidth
            style={styles.submitBtn}
          />
        </ScrollView>
      </KeyboardAvoidingView>

      {/* Product search modal */}
      <Modal
        visible={productModalVisible}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={() => setProductModalVisible(false)}
      >
        <View style={styles.modal}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Seleccionar producto</Text>
            <Pressable
              onPress={() => setProductModalVisible(false)}
              style={styles.modalClose}
              accessibilityLabel="Cerrar"
              accessibilityRole="button"
            >
              <MaterialCommunityIcons name="close" size={24} color={COLORS.textPrimary} />
            </Pressable>
          </View>
          <View style={styles.modalSearch}>
            <MaterialCommunityIcons name="magnify" size={18} color={COLORS.textHint} />
            <TextInput
              style={styles.modalSearchInput}
              placeholder="Buscar..."
              placeholderTextColor={COLORS.textHint}
              value={productSearch}
              onChangeText={handleProductSearch}
              autoFocus
              autoCapitalize="none"
              autoCorrect={false}
            />
          </View>
          {manualMessage ? (
            <Text style={styles.requestMessage}>{manualMessage}</Text>
          ) : null}
          {loadingProducts ? (
            <ActivityIndicator style={styles.modalLoading} color={COLORS.primary} />
          ) : (
            <FlatList
              data={products}
              keyExtractor={(p) => String(p.id)}
              renderItem={({ item }) => (
                <Pressable
                  style={({ pressed }) => [styles.modalItem, pressed && styles.modalItemPressed]}
                  onPress={() => handleSelectProduct(item)}
                  accessibilityRole="button"
                  accessibilityLabel={item.name}
                >
                  <View style={styles.modalItemBody}>
                    <Text style={styles.modalItemName} numberOfLines={1}>{item.name}</Text>
                    {item.review_status === 'pending_review' ? (
                      <Text style={styles.pendingBadge}>Pendiente de revisión</Text>
                    ) : null}
                    {item.brand?.name ? (
                      <Text style={styles.modalItemMeta} numberOfLines={1}>{item.brand.name}</Text>
                    ) : null}
                  </View>
                  <MaterialCommunityIcons name="chevron-right" size={20} color={COLORS.textHint} />
                </Pressable>
              )}
              ListEmptyComponent={
                <View style={styles.modalEmptyBox}>
                  <Text style={styles.modalEmpty}>Sin resultados.</Text>
                  {productSearch.trim().length > 1 ? (
                    <AppButton
                      title="Cargar producto manualmente"
                      variant="outline"
                      onPress={openManualProduct}
                      fullWidth
                    />
                  ) : null}
                </View>
              }
              keyboardShouldPersistTaps="handled"
            />
          )}
        </View>
      </Modal>

      <Modal
        visible={manualVisible}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={() => setManualVisible(false)}
      >
        <View style={styles.modal}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Crear producto rápido</Text>
            <Pressable onPress={() => setManualVisible(false)} style={styles.modalClose} accessibilityLabel="Cerrar" accessibilityRole="button">
              <MaterialCommunityIcons name="close" size={24} color={COLORS.textPrimary} />
            </Pressable>
          </View>
          <ScrollView style={styles.scroll} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
            <Text style={styles.hintText}>Se carga ahora en tu stock y queda pendiente de revisión del catálogo.</Text>
            <AppInput label="Nombre *" value={manualName} onChangeText={setManualName} placeholder="Producto" />
            <AppInput label="Cantidad en stock *" value={manualQuantity} onChangeText={setManualQuantity} keyboardType="decimal-pad" placeholder="1" />
            <Text style={styles.label}>Unidad *</Text>
            <View style={styles.chipRow}>
              {units.map((unit) => (
                <Pressable
                  key={unit.id}
                  style={[styles.locationChip, manualUnitId === unit.id && styles.locationChipSelected]}
                  onPress={() => setManualUnitId(unit.id)}
                  accessibilityRole="button"
                  accessibilityLabel={unit.name}
                >
                  <Text style={[styles.chipText, manualUnitId === unit.id && styles.chipTextSelected]} numberOfLines={1}>
                    {unit.symbol || unit.code || unit.name}
                  </Text>
                </Pressable>
              ))}
            </View>
            {manualMessage ? <Text style={styles.fieldError}>{manualMessage}</Text> : null}
            <AppButton
              title="Cargar producto y stock"
              onPress={handleManualProductSubmit}
              loading={manualSubmitting}
              disabled={!manualName.trim() || !manualUnitId || !manualQuantity || Number(manualQuantity) <= 0}
              fullWidth
            />
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  centered: { flex: 1, justifyContent: 'center' },
  scroll: { flex: 1, backgroundColor: COLORS.background },
  content: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  field: { gap: SPACING.xs },
  label: {
    fontSize: FONT.labelSize,
    fontWeight: FONT.labelWeight,
    color: COLORS.textSecondary,
  },
  selector: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.sm,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    minHeight: TOUCH_TARGET,
    gap: SPACING.sm,
  },
  selectorError: {
    borderColor: COLORS.error,
  },
  selectorValue: {
    flex: 1,
    fontSize: FONT.bodySize,
    color: COLORS.textPrimary,
  },
  selectedProductText: { flex: 1 },
  selectorPlaceholder: {
    flex: 1,
    fontSize: FONT.bodySize,
    color: COLORS.textHint,
  },
  fieldError: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.error,
  },
  scanBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.xs,
    alignSelf: 'flex-start',
    minHeight: TOUCH_TARGET - 8,
    paddingVertical: SPACING.xs,
  },
  scanBtnText: {
    color: COLORS.primary,
    fontWeight: '700',
    fontSize: FONT_SIZE.sm,
  },
  hintText: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.textHint,
    fontStyle: 'italic',
  },
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: SPACING.xs,
  },
  locationChip: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.full,
    paddingHorizontal: SPACING.sm,
    paddingVertical: 6,
    backgroundColor: COLORS.surface,
    maxWidth: 140,
  },
  locationChipSelected: {
    borderColor: COLORS.primary,
    backgroundColor: COLORS.primarySurface,
  },
  chipText: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.textSecondary,
    fontWeight: '500',
  },
  chipTextSelected: {
    color: COLORS.primary,
    fontWeight: '700',
  },
  submitBtn: { marginTop: SPACING.sm },
  readonlyField: {
    backgroundColor: COLORS.surfaceElevated,
    borderRadius: RADIUS.sm,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: SPACING.xs,
  },
  readonlyValue: {
    fontSize: FONT.bodySize,
    color: COLORS.textPrimary,
    fontWeight: '500',
  },
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
  modalTitle: {
    fontSize: FONT.subtitleSize,
    fontWeight: FONT.subtitleWeight,
    color: COLORS.textPrimary,
  },
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
  modalSearchInput: {
    flex: 1,
    fontSize: FONT.bodySize,
    color: COLORS.textPrimary,
    minHeight: TOUCH_TARGET - 8,
  },
  modalLoading: { margin: SPACING.xxl },
  modalItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: SPACING.md,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.borderLight,
    gap: SPACING.sm,
  },
  modalItemPressed: { backgroundColor: COLORS.surfaceElevated },
  modalItemBody: { flex: 1, gap: 2 },
  modalItemName: {
    fontSize: FONT.bodySize,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  modalItemMeta: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.textSecondary,
  },
  pendingBadge: {
    alignSelf: 'flex-start',
    color: COLORS.warning,
    fontSize: FONT_SIZE.xs,
    fontWeight: '700',
  },
  modalEmpty: {
    textAlign: 'center',
    color: COLORS.textHint,
    fontSize: FONT.bodySize,
  },
  modalEmptyBox: { padding: SPACING.xxl, gap: SPACING.md },
  requestMessage: { color: COLORS.textSecondary, fontSize: FONT_SIZE.sm, paddingHorizontal: SPACING.md, paddingTop: SPACING.sm },
});
