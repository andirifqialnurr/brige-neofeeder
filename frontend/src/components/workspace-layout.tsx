import { CircleUserRound, Database, LogOut, Menu, X } from 'lucide-react';

import { AppShell, Brand, IconButton, Sidebar, SidebarNav, Topbar } from '@/components/ui';

import type { ReactNode } from 'react';
import type { PageId } from '@/lib/router';
import { navItems } from '@/hooks/use-workspace-controller';
import { WorkspaceDialogs } from './workspace-dialogs';
import { useWorkspace } from '@/hooks/workspace-context';
export function WorkspaceLayout({ children }: { children: ReactNode }) {
  const {
    theme,
    setTheme,
    nextTheme,
    ThemeIcon,
    authUser,
    activePage,
    mobileNavOpen,
    setMobileNavOpen,
    feedback,
    navigate,
    handleLogout,
  } = useWorkspace();
  const themeControl = (
    <IconButton
      label={theme === 'dark' ? 'Gunakan tema terang' : 'Gunakan tema gelap'}
      icon={ThemeIcon}
      onClick={() => setTheme(nextTheme)}
    />
  );

  return (
    <>
      <a className="skip-link" href="#main-content">
        Ke konten utama
      </a>
      <div className="mobile-topbar">
        <Brand icon={Database} title="NeoBridge" subtitle="Workspace" />
        <button
          className="icon-button"
          aria-label={mobileNavOpen ? 'Tutup navigasi' : 'Buka navigasi'}
          aria-expanded={mobileNavOpen}
          aria-controls="app-navigation"
          onClick={() => setMobileNavOpen(!mobileNavOpen)}
          type="button"
        >
          {mobileNavOpen ? <X size={20} /> : <Menu size={20} />}
        </button>
      </div>
      <div className={mobileNavOpen ? 'app-layout nav-open' : 'app-layout'}>
        <AppShell
          sidebar={
            <Sidebar>
              <Brand icon={Database} title="NeoBridge" subtitle="Bridge Neo Feeder" />
              <div id="app-navigation">
                <SidebarNav
                  activeItem={activePage}
                  items={navItems.filter(
                    (item) => item.id !== 'operations' || authUser?.role === 'admin',
                  )}
                  onItemSelect={(item) => navigate(item.id as PageId)}
                />
              </div>
              <span className="sidebar-footer">Workspace kampus</span>
            </Sidebar>
          }
        >
          <Topbar
            action={
              <div className="topbar-actions">
                <span className="user-identity">
                  <CircleUserRound size={18} />
                  <span>{authUser?.name}</span>
                </span>
                {themeControl}
                <IconButton label="Keluar" icon={LogOut} onClick={handleLogout} />
              </div>
            }
          />
          <div className="page-content">
            {feedback ? (
              <p className="success-state" role="status">
                {feedback}
              </p>
            ) : null}
            {children}
          </div>
        </AppShell>
      </div>
      <WorkspaceDialogs />
    </>
  );
}
