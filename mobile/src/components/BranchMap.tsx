import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import MapView, { Marker } from 'react-native-maps';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';
import { branchHasCoordinates, toNumber } from '@/utils/retail';
import type { SupermarketBranch } from '@/types/retail';

export function BranchMap({
  branches,
  selectedId,
  onSelect,
}: {
  branches: SupermarketBranch[];
  selectedId?: number | null;
  onSelect: (branch: SupermarketBranch) => void;
}) {
  const withCoords = branches.filter(branchHasCoordinates);
  if (withCoords.length === 0) {
    return (
      <View style={styles.empty}>
        <Text style={styles.emptyText}>No hay sucursales con coordenadas para mostrar en el mapa.</Text>
      </View>
    );
  }
  const selected = withCoords.find((branch) => branch.id === selectedId) ?? withCoords[0];
  const latitude = toNumber(selected.latitude) ?? 0;
  const longitude = toNumber(selected.longitude) ?? 0;

  return (
    <MapView
      style={styles.map}
      region={{ latitude, longitude, latitudeDelta: 0.06, longitudeDelta: 0.06 }}
    >
      {withCoords.map((branch) => {
        const lat = toNumber(branch.latitude);
        const lng = toNumber(branch.longitude);
        if (lat === null || lng === null) return null;
        return (
          <Marker
            key={branch.id}
            coordinate={{ latitude: lat, longitude: lng }}
            title={branch.name}
            description={branch.address || branch.chain?.name || undefined}
            pinColor={branch.id === selectedId ? COLORS.warning : COLORS.primary}
            onPress={() => onSelect(branch)}
          />
        );
      })}
    </MapView>
  );
}

const styles = StyleSheet.create({
  map: { height: 220, borderRadius: RADIUS.md, overflow: 'hidden' },
  empty: { minHeight: 120, borderRadius: RADIUS.md, backgroundColor: COLORS.surface, alignItems: 'center', justifyContent: 'center', padding: SPACING.md },
  emptyText: { fontSize: FONT.captionSize, color: COLORS.textSecondary, textAlign: 'center' },
});
