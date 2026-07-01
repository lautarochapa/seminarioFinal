import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { AppInput } from '@/components/AppInput';
import { FormError } from '@/components/FormError';
import { LoadingScreen } from '@/components/LoadingScreen';
import { ErrorState } from '@/components/ErrorState';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useStockLocations } from '@/hooks/useStockLocations';
import { useStock } from '@/hooks/useStock';
import { stockApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, FONT_SIZE, RADIUS, SPACING } from '@/utils/theme';
import type { StockLocation } from '@/types/stock';

interface StockItemEditScreenProps {
  stockItemId: number;
}

export function StockItemEditScreen({ stockItemId }: StockItemEditScreenProps) {
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { data, loading: loadingList, error: listError, refresh } = useStock(groupId);
  const { data: locations, loading: loadingLocs } = useStockLocations(groupId);

  const item = data.find((i) => i.id === stockItemId) ?? null;

  const [locationId, setLocationId] = useState<number | null>(null);
  const [quantity, setQuantity] = useState('');
  const [expirationDate, setExpirationDate] = useState('');
  const [purchasePrice, setPurchasePrice] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [initialized, setInitialized] = useState(false);

  useEffect(() => {
    if (item && !initialized) {
      /* eslint-disable react-hooks/set-state-in-effect */
      setQuantity(String(item.quantity));
      setLocationId(item.stock_location_id);
      setExpirationDate(item.expiration_date ?? '');
      setPurchasePrice(item.purchase_price != null ? String(item.purchase_price) : '');
      setInitialized(true);
      /* eslint-enable react-hooks/set-state-in-effect */
    }
  }, [item, initialized]);

  if (loadingList && !item) return <LoadingScreen message="Cargando..." />;
  if (listError && !item) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Editar stock" showBack onBack={goBackOrHome} />
        <ErrorState message={friendlyMessage(listError)} traceId={listError.traceId} onRetry={refresh} type="server" />
      </View>
    );
  }
  if (!item) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Editar stock" showBack onBack={goBackOrHome} />
        <ErrorState message="Item no encontrado." onRetry={refresh} type="generic" />
      </View>
    );
  }

  async function handleSubmit() {
    const errors: Record<string, string> = {};
    if (!quantity || isNaN(Number(quantity)) || Number(quantity) < 0) {
      errors.quantity = 'Ingresá una cantidad válida.';
    }
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      return;
    }
    if (!groupId || !item) return;

    setSubmitting(true);
    setSubmitError(null);
    setFieldErrors({});

    try {
      await stockApi.update(groupId, item.id, {
        stock_location_id: locationId,
        quantity: Number(quantity),
        expiration_date: expirationDate || null,
        purchase_price: purchasePrice ? Number(purchasePrice) : null,
      });
      goBackOrHome();
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.normalized.status === 422 && Object.keys(err.normalized.fieldErrors).length > 0) {
          const fe: Record<string, string> = {};
          Object.entries(err.normalized.fieldErrors).forEach(([k, v]) => {
            fe[k] = v[0] ?? '';
          });
          setFieldErrors(fe);
        } else if (err.normalized.status === 403) {
          setSubmitError('Sin permiso para editar este item.');
        } else if (err.normalized.status === 404) {
          setSubmitError('Item no encontrado.');
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

  return (
    <View style={styles.fill}>
      <AppHeader
        title="Editar stock"
        subtitle={item.product?.name}
        showBack
        onBack={goBackOrHome}
      />
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

          {/* Producto (read-only) */}
          <View style={styles.readonlyField}>
            <Text style={styles.label}>Producto</Text>
            <Text style={styles.readonlyValue}>
              {item.product?.name ?? `Producto #${item.product_id}`}
            </Text>
          </View>

          {/* Ubicación */}
          <View style={styles.field}>
            <Text style={styles.label}>Ubicación</Text>
            {loadingLocs ? (
              <ActivityIndicator size="small" color={COLORS.primary} />
            ) : (
              <View style={styles.chipRow}>
                <Pressable
                  style={[styles.locationChip, locationId === null && styles.locationChipSelected]}
                  onPress={() => setLocationId(null)}
                  accessibilityRole="button"
                >
                  <Text style={[styles.chipText, locationId === null && styles.chipTextSelected]}>Sin ubicación</Text>
                </Pressable>
                {locations.map((loc: StockLocation) => (
                  <Pressable
                    key={loc.id}
                    style={[styles.locationChip, locationId === loc.id && styles.locationChipSelected]}
                    onPress={() => setLocationId(loc.id)}
                    accessibilityRole="button"
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
            title="Guardar cambios"
            onPress={handleSubmit}
            loading={submitting}
            fullWidth
            style={styles.submitBtn}
          />
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  scroll: { flex: 1, backgroundColor: COLORS.background },
  content: { padding: SPACING.md, gap: SPACING.md, paddingBottom: SPACING.xxl },
  field: { gap: SPACING.xs },
  label: {
    fontSize: FONT.labelSize,
    fontWeight: FONT.labelWeight,
    color: COLORS.textSecondary,
  },
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
});
