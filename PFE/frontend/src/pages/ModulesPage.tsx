import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { modulesApi } from '../services/api/modules';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

export default function ModulesPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data, isLoading, error } = useQuery({
    queryKey: ['modules'],
    queryFn: () => modulesApi.list(),
  });

  if (error) toast.error('Erreur chargement des modules');

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.modules')}</h1>
      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary-600" /></div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {data?.map((m) => (
            <Card key={m.id} className="cursor-pointer hover:shadow-md transition" onClick={() => navigate(`/modules/${m.id}`)}>
              <CardHeader className="pb-2">
                <CardTitle className="text-lg">{m.code} — {m.label}</CardTitle>
              </CardHeader>
              <CardContent className="text-sm text-gray-600">
                <p>Masse horaire : {m.masse_horaire}h • Coef. {m.coefficient}</p>
                {m.filiere && <p>Filière : {m.filiere.label}</p>}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
