import { useCallback, useState } from 'react';
import * as Location from 'expo-location';
import { branchesApi } from '@/api/endpoints';
import { normalizeError } from '@/hooks/useRetailList';
import type { NormalizedError } from '@/types/api';
import type { SupermarketBranch } from '@/types/retail';

interface NearbyState {
  data: SupermarketBranch[];
  loading: boolean;
  error: NormalizedError | null;
  permissionStatus: string | null;
  requestNearby: (radius?: number) => Promise<void>;
}

function locationError(message: string, code = 'LOCATION_UNAVAILABLE'): NormalizedError {
  return {
    status: 0,
    code,
    message,
    fieldErrors: {},
    traceId: '',
    isNetworkError: false,
    isTimeoutError: code === 'LOCATION_TIMEOUT',
  };
}

export function useNearbyBranches(): NearbyState {
  const [data, setData] = useState<SupermarketBranch[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);
  const [permissionStatus, setPermissionStatus] = useState<string | null>(null);

  const requestNearby = useCallback(async (radius = 5) => {
    setLoading(true);
    setError(null);
    try {
      const servicesEnabled = await Location.hasServicesEnabledAsync();
      if (!servicesEnabled) {
        setError(locationError('La ubicacion del dispositivo esta apagada.', 'LOCATION_SERVICES_DISABLED'));
        return;
      }
      const permission = await Location.requestForegroundPermissionsAsync();
      setPermissionStatus(permission.status);
      if (permission.status !== Location.PermissionStatus.GRANTED) {
        setError(locationError(
          permission.canAskAgain ? 'Permiso de ubicacion denegado.' : 'Permiso de ubicacion bloqueado. Revisalo en ajustes.',
          permission.canAskAgain ? 'LOCATION_PERMISSION_DENIED' : 'LOCATION_PERMISSION_BLOCKED',
        ));
        return;
      }
      const timeout = new Promise<never>((_, reject) => {
        setTimeout(() => reject(new Error('LOCATION_TIMEOUT')), 10000);
      });
      const current = await Promise.race([
        Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced }),
        timeout,
      ]);
      const res = await branchesApi.nearby({
        lat: current.coords.latitude,
        lng: current.coords.longitude,
        radius,
      });
      setData(res.data);
    } catch (err: unknown) {
      if (err instanceof Error && err.message === 'LOCATION_TIMEOUT') {
        setError(locationError('No se pudo obtener la ubicacion a tiempo.', 'LOCATION_TIMEOUT'));
      } else {
        setError(normalizeError(err));
      }
    } finally {
      setLoading(false);
    }
  }, []);

  return { data, loading, error, permissionStatus, requestNearby };
}
