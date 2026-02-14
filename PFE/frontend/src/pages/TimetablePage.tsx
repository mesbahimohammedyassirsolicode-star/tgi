import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { timetableApi, type TimetableSeance } from '../services/api/timetable';
import { groupsApi } from '../services/api/groups';
import { useAuth } from '../context/AuthContext';
import { Card } from '../components/ui/card';
import { Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

export default function TimetablePage() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const isStudent = user?.role === 'stagiaire';

  const [groupeId, setGroupeId] = useState<number | ''>('');
  const [weekStart, setWeekStart] = useState(() => {
    const d = new Date();
    const day = d.getDay();
    const diff = d.getDate() - (day === 0 ? 6 : day - 1);
    const monday = new Date(d);
    monday.setDate(diff);
    return monday.toISOString().slice(0, 10);
  });

  const { data: groupsData } = useQuery({
    queryKey: ['groups', user?.id, user?.role],
    queryFn: () => groupsApi.list({ per_page: 50 }),
    enabled: !isStudent,
  });
  const groups = Array.isArray(groupsData?.items) ? groupsData.items : [];

  const { data: timetable, isLoading, error, isError } = useQuery({
    queryKey: ['timetable', user?.id, user?.role, isStudent ? 'my' : groupeId, weekStart],
    queryFn: () =>
      timetableApi.get(
        isStudent ? { week_start: weekStart } : { groupe_id: groupeId || undefined, week_start: weekStart }
      ),
    enabled: true,
  });

  useEffect(() => {
    if (isError && error) {
      toast.error('Erreur chargement de l\'emploi du temps');
    }
  }, [isError, error]);

  useEffect(() => {
    if (timetable?.week_start && (timetable.seances?.length ?? 0) > 0 && timetable.week_start !== weekStart) {
      setWeekStart(timetable.week_start);
    }
  }, [timetable?.week_start, timetable?.seances?.length]);

  const byDate = timetable?.by_date ?? {};
  const seances = timetable?.seances ?? [];
  const isEmpty = !isLoading && !isError && Array.isArray(seances) && seances.length === 0;
  const effectiveWeekStart = (timetable?.week_start && timetable.week_start !== '') ? timetable.week_start : weekStart;
  const weekDates = (() => {
    const start = new Date(effectiveWeekStart);
    if (isNaN(start.getTime())) return [];
    return Array.from({ length: 6 }, (_, i) => {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      return d.toISOString().slice(0, 10);
    });
  })();

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.attendance')} — Emploi du temps</h1>
      <div className="flex flex-wrap gap-4 items-center">
        {!isStudent && (
          <label className="text-sm font-medium text-gray-700">
            Groupe
            <select
              value={groupeId}
              onChange={(e) => setGroupeId(e.target.value ? Number(e.target.value) : '')}
              className="ml-2 rounded border border-gray-300 px-3 py-2"
            >
              <option value="">Tous</option>
              {groups.map((g) => (
                <option key={g.id} value={g.id}>{g.label}</option>
              ))}
            </select>
          </label>
        )}
        <label className="text-sm font-medium text-gray-700">
          Semaine
          <input
            type="date"
            value={weekStart}
            onChange={(e) => setWeekStart(e.target.value)}
            className="ml-2 rounded border border-gray-300 px-3 py-2"
          />
        </label>
      </div>
      {isLoading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="h-8 w-8 animate-spin text-primary-600" />
        </div>
      ) : isError ? (
        <div className="py-12 text-center">
          <p className="text-red-600 font-medium">Erreur chargement de l&apos;emploi du temps.</p>
          <p className="text-sm text-gray-500 mt-1">Vérifiez votre connexion ou réessayez plus tard.</p>
        </div>
      ) : isEmpty ? (
        <div className="py-12 text-center text-gray-500">
          <p>Aucune séance pour cette semaine.</p>
          {isStudent && (
            <p className="text-sm mt-1">Assurez-vous d&apos;être inscrit dans un groupe lié à votre filière.</p>
          )}
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr className="border-b">
                <th className="text-left p-2 w-24">Heure</th>
                {weekDates.map((date, i) => (
                  <th key={date} className="text-left p-2 border-l">
                    {DAYS[i]} {date}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {[8, 9, 10, 11, 14, 15, 16, 17].map((hour) => (
                <tr key={hour} className="border-b">
                  <td className="p-2 text-gray-500">{hour}h00</td>
                  {weekDates.map((date) => {
                    const seances = (byDate[date] ?? []).filter(
                      (s: { start_time: string }) => Number(s.start_time?.slice(0, 2)) === hour
                    );
                    return (
                      <td key={date} className="p-2 border-l align-top min-w-[140px]">
                        {seances.map((s: TimetableSeance) => (
                          <Card key={s.id} className="mb-2 p-2 text-xs bg-white shadow-sm border border-gray-200">
                            <p className="font-semibold text-gray-900">{s.affectation?.module?.label ?? '—'}</p>
                            <p className="text-gray-500 mt-0.5">{s.start_time?.slice(0, 5)} – {s.end_time?.slice(0, 5)}</p>
                            {s.affectation?.formateur?.user?.name && (
                              <p className="text-gray-600 mt-1 truncate" title={s.affectation.formateur.user.name}>
                                {s.affectation.formateur.user.name}
                              </p>
                            )}
                            {s.salle && (
                              <p className="text-xs text-blue-700 font-medium mt-1">Salle: {s.salle}</p>
                            )}
                          </Card>
                        ))}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
