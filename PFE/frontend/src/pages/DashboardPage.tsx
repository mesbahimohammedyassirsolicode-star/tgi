import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { BookOpen, Calendar, Users, ClipboardList, GraduationCap, School, Briefcase } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import type { DashboardStats } from '../services/dashboardService';
import { dashboardService } from '../services/dashboardService';
import { modulesApi } from '../services/api/modules';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

export default function DashboardPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { user } = useAuth();
  const roleLabel = user?.role === 'admin' ? 'Directeur / Secrétariat' : user?.role;
  const [data, setData] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);

  const { data: myModules } = useQuery({
    queryKey: ['modules', 'my'],
    queryFn: () => modulesApi.list(),
    enabled: user?.role === 'stagiaire' && !!user?.stagiaire?.filiere_id,
  });

  useEffect(() => {
    const fetchStats = async () => {
      try {
        const stats = await dashboardService.getStats();
        setData(stats);
      } catch (error) {
        console.error('Failed to fetch dashboard stats', error);
      } finally {
        setLoading(false);
      }
    };

    if (user?.role === 'admin') {
      fetchStats();
    } else {
      setLoading(false);
    }
  }, [user]);

  const cards = [
    { href: '/academic/years', icon: Calendar, title: t('nav.years'), desc: 'Années scolaires', value: '2025-2026' },
    { href: '/groups', icon: Users, title: t('nav.groups'), desc: 'Groupes et classes', value: data?.stats?.groupes_count ?? 0 },
    { href: '/modules', icon: BookOpen, title: t('nav.modules'), desc: 'Modules et syllabus', value: (data?.stats?.filieres_count ?? 0) + ' Filières' },
    { href: '/attendance', icon: ClipboardList, title: t('nav.attendance'), desc: 'Séances et présences', value: 'Actif' },
  ];

  const adminCards = [
    { title: 'Stagiaires', value: data?.stats?.stagiaires_count ?? 0, icon: GraduationCap, color: 'text-blue-600', bg: 'bg-blue-100' },
    { title: 'Formateurs', value: data?.stats?.formateurs_count ?? 0, icon: Briefcase, color: 'text-emerald-600', bg: 'bg-emerald-100' },
    { title: 'Groupes', value: data?.stats?.groupes_count ?? 0, icon: Users, color: 'text-purple-600', bg: 'bg-purple-100' },
    { title: 'Filières', value: data?.stats?.filieres_count ?? 0, icon: School, color: 'text-orange-600', bg: 'bg-orange-100' },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">{t('nav.dashboard')} — GIMS</h1>
        <p className="text-gray-600 mt-1">Bienvenue, {user?.name}. Rôle : {roleLabel}</p>
      </div>

      {/* Stagiaire: filière, modules, emploi du temps */}
      {user?.role === 'stagiaire' && (
        <div className="space-y-4">
          <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
            <h3 className="text-lg font-medium text-gray-900 mb-2">Ma formation</h3>
            {user.stagiaire?.filiere ? (
              <>
                <p className="text-gray-600">
                  <span className="font-medium">Filière (spécialité) :</span>{' '}
                  {user.stagiaire.filiere.code} — {user.stagiaire.filiere.label}
                </p>
                {user.stagiaire.niveau_formation && (
                  <p className="text-gray-600 mt-1">
                    <span className="font-medium">Type de formation :</span>{' '}
                    {user.stagiaire.niveau_formation === 'TS' && 'Technicien Spécialisé'}
                    {user.stagiaire.niveau_formation === 'Q' && 'Qualification'}
                    {user.stagiaire.niveau_formation === 'T' && 'Technicien'}
                    {user.stagiaire.niveau_formation === 'BACHELOR' && 'Bachelor'}
                    {user.stagiaire.niveau_formation === 'MASTER' && 'Master'}
                    {!['TS','Q','T','BACHELOR','MASTER'].includes(user.stagiaire.niveau_formation) && user.stagiaire.niveau_formation}
                  </p>
                )}
              </>
            ) : (
              <p className="text-amber-700">Aucune filière assignée. Contactez l&apos;administration.</p>
            )}
          </div>
          {Array.isArray(myModules) && myModules.length > 0 && (
            <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-3">Mes modules</h3>
              <ul className="space-y-2">
                {myModules.slice(0, 10).map((m: { id: number; code: string; label: string }) => (
                  <li key={m.id} className="flex items-center text-sm text-gray-700">
                    <BookOpen className="w-4 h-4 mr-2 text-primary-600 shrink-0" />
                    {m.code} — {m.label}
                  </li>
                ))}
                {myModules.length > 10 && (
                  <li className="text-sm text-gray-500">… et {myModules.length - 10} autre(s) module(s)</li>
                )}
              </ul>
            </div>
          )}
          <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
            <h3 className="text-lg font-medium text-gray-900 mb-2">Emploi du temps</h3>
            <p className="text-gray-600 mb-3">Consultez les séances de votre groupe.</p>
            <button
              type="button"
              onClick={() => navigate('/timetable')}
              className="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700"
            >
              <Calendar className="w-4 h-4 mr-2" />
              Voir l&apos;emploi du temps
            </button>
          </div>
        </div>
      )}

      {/* Admin Stats Grid */}
      {user?.role === 'admin' && (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {adminCards.map((c) => (
              <div key={c.title} className="p-4 bg-white rounded-lg border border-gray-200 shadow-sm flex items-center">
                <div className={`p-3 rounded-full ${c.bg} mr-4`}>
                  <c.icon className={`w-6 h-6 ${c.color}`} />
                </div>
                <div>
                  <p className="text-sm font-medium text-gray-500">{c.title}</p>
                  <p className="text-2xl font-bold text-gray-900">{loading ? '-' : c.value}</p>
                </div>
              </div>
            ))}
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div className="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Stagiaires par Filière</h3>
              <div className="h-64">
                {loading ? (
                  <div className="h-full flex items-center justify-center text-gray-400">Chargement...</div>
                ) : Array.isArray(data?.charts?.students_per_filiere) ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data.charts.students_per_filiere}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="name" />
                      <YAxis />
                      <Tooltip />
                      <Bar dataKey="value" fill="#4f46e5" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="h-full flex items-center justify-center text-gray-400">Aucune donnée</div>
                )}
              </div>
            </div>

            <div className="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Raccourcis Rapides</h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {cards.map((c) => (
                  <button
                    key={c.href}
                    onClick={() => navigate(c.href)}
                    className="p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition text-left border border-gray-200"
                  >
                    <div className="flex items-center justify-between mb-2">
                      <c.icon className="w-5 h-5 text-gray-600" />
                      <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Aller</span>
                    </div>
                    <h4 className="font-medium text-gray-900">{c.title}</h4>
                    <p className="text-sm text-gray-500">{c.desc}</p>
                  </button>
                ))}
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
