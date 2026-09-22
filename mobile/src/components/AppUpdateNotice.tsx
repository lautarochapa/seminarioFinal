import { useEffect } from 'react';
import { Alert, AppState, Linking, Platform } from 'react-native';
import Constants from 'expo-constants';
import * as Application from 'expo-application';
import { mobileReleaseApi } from '@/api/endpoints';
import { availableAndroidUpdate, type AndroidRelease } from '@/utils/appVersion';

export function AppUpdateNotice() {
  useEffect(() => {
    if (Platform.OS !== 'android' || Constants.executionEnvironment === 'storeClient'
      || !Application.nativeBuildVersion) return;

    let mounted = true;
    let displayed = false;
    let state = AppState.currentState;
    let update: AndroidRelease | null = null;

    function showUpdate() {
      if (!mounted || displayed || !update || state !== 'active') return;
      displayed = true;
      const release = update;
      const installed = Application.nativeApplicationVersion ?? Application.nativeBuildVersion;
      Alert.alert(
        'Actualización disponible',
        `Ya está disponible CocinaComidaControl ${release.version}. Tenés instalada la versión ${installed}. Podés actualizarla desde la página o continuar por ahora.`,
        [
          { text: 'Más tarde', style: 'cancel' },
          { text: 'Ir a descargar', onPress: () => {
            void Linking.openURL(release.download_page_url).catch(() => {
              if (mounted) Alert.alert('No se pudo abrir la página', `Podés ingresar desde tu navegador: ${release.download_page_url}`);
            });
          } },
        ],
        { cancelable: true },
      );
    }

    const subscription = AppState.addEventListener('change', (next) => {
      state = next;
      showUpdate();
    });
    void mobileReleaseApi.androidVersion()
      .then((response) => {
        update = availableAndroidUpdate(response.data, Application.nativeBuildVersion);
        showUpdate();
      })
      .catch(() => undefined); // An optional check must never prevent login or offline use.

    return () => { mounted = false; subscription.remove(); };
  }, []);

  return null;
}
