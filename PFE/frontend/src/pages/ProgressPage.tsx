import { useAuth } from '../context/AuthContext';
import { useQuery } from '@tanstack/react-query';
import { progressApi } from '../services/api/progress';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

export default function ProgressPage() {
  const { user } = useAuth();
  const stagiaireId = user?.stagiaire?.id ?? user?.stagiaire?.id;

  const { data, isLoading, error } = useQuery({
    queryKey: ['progress', stagiaireId],
    queryFn: () => progressApi.get(stagiaireId!),
    enabled: !!stagiaireId,
  });

  if (error) toast.error('Erreur chargement de la progression');

  if (!stagiaireId) {
    return (
      <div className="p-6 text-gray-500">
        Cette page est réservée aux stagiaires (progression du syllabus par module).
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Progression — Syllabus</h1>
      {isLoading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="h-8 w-8 animate-spin text-primary-600" />
        </div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {data?.by_module?.map((m) => (
            <Card key={m.affectation_id}>
              <CardHeader className="pb-2">
                <CardTitle className="text-lg">{m.module ?? `Module #${m.affectation_id}`}</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="flex items-center gap-4">
                  <div className="flex-1 h-3 bg-gray-200 rounded-full overflow-hidden">
                    <div
                      className="h-full bg-primary-600 rounded-full transition-all"
                      style={{ width: `${m.progress_percent}%` }}
                    />
                  </div>
                  <span className="text-sm font-medium">{m.progress_percent}%</span>
                </div>
                <p className="text-xs text-gray-500 mt-2">
                  {m.completed_count} / {m.total_count} éléments
                </p>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
