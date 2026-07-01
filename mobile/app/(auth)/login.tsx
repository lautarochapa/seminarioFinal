import { StatusBar } from 'expo-status-bar';
import { LoginScreen } from '@/screens/LoginScreen';

export default function LoginRoute() {
  return (
    <>
      <StatusBar style="dark" />
      <LoginScreen />
    </>
  );
}
