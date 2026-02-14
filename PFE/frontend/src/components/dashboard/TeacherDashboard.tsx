import { useNavigate } from 'react-router-dom';
import { Calendar, BookOpen, ClipboardCheck, PenLine } from 'lucide-react';
import type { TeacherDashboardData } from '../../services/dashboardService';
import { Button } from '../ui/button';

interface TeacherDashboardProps {
  data: TeacherDashboardData;
  userName: string;
}

export default function TeacherDashboard({ data, userName }: TeacherDashboardProps) {
  const navigate = useNavigate();
  const { todays_sessions, assigned_modules } = data;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Tableau de bord - Formateur</h1>
        <p className="text-gray-600 mt-1">Bienvenue, {userName}.</p>
      </div>

      <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
        <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
          <Calendar className="w-5 h-5 mr-2 text-indigo-600" />
          Seances du jour
        </h3>
        {todays_sessions?.length > 0 ? (
          <ul className="divide-y divide-gray-200">
            {todays_sessions.map((s) => (
              <li key={s.id} className="py-3 flex justify-between items-center">
                <span className="font-medium">{s.module}</span>
                <span className="text-gray-500 text-sm">
                  {s.groupe} - {String(s.start_time).slice(0, 5)} - {String(s.end_time).slice(0, 5)}
                </span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-gray-500">Aucune seance aujourd&apos;hui.</p>
        )}
      </div>

      <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
        <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
          <BookOpen className="w-5 h-5 mr-2 text-indigo-600" />
          Modules assignes
        </h3>
        {assigned_modules?.length > 0 ? (
          <ul className="space-y-2">
            {assigned_modules.map((m) => (
              <li key={m.module_id} className="flex justify-between text-sm">
                <span>{m.module_code} - {m.module_label}</span>
                <span className="text-gray-500">{m.groupes?.map((g) => g.label).join(', ') || '-'}</span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-gray-500">Aucun module assigne.</p>
        )}
      </div>

      <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
        <h3 className="text-lg font-medium text-gray-900 mb-4">Raccourcis</h3>
        <div className="flex flex-wrap gap-3">
          <Button onClick={() => navigate('/attendance')}>
            <ClipboardCheck className="w-4 h-4 mr-2" />
            Marquer les presences
          </Button>
          <Button variant="outline" onClick={() => navigate('/evaluations')}>
            <PenLine className="w-4 h-4 mr-2" />
            Saisir les notes
          </Button>
        </div>
      </div>
    </div>
  );
}

