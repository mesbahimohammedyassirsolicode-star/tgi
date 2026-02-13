import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { timetableApi } from '../services/api/timetable';
import { groupsApi } from '../services/api/groups';
import { Card } from '../components/ui/card';
import { Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

export default function TimetablePage() {
  const { t } = useTranslation();
  const [groupeId, setGroupeId] = useState<number | ''>('');
  const [weekStart, setWeekStart] = useState(() => {
    const d = new Date();
    const day = d.getDay();
    const diff = d.getDate() - (day === 0 ? 6 : day - 1);
    const monday = new Date(d);
    monday.setDate(diff);
    return monday.toISOString().slice(0, 10);
  });

  const { data: groups } = useQuery({
    queryKey: ['groups'],
    queryFn: () => groupsApi.list({ per_page: 50 }).then((r) => r.items),
  });

  const { data: timetable, isLoading, error } = useQuery({
    queryKey: ['timetable', groupeId, weekStart],
    queryFn: () => timetableApi.get({ groupe_id: groupeId || undefined, week_start: weekStart }),
    enabled: true,
  });

  if (error) toast.error('Erreur chargement de l\'emploi du temps');

  const byDate = timetable?.by_date ?? {};
  const weekDates = (() => {
    const start = new Date(weekStart);
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
        <label className="text-sm font-medium text-gray-700">
          Groupe
          <select
            value={groupeId}
            onChange={(e) => setGroupeId(e.target.value ? Number(e.target.value) : '')}
            className="ml-2 rounded border border-gray-300 px-3 py-2"
          >
            <option value="">Tous</option>
            {groups?.map((g) => (
              <option key={g.id} value={g.id}>{g.label}</option>
            ))}
          </select>
        </label>
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
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr className="border-b">
                <th className="text-left p-2 w-24">Heure</th>
                {weekDates.map((date) => (
                  <th key={date} className="text-left p-2 border-l">
                    {DAYS[new Date(date).getDay() - 1] ?? date} {date}
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
                      <td key={date} className="p-2 border-l align-top">
                        {seances.map((s: { id: number; start_time: string; end_time: string; affectation?: { module?: { label: string }; salle?: string } }) => (
                          <Card key={s.id} className="mb-2 p-2 text-xs">
                            <p className="font-medium">{s.affectation?.module?.label ?? '—'}</p>
                            <p className="text-gray-500">{s.start_time} - {s.end_time}</p>
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
