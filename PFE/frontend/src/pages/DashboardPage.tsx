import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { BookOpen, Calendar, Users, ClipboardList } from 'lucide-react';

export default function DashboardPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { user } = useAuth();
  const roleLabel = user?.role === 'admin' ? 'Directeur / Secrétariat' : user?.role;

  const cards = [
    { href: '/academic/years', icon: Calendar, title: t('nav.years'), desc: 'Années scolaires' },
    { href: '/groups', icon: Users, title: t('nav.groups'), desc: 'Groupes et classes' },
    { href: '/modules', icon: BookOpen, title: t('nav.modules'), desc: 'Modules et syllabus' },
    { href: '/attendance', icon: ClipboardList, title: t('nav.attendance'), desc: 'Séances et présences' },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">{t('nav.dashboard')} — GIMS</h1>
        <p className="text-gray-600 mt-1">Bienvenue, {user?.name}. Rôle : {roleLabel}</p>
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {cards.map((c) => (
          <button
            key={c.href}
            type="button"
            onClick={() => navigate(c.href)}
            className="p-4 bg-white rounded-lg border border-gray-200 hover:border-primary-500 hover:shadow transition text-left"
          >
            <c.icon className="w-8 h-8 text-primary-600 mb-2" />
            <h3 className="font-medium text-gray-900">{c.title}</h3>
            <p className="text-sm text-gray-500">{c.desc}</p>
          </button>
        ))}
      </div>
    </div>
  );
}
