import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { academicStructureApi } from '../../services/api/academicStructure';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

export default function FilieresPage() {
  const { t } = useTranslation();
  const { data, isLoading, error } = useQuery({
    queryKey: ['academic', 'filieres'],
    queryFn: () => academicStructureApi.getFilieres(),
  });

  if (error) toast.error('Erreur chargement des filières');

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.filieres')}</h1>
      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary-600" /></div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {data?.map((f) => (
            <Card key={f.id}>
              <CardHeader className="pb-2">
                <CardTitle className="text-lg">{f.label}</CardTitle>
              </CardHeader>
              <CardContent className="text-sm text-gray-600">
                <p>Code : {f.code}</p>
                {f.niveau && <p>Niveau : {f.niveau.label}</p>}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
