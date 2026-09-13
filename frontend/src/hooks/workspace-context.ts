import { createContext, useContext } from 'react';
import type { useWorkspaceController } from './use-workspace-controller';
export const WorkspaceContext = createContext<ReturnType<typeof useWorkspaceController> | null>(
  null,
);
export function useWorkspace() {
  const value = useContext(WorkspaceContext);
  if (!value) throw new Error('Workspace provider is required.');
  return value;
}
