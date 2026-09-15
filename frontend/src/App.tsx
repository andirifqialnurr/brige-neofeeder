import { lazy, Suspense, useEffect } from 'react';
import { WorkspaceContext, useWorkspace } from '@/hooks/workspace-context';
import { useWorkspaceController } from '@/hooks/use-workspace-controller';
import { WorkspaceLayout } from '@/components/workspace-layout';
import { LoadingState, EmptyState, AppButton } from '@/components/ui';
import { FileQuestion, ArrowLeft } from 'lucide-react';
import { goTo, loginDestination } from '@/lib/router';
import { LandingScreen } from '@/pages/landing';
import { LoginScreen } from '@/pages/login';
const DashboardPage = lazy(() =>
  import('@/pages/dashboard').then((module) => ({ default: module.DashboardPage })),
);
import { CampusPage } from '@/pages/campus';
import { NeoFeederPage } from '@/pages/neo-feeder';
import { TemplatePage } from '@/pages/template-excel';
import { ImportBatchPage } from '@/pages/import-batch';
import { ImportBatchDetailPage } from '@/pages/import-batch/detail';
import { MappingPage } from '@/pages/mapping';
import { OperationsPage } from '@/pages/operations';

function Redirect({ to }: { to: string }) {
  useEffect(() => {
    goTo(to, true);
  }, [to]);
  return <LoadingState label="Membuka halaman" />;
}
function ApplicationRoutes() {
  const { route, authState, authUser } = useWorkspace();
  if (authState === 'checking')
    return (
      <main className="auth-loading-shell">
        <LoadingState label="Memeriksa sesi" />
      </main>
    );
  if (route.screen === 'redirect') return <Redirect to="/import-batch" />;
  if (route.screen === 'landing') return <LandingScreen />;
  if (route.screen === 'login')
    return authUser ? <Redirect to={loginDestination(location.search)} /> : <LoginScreen />;
  if (route.screen === 'not-found')
    return (
      <main className="auth-loading-shell">
        <EmptyState
          icon={FileQuestion}
          title="Halaman tidak ditemukan"
          action={
            <AppButton icon={ArrowLeft} onClick={() => goTo(authUser ? '/dashboard' : '/')}>
              Kembali
            </AppButton>
          }
        />
      </main>
    );
  if (!authUser) return <Redirect to={'/login?next=' + encodeURIComponent(route.url)} />;
  const pages = {
    dashboard: <DashboardPage />,
    campus: <CampusPage />,
    'neo-feeder': <NeoFeederPage />,
    'template-excel': <TemplatePage />,
    'import-batch': route.batchId ? (
      <ImportBatchDetailPage key={route.batchId} id={route.batchId} />
    ) : (
      <ImportBatchPage />
    ),
    mapping: <MappingPage />,
    operations: <OperationsPage />,
  };
  return (
    <WorkspaceLayout>
      <Suspense fallback={<LoadingState label="Memuat halaman" />}>{pages[route.page]}</Suspense>
    </WorkspaceLayout>
  );
}
export default function App() {
  const workspace = useWorkspaceController();
  return (
    <WorkspaceContext.Provider value={workspace}>
      <ApplicationRoutes />
    </WorkspaceContext.Provider>
  );
}
