import { useNavigate, useLocation, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { Button } from '../components/ui/button';
import {
  LogOut,
  Home,
  Users,
  BookOpen,
  Calendar,
  ClipboardList,
  BarChart,
  Briefcase,
  MessageSquare,
  Bell,
  UserCircle,
} from 'lucide-react';
import { cn } from '../lib/utils';
import { useTranslation } from 'react-i18next';

const navItems: { label: string; icon: typeof Home; href: string; roles?: string[] }[] = [
  { label: 'Tableau de bord', icon: Home, href: '/dashboard' },
  { label: 'Utilisateurs', icon: Users, href: '/users', roles: ['admin'] },
  { label: 'Années scolaires', icon: Calendar, href: '/academic/years', roles: ['admin'] },
  { label: 'Filières', icon: BookOpen, href: '/academic/filieres', roles: ['admin'] },
  { label: 'Affectation formateurs', icon: ClipboardList, href: '/formateur-assignments', roles: ['admin'] },
  { label: 'Groupes', icon: Users, href: '/groups' },
  { label: 'Modules', icon: BookOpen, href: '/modules' },
  { label: 'Affectations', icon: ClipboardList, href: '/affectations', roles: ['admin', 'formateur'] },
  { label: 'Présences', icon: ClipboardList, href: '/attendance' },
  { label: 'Emploi du temps', icon: Calendar, href: '/timetable', roles: ['admin', 'formateur', 'stagiaire'] },
  { label: 'Progression', icon: BarChart, href: '/progress' },
  { label: 'Évaluations', icon: BarChart, href: '/evaluations' },
  { label: 'Stages', icon: Briefcase, href: '/stages' },
  { label: 'Avis (anonyme)', icon: MessageSquare, href: '/feedback' },
  { label: 'Notifications', icon: Bell, href: '/notifications' },
  { label: 'Mes enfants', icon: UserCircle, href: '/parent/children', roles: ['parent'] },
];

export default function DashboardLayout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { t } = useTranslation();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const canSee = (roles?: string[]) => {
    if (!roles) return true;
    if (!user?.role) return false;
    if (user.role === 'admin') return true;
    return roles.includes(user.role);
  };

  const visibleNav = navItems.filter((item) => canSee(item.roles));

  return (
    <div className="flex h-screen bg-gray-50">
      <aside className="w-64 bg-white border-r hidden md:flex flex-col">
        <div className="p-6 border-b">
          <h1 className="text-2xl font-bold text-primary-600">{t('app.title')}</h1>
          <p className="text-xs text-gray-400">{t('app.subtitle')}</p>
        </div>
        <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
          {visibleNav.map((item) => (
            <button
              key={item.href}
              type="button"
              className={cn(
                'w-full flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors text-left',
                location.pathname === item.href || location.pathname.startsWith(item.href + '/')
                  ? 'bg-primary-50 text-primary-700'
                  : 'text-gray-700 hover:bg-gray-100'
              )}
              onClick={() => navigate(item.href)}
            >
              <item.icon className="w-5 h-5 mr-3 shrink-0" />
              {item.label}
            </button>
          ))}
        </nav>
        <div className="p-4 border-t">
          <div className="flex items-center mb-4">
            <div className="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold shrink-0">
              {user?.name?.charAt(0) ?? '?'}
            </div>
            <div className="ml-3 min-w-0">
              <p className="text-sm font-medium text-gray-900 truncate">{user?.name}</p>
              <p className="text-xs text-gray-500 capitalize">{user?.role}</p>
            </div>
          </div>
          <Button
            variant="outline"
            className="w-full justify-start text-red-600 hover:text-red-700 hover:bg-red-50"
            onClick={handleLogout}
          >
            <LogOut className="w-4 h-4 mr-2" />
            {t('auth.logout')}
          </Button>
        </div>
      </aside>

      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="bg-white shadow-sm md:hidden p-4 flex items-center justify-between">
          <h1 className="font-bold text-primary-600">{t('app.title')}</h1>
          <Button size="sm" variant="ghost" onClick={handleLogout}>
            <LogOut className="w-4 h-4" />
          </Button>
        </header>
        <main className="flex-1 overflow-y-auto p-6"><Outlet /></main>
      </div>
    </div>
  );
}
