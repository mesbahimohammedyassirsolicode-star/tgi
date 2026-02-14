import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { seancesApi } from '../services/api/seances';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function AttendancePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { user } = useAuth();
  const [dateFrom] = useState(() => new Date().toISOString().slice(0, 10));
  const [dateTo] = useState(() => new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10));

  const { data, isLoading, error } = useQuery({
    queryKey: ['seances', user?.id, user?.role, dateFrom, dateTo],
    queryFn: () => seancesApi.list({ start_date: dateFrom, end_date: dateTo, per_page: 30 }),
  });

  if (error) toast.error('Erreur chargement des séances');

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.attendance')}</h1>
      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary-600" /></div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {data?.items?.map((s) => (
            <Card key={s.id}>
              <CardHeader className="pb-2">
                <CardTitle className="text-lg">{s.affectation?.module?.label ?? 'Séance'}</CardTitle>
              </CardHeader>
              <CardContent className="text-sm text-gray-600 space-y-2">
                <p>{s.date} {s.start_time} — {s.end_time}</p>
                <p>Groupe : {s.affectation?.groupe?.label}</p>
                <p className="capitalize">{s.status}</p>
                <Button size="sm" onClick={() => navigate(`/attendance/seances/${s.id}`)}>
                  Appel
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
