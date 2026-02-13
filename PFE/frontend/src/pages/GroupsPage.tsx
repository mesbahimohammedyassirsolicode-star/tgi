import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { groupsApi } from '../services/api/groups';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

export default function GroupsPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data, isLoading, error } = useQuery({
    queryKey: ['groups'],
    queryFn: () => groupsApi.list({ per_page: 20 }),
  });

  if (error) toast.error('Erreur chargement des groupes');

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.groups')}</h1>
      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary-600" /></div>
      ) : (
        <>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {data?.items?.map((g) => (
              <Card key={g.id} className="cursor-pointer hover:shadow-md transition" onClick={() => navigate(`/groups/${g.id}`)}>
                <CardHeader className="pb-2">
                  <CardTitle className="text-lg">{g.label}</CardTitle>
                </CardHeader>
                <CardContent className="text-sm text-gray-600">
                  <p>Année : {g.annee_scolaire?.label ?? g.annee_scolaire_id}</p>
                  <p>Filière : {g.filiere?.label ?? g.filiere_id}</p>
                  <p>Capacité : {g.capacity}</p>
                </CardContent>
              </Card>
            ))}
          </div>
          {data?.meta && (
            <p className="text-sm text-gray-500">
              Total : {data.meta.total} groupe(s)
            </p>
          )}
        </>
      )}
    </div>
  );
}
