import React from 'react';
import { render } from '@testing-library/react-native';
import { LoginScreen } from '../src/screens/LoginScreen';
import { RegisterScreen } from '../src/screens/RegisterScreen';

jest.mock('../src/auth/AuthContext', () => ({
  useAuth: () => ({ login: jest.fn(), register: jest.fn(), isLoading: false }),
}));
jest.mock('../src/auth/FamilyGroupContext', () => ({
  useFamilyGroupContext: () => ({ restoreGroup: jest.fn() }),
}));
jest.mock('../src/api/endpoints', () => ({ familyGroupsApi: {} }));
jest.mock('expo-router', () => ({ useRouter: () => ({ push: jest.fn() }) }));
jest.mock('@expo/vector-icons', () => ({ MaterialCommunityIcons: 'MaterialCommunityIcons' }));

it.each([['login', LoginScreen], ['register', RegisterScreen]] as const)(
  '%s offers email/password without social sign-in or demo accounts',
  async (_name, Screen) => {
    const screen = await render(<Screen />);
    expect(screen.getByLabelText('Email')).toBeTruthy();
    expect(screen.getByLabelText('Contrase\u00f1a')).toBeTruthy();
    expect(screen.queryByText(/google|facebook|usuarios demo|@cccontrol\.test/i)).toBeNull();
  },
);

it('keeps preview an installable HTTPS release with demo accounts hidden', () => {
  const { build, cli } = require('../eas.json');
  const { expo } = require('../app.json');
  const pkg = require('../package.json');
  const lock = require('../package-lock.json');
  expect(build.preview.distribution).toBe('internal');
  expect(build.preview.developmentClient).not.toBe(true);
  expect(build.preview.android.buildType).toBe('apk');
  for (const profile of [build.preview, build.production]) {
    expect(profile.env.EXPO_PUBLIC_API_URL).toBe('https://cocinacomidacontrol.onrender.com');
    expect(profile.env.EXPO_PUBLIC_SHOW_DEMO_USERS).toBe('false');
    expect(profile.env.EXPO_PUBLIC_ALLOW_INSECURE_API).toBe('false');
  }
  expect(cli.appVersionSource).toBe('local');
  expect(expo.android.package).toBe('com.cccontrol.mobile');
  expect(expo.version).toBe('1.0.8');
  expect(expo.android.versionCode).toBe(9);
  expect(pkg.version).toBe(expo.version);
  expect(lock.version).toBe(expo.version);
  expect(lock.packages[''].version).toBe(expo.version);
});
