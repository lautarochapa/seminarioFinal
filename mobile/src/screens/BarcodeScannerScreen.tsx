import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Modal, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { BarcodeScanner } from '@/components/BarcodeScanner';
import { BarcodeResult } from '@/components/BarcodeResult';
import { productsApi, stockApi, stockLocationsApi, unitsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { useOptionalFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { setPendingScanResult } from '@/utils/barcodeScanResult';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, FONT, RADIUS, SPACING, TOUCH_TARGET } from '@/utils/theme';
import type { ProductDetail } from '@/types/product';
import type { StockLocation } from '@/types/stock';
import type { Unit } from '@/types/unit';

export function BarcodeScannerScreen() {
  const router = useRouter();
  const familyGroupContext = useOptionalFamilyGroupContext();
  const groupId = familyGroupContext?.selectedGroup?.id ?? null;
  const [lastCode, setLastCode] = useState<string | null>(null);
  const [product, setProduct] = useState<ProductDetail | null>(null);
  const [notFound, setNotFound] = useState(false);
  const [loading, setLoading] = useState(false);
  const [manualVisible, setManualVisible] = useState(false);
  const [manualCode, setManualCode] = useState('');
  const [manualProductVisible, setManualProductVisible] = useState(false);
  const [manualName, setManualName] = useState('');
  const [manualQuantity, setManualQuantity] = useState('1');
  const [manualUnitId, setManualUnitId] = useState<number | null>(null);
  const [knownStockVisible, setKnownStockVisible] = useState(false);
  const [knownQuantity, setKnownQuantity] = useState('1');
  const [knownUnitId, setKnownUnitId] = useState<number | null>(null);
  const [knownLocationId, setKnownLocationId] = useState<number | null>(null);
  const [knownExpiration, setKnownExpiration] = useState('');
  const [locations, setLocations] = useState<StockLocation[]>([]);
  const [addingKnownStock, setAddingKnownStock] = useState(false);
  const [units, setUnits] = useState<Unit[]>([]);
  const [requestingProduct, setRequestingProduct] = useState(false);

  useEffect(() => {
    if (!unitsApi || typeof unitsApi.list !== 'function') {
      return;
    }

    unitsApi.list()
      .then((res) => {
        const loaded = res.data || [];
        setUnits(loaded);
      })
      .catch(() => setUnits([]));
  }, []);

  useEffect(() => {
    if (!groupId) {
      return;
    }

    stockLocationsApi.list(groupId)
      .then((res) => {
        const loaded = res.data || [];
        setLocations(loaded);
        setKnownLocationId(loaded[0]?.id ?? null);
      })
      .catch(() => setLocations([]));
  }, [groupId]);

  async function lookup(code: string) {
    setLoading(true);
    setProduct(null);
    setNotFound(false);
    setLastCode(code);
    try {
      const res = groupId
        ? await productsApi.findByBarcode(code, groupId)
        : await productsApi.findByBarcode(code);
      setProduct(res.data);
      setKnownUnitId(res.data.stock_entry_suggestion?.unit_id ?? null);
    } catch (err) {
      if (err instanceof ApiError && err.normalized.status === 404) {
        setNotFound(true);
      } else {
        setNotFound(true);
      }
    } finally {
      setLoading(false);
    }
  }

  function handleUseProduct() {
    if (!product || !lastCode) return;
    setPendingScanResult({ productId: product.id, productName: product.name, barcode: lastCode });
    router.back();
  }

  function handleScanAgain() {
    setProduct(null);
    setNotFound(false);
    setLastCode(null);
  }

  function openManualProduct() {
    if (!lastCode) return;
    setManualName(`Producto ${lastCode}`);
    setManualQuantity('1');
    setManualProductVisible(true);
  }

  function openKnownStock() {
    if (!product) return;
    if (!groupId) {
      Alert.alert('Selecciona un grupo', 'Necesitas un grupo familiar activo para cargar stock.');
      return;
    }
    setKnownExpiration('');
    setKnownQuantity(product.stock_entry_suggestion?.quantity != null ? String(product.stock_entry_suggestion.quantity) : '');
    setKnownUnitId(product.stock_entry_suggestion?.unit_id ?? null);
    setKnownLocationId(knownLocationId ?? locations[0]?.id ?? null);
    setKnownStockVisible(true);
  }

  async function submitKnownStock() {
    if (!lastCode || !groupId || addingKnownStock) return;
    if (!knownLocationId || !knownUnitId || !knownQuantity || Number(knownQuantity) <= 0) return;

    setAddingKnownStock(true);
    try {
      await stockApi.scan(groupId, {
        barcode: lastCode,
        stock_location_id: knownLocationId,
        quantity: Number(knownQuantity),
        unit_id: knownUnitId,
        expiration_date: knownExpiration.trim() || null,
      });
      setKnownStockVisible(false);
      Alert.alert('Stock actualizado', 'El producto quedo cargado en tu stock.');
      router.replace('/(app)/stock' as never);
    } catch (err) {
      const msg = err instanceof ApiError ? err.normalized.message : 'No se pudo agregar al stock.';
      Alert.alert('No se pudo cargar', msg);
    } finally {
      setAddingKnownStock(false);
    }
  }

  function handleKnownStockSubmit() {
    if (!knownUnitId) return;
    const existingUnits = product?.stock_entry_suggestion?.existing_units ?? [];
    const differs = existingUnits.length > 0 && !existingUnits.some((unit) => unit.id === knownUnitId);
    if (differs) {
      Alert.alert(
        'Se creará un lote separado',
        `Ya tenés este producto cargado en ${unitNames(existingUnits)}. Si elegís otra unidad se creará un lote separado.`,
        [
          { text: 'Cancelar', style: 'cancel' },
          { text: 'Continuar', onPress: () => { void submitKnownStock(); } },
        ],
      );
      return;
    }
    void submitKnownStock();
  }

  async function handleManualProductSubmit() {
    if (!lastCode || requestingProduct) return;
    if (!groupId) {
      Alert.alert('Selecciona un grupo', 'Necesitas un grupo familiar activo para cargar stock.');
      return;
    }
    if (!manualName.trim() || !manualUnitId || !manualQuantity || Number(manualQuantity) <= 0) return;
    setRequestingProduct(true);
    try {
      await stockApi.createManualProduct(groupId, {
        product: {
          name: manualName.trim(),
          barcode: lastCode,
          unit_id: manualUnitId,
        },
        stock: {
          quantity: Number(manualQuantity),
          unit_id: manualUnitId,
        },
      });
      setManualProductVisible(false);
      Alert.alert('Producto cargado', 'Quedo en tu stock y pendiente de revision del catalogo.');
      router.replace('/(app)/stock' as never);
    } catch {
      Alert.alert('No se pudo cargar', 'Intenta nuevamente en unos minutos.');
      setNotFound(true);
    } finally {
      setRequestingProduct(false);
    }
  }

  function handleManualSubmit() {
    const code = manualCode.trim();
    if (!code) return;
    setManualVisible(false);
    setManualCode('');
    lookup(code);
  }

  const showingResult = Boolean(lastCode) && (product !== null || notFound) && !loading;

  return (
    <View style={styles.fill}>
      <AppHeader title="Escanear código" showBack onBack={goBackOrHome} />
      {showingResult ? (
        <View style={styles.resultWrap}>
          <BarcodeResult
            barcode={lastCode ?? ''}
            product={product}
            notFound={notFound}
            stockStatus={product ? stockStatus(product) : null}
            onAddToStock={product ? openKnownStock : undefined}
            onUseProduct={product ? handleUseProduct : undefined}
            onRequestProduct={notFound && !requestingProduct ? openManualProduct : undefined}
            onScanAgain={handleScanAgain}
          />
        </View>
      ) : (
        <BarcodeScanner
          active={!loading}
          onScanned={lookup}
          onManualEntry={() => setManualVisible(true)}
        />
      )}
      {loading ? (
        <View style={styles.loadingOverlay}>
          <ActivityIndicator size="large" color={COLORS.primary} />
        </View>
      ) : null}

      <Modal visible={manualVisible} transparent animationType="fade" onRequestClose={() => setManualVisible(false)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Ingresar código manualmente</Text>
            <TextInput
              style={styles.modalInput}
              value={manualCode}
              onChangeText={setManualCode}
              placeholder="Código de barras"
              placeholderTextColor={COLORS.textHint}
              keyboardType="numeric"
              autoFocus
              accessibilityLabel="Código de barras"
            />
            <View style={styles.modalActions}>
              <Pressable
                onPress={() => { setManualVisible(false); setManualCode(''); }}
                accessibilityRole="button"
                style={styles.modalCancelBtn}
              >
                <Text style={styles.modalCancelText}>Cancelar</Text>
              </Pressable>
              <AppButton title="Buscar" onPress={handleManualSubmit} disabled={!manualCode.trim()} />
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={manualProductVisible} transparent animationType="fade" onRequestClose={() => setManualProductVisible(false)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Crear producto rapido</Text>
            <Text style={styles.modalHint}>Se carga ahora en tu stock y queda pendiente de revision.</Text>
            <TextInput
              style={styles.modalInput}
              value={manualName}
              onChangeText={setManualName}
              placeholder="Nombre del producto"
              placeholderTextColor={COLORS.textHint}
              accessibilityLabel="Nombre del producto"
            />
            <TextInput
              style={styles.modalInput}
              value={manualQuantity}
              onChangeText={setManualQuantity}
              placeholder="Cantidad"
              placeholderTextColor={COLORS.textHint}
              keyboardType="decimal-pad"
              accessibilityLabel="Cantidad"
            />
            <View style={styles.unitWrap}>
              {units.map((unit) => (
                <Pressable
                  key={unit.id}
                  onPress={() => setManualUnitId(unit.id)}
                  accessibilityRole="button"
                  style={[styles.unitChip, manualUnitId === unit.id && styles.unitChipSelected]}
                >
                  <Text style={[styles.unitChipText, manualUnitId === unit.id && styles.unitChipTextSelected]}>
                    {unit.symbol || unit.code || unit.name}
                  </Text>
                </Pressable>
              ))}
            </View>
            <View style={styles.modalActions}>
              <Pressable
                onPress={() => setManualProductVisible(false)}
                accessibilityRole="button"
                style={styles.modalCancelBtn}
              >
                <Text style={styles.modalCancelText}>Cancelar</Text>
              </Pressable>
              <AppButton
                title="Cargar"
                onPress={handleManualProductSubmit}
                loading={requestingProduct}
                disabled={!manualName.trim() || !manualUnitId || !manualQuantity || Number(manualQuantity) <= 0}
              />
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={knownStockVisible} transparent animationType="fade" onRequestClose={() => setKnownStockVisible(false)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Agregar al stock</Text>
            <Text style={styles.modalHint}>{product?.name}</Text>
            <TextInput
              style={styles.modalInput}
              value={knownQuantity}
              onChangeText={setKnownQuantity}
              placeholder="Cantidad"
              placeholderTextColor={COLORS.textHint}
              keyboardType="decimal-pad"
              accessibilityLabel="Cantidad"
            />
            <TextInput
              style={styles.modalInput}
              value={knownExpiration}
              onChangeText={setKnownExpiration}
              placeholder="Vencimiento (YYYY-MM-DD, opcional)"
              placeholderTextColor={COLORS.textHint}
              autoCapitalize="none"
              accessibilityLabel="Fecha de vencimiento"
            />
            <View style={styles.unitWrap}>
              {locations.map((location) => (
                <Pressable
                  key={location.id}
                  onPress={() => setKnownLocationId(location.id)}
                  accessibilityRole="button"
                  style={[styles.unitChip, knownLocationId === location.id && styles.unitChipSelected]}
                >
                  <Text style={[styles.unitChipText, knownLocationId === location.id && styles.unitChipTextSelected]}>
                    {location.name}
                  </Text>
                </Pressable>
              ))}
            </View>
            <View style={styles.unitWrap}>
              {units.map((unit) => (
                <Pressable
                  key={unit.id}
                  onPress={() => setKnownUnitId(unit.id)}
                  accessibilityRole="button"
                  style={[styles.unitChip, knownUnitId === unit.id && styles.unitChipSelected]}
                >
                  <Text style={[styles.unitChipText, knownUnitId === unit.id && styles.unitChipTextSelected]}>
                    {unit.symbol || unit.code || unit.name}
                  </Text>
                </Pressable>
              ))}
            </View>
            {product?.stock_entry_suggestion?.source === 'existing_stock' ? (
              <Text style={styles.modalHint}>Unidad usada actualmente: {unitNames(product.stock_entry_suggestion.existing_units)}.</Text>
            ) : product?.stock_entry_suggestion?.source === 'package' ? (
              <Text style={styles.modalHint}>Cantidad y unidad tomadas de la presentación del producto.</Text>
            ) : !knownUnitId ? (
              <Text style={styles.warningText}>Este producto no tiene una unidad conocida. Elegí una antes de guardar.</Text>
            ) : null}
            {knownUnitId && (product?.stock_entry_suggestion?.existing_units ?? []).length > 0 &&
              !(product?.stock_entry_suggestion?.existing_units ?? []).some((unit) => unit.id === knownUnitId) ? (
                <Text style={styles.warningText}>
                  Ya tenés este producto cargado en {unitNames(product?.stock_entry_suggestion?.existing_units ?? [])}. Si elegís otra unidad se creará un lote separado.
                </Text>
              ) : null}
            <View style={styles.modalActions}>
              <Pressable
                onPress={() => setKnownStockVisible(false)}
                accessibilityRole="button"
                style={styles.modalCancelBtn}
              >
                <Text style={styles.modalCancelText}>Cancelar</Text>
              </Pressable>
              <AppButton
                title="Agregar"
                onPress={handleKnownStockSubmit}
                loading={addingKnownStock}
                disabled={!knownLocationId || !knownUnitId || !knownQuantity || Number(knownQuantity) <= 0}
              />
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

function stockStatus(product: ProductDetail): string {
  const summary = product.stock_summary;
  if (!summary || !summary.in_stock) {
    return 'No esta en tu stock.';
  }
  return summary.items_count === 1 ? 'Ya tenes 1 item en stock.' : `Ya tenes ${summary.items_count} items en stock.`;
}

function unitNames(units: Array<{ name: string; symbol?: string; code?: string }>): string {
  return units.map((unit) => unit.name || unit.symbol || unit.code || '').filter(Boolean).join(', ');
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  resultWrap: { flex: 1, padding: SPACING.md, justifyContent: 'center' },
  loadingOverlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0,0,0,0.3)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', alignItems: 'center', justifyContent: 'center', padding: SPACING.lg },
  modalCard: { width: '100%', backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.lg, gap: SPACING.md },
  modalTitle: { fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary },
  modalHint: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  warningText: { fontSize: FONT.captionSize, color: COLORS.warning, fontWeight: '600' },
  modalInput: {
    minHeight: TOUCH_TARGET,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.sm,
    paddingHorizontal: SPACING.md,
    fontSize: FONT.bodySize,
    color: COLORS.textPrimary,
  },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center', gap: SPACING.md },
  modalCancelBtn: { paddingHorizontal: SPACING.md, paddingVertical: SPACING.sm, minHeight: TOUCH_TARGET, justifyContent: 'center' },
  modalCancelText: { color: COLORS.textSecondary, fontWeight: '700', fontSize: FONT.bodySize },
  unitWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: SPACING.xs },
  unitChip: { borderWidth: 1, borderColor: COLORS.border, borderRadius: RADIUS.full, paddingHorizontal: SPACING.sm, paddingVertical: SPACING.xs },
  unitChipSelected: { borderColor: COLORS.primary, backgroundColor: COLORS.primarySurface },
  unitChipText: { color: COLORS.textSecondary, fontWeight: '600' },
  unitChipTextSelected: { color: COLORS.primary },
});
