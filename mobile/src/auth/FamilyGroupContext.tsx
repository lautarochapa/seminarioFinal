import React, { createContext, useCallback, useContext, useRef, useState } from 'react';
import { secureStorage } from '@/storage/secureStorage';
import type { FamilyGroup } from '@/types/familyGroup';

interface FamilyGroupContextValue {
  selectedGroup: FamilyGroup | null;
  selectGroup: (group: FamilyGroup) => void;
  clearGroup: () => void;
  restoreGroup: (groups: FamilyGroup[], isCurrent?: () => boolean) => Promise<void>;
}

const FamilyGroupContext = createContext<FamilyGroupContextValue | null>(null);

export function FamilyGroupProvider({ children }: { children: React.ReactNode }) {
  const [selectedGroup, setSelectedGroup] = useState<FamilyGroup | null>(null);
  const selectionVersion = useRef(0);

  const selectGroup = useCallback((group: FamilyGroup) => {
    selectionVersion.current += 1;
    if (selectedGroup?.id !== group.id) {
      // Group-scoped responses must never survive a context switch.
      // Fire-and-forget keeps selection responsive; cache is best-effort.
      import('@/storage/offlineCache').then(({ offlineCache }) => offlineCache.clearAll()).catch(() => undefined);
    }
    setSelectedGroup(group);
    secureStorage.setSelectedGroupId(group.id).catch(() => { /* best-effort */ });
  }, [selectedGroup?.id]);

  const clearGroup = useCallback(() => {
    selectionVersion.current += 1;
    setSelectedGroup(null);
    secureStorage.removeSelectedGroupId().catch(() => { /* best-effort */ });
  }, []);

  // Call after loading the list of groups the user belongs to.
  // Restores the persisted group if still valid, or auto-selects when there is exactly one.
  const restoreGroup = useCallback(async (groups: FamilyGroup[], isCurrent: () => boolean = () => true) => {
    const version = ++selectionVersion.current;
    // A delayed storage read must not undo logout or a newer manual selection.
    const canRestore = () => isCurrent() && version === selectionVersion.current;
    if (!canRestore()) return;
    if (groups.length === 0) {
      setSelectedGroup(null);
      return;
    }

    const storedId = await secureStorage.getSelectedGroupId();
    if (!canRestore()) return;
    if (storedId !== null) {
      const match = groups.find((g) => g.id === storedId);
      if (match) {
        setSelectedGroup(match);
        return;
      }
      // Stored ID no longer valid
      await secureStorage.removeSelectedGroupId();
      if (!canRestore()) return;
    }

    // Auto-select when exactly one group
    if (groups.length === 1) {
      setSelectedGroup(groups[0]);
      await secureStorage.setSelectedGroupId(groups[0].id);
    } else {
      setSelectedGroup(null);
    }
  }, []);

  return (
    <FamilyGroupContext.Provider value={{ selectedGroup, selectGroup, clearGroup, restoreGroup }}>
      {children}
    </FamilyGroupContext.Provider>
  );
}

export function useFamilyGroupContext(): FamilyGroupContextValue {
  const ctx = useContext(FamilyGroupContext);
  if (!ctx) throw new Error('useFamilyGroupContext must be used inside FamilyGroupProvider');
  return ctx;
}

export function useOptionalFamilyGroupContext(): FamilyGroupContextValue | null {
  return useContext(FamilyGroupContext);
}
