import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { affectationsApi } from '../services/api/affectations';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

export default function AffectationsPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data, isLoading, error } = useQuery({
    queryKey: ['affectations'],
    queryFn: () => affectationsApi.list(),
  });

  if (error) toast.error('Erreur chargement des affectations');

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.affectations')}</h1>
      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary-600" /></div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {data?.map((a) => (
            <Card key={a.id}>
              <CardHeader className="pb-2">
                <CardTitle className="text-lg">{a.module?.label ?? a.module_id}</CardTitle>
              </CardHeader>
              <CardContent className="text-sm text-gray-600">
                <p>Groupe : {a.groupe?.label ?? a.groupe_id}</p>
                <p>Formateur : {a.formateur?.user?.name ?? a.formateur_id}</p>
                <Button size="sm" variant="outline" className="mt-2" onClick={() => navigate(`/affectations/${a.id}/grades`)}>
                  Voir notes (moyenne module)
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
