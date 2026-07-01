import React, { createContext, useCallback, useContext, useState } from 'react';
import { secureStorage } from '@/storage/secureStorage';
import type { FamilyGroup } from '@/types/familyGroup';

interface FamilyGroupContextValue {
  selectedGroup: FamilyGroup | null;
  selectGroup: (group: FamilyGroup) => void;
  clearGroup: () => void;
  restoreGroup: (groups: FamilyGroup[]) => Promise<void>;
}

const FamilyGroupContext = createContext<FamilyGroupContextValue | null>(null);

export function FamilyGroupProvider({ children }: { children: React.ReactNode }) {
  const [selectedGroup, setSelectedGroup] = useState<FamilyGroup | null>(null);

  const selectGroup = useCallback((group: FamilyGroup) => {
    setSelectedGroup(group);
    secureStorage.setSelectedGroupId(group.id).catch(() => { /* best-effort */ });
  }, []);

  const clearGroup = useCallback(() => {
    setSelectedGroup(null);
    secureStorage.removeSelectedGroupId().catch(() => { /* best-effort */ });
  }, []);

  // Call after loading the list of groups the user belongs to.
  // Restores the persisted group if still valid, or auto-selects when there is exactly one.
  const restoreGroup = useCallback(async (groups: FamilyGroup[]) => {
    if (groups.length === 0) {
      setSelectedGroup(null);
      return;
    }

    const storedId = await secureStorage.getSelectedGroupId();
    if (storedId !== null) {
      const match = groups.find((g) => g.id === storedId);
      if (match) {
        setSelectedGroup(match);
        return;
      }
      // Stored ID no longer valid
      await secureStorage.removeSelectedGroupId();
    }

    // Auto-select when exactly one group
    if (groups.length === 1) {
      setSelectedGroup(groups[0]);
      await secureStorage.setSelectedGroupId(groups[0].id);
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
