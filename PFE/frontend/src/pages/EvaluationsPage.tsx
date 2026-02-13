import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { evaluationsApi } from '../services/api/evaluations';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

export default function EvaluationsPage() {
  const { t } = useTranslation();
  const { data, isLoading, error } = useQuery({
    queryKey: ['evaluations'],
    queryFn: () => evaluationsApi.list({ per_page: 20 }),
  });

  if (error) toast.error('Erreur chargement des évaluations');

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.evaluations')}</h1>
      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary-600" /></div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {data?.items?.map((e) => (
            <Card key={e.id}>
              <CardHeader className="pb-2">
                <CardTitle className="text-lg">{e.item_label}</CardTitle>
              </CardHeader>
              <CardContent className="text-sm text-gray-600">
                <p>Type : {e.type} • Barème : {e.max_points}</p>
                <p>Date : {e.date}</p>
                <p>Module : {e.affectation?.module?.label}</p>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
