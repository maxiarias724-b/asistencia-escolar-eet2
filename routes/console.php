<?php

use App\Services\InstitutionalStateSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('institution:sync {--export : Exporta la base de datos al snapshot institucional} {--import : Importa el snapshot institucional a la base de datos}', function (InstitutionalStateSyncService $syncService) {
    if ($this->option('export')) {
        $ok = $syncService->exportSnapshot();
        $this->info($ok
            ? 'Estado institucional exportado en: '.$syncService->snapshotPath()
            : 'No se pudo exportar el estado institucional.');

        return;
    }

    if ($this->option('import')) {
        $ok = $syncService->importSnapshot(true);
        $this->info($ok
            ? 'Estado institucional importado correctamente desde: '.$syncService->snapshotPath()
            : 'No se encontró o no se pudo importar el snapshot institucional.');

        return;
    }

    $ok = $syncService->exportSnapshot();
    $this->info($ok
        ? 'Estado institucional sincronizado (exportado) en: '.$syncService->snapshotPath()
        : 'No se pudo sincronizar el estado institucional.');
})->purpose('Sincroniza usuarios, asignaciones y asistencias entre computadoras (database/seeders/data/institutional_state.json)');
