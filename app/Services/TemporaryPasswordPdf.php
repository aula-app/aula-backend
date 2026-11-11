<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Renders temporary-password sheets as one PDF, a page per sheet.
 *
 * @psalm-import-type Sheet from TemporaryPasswordService
 */
class TemporaryPasswordPdf
{
    private const string MEMORY_LIMIT = '1G';

    /**
     * @param  list<Sheet>  $sheets
     */
    public function render(Tenant $tenant, array $sheets): string
    {
        // dompdf needs roughly 190 MB for 1200 users.
        $previousLimit = ini_set('memory_limit', self::MEMORY_LIMIT);

        try {
            return $this->renderSheets($tenant, $sheets);
        } finally {
            if ($previousLimit !== false) {
                ini_set('memory_limit', $previousLimit);
            }
        }
    }

    /**
     * @param  list<Sheet>  $sheets
     */
    private function renderSheets(Tenant $tenant, array $sheets): string
    {
        $cacheDir = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cacheDir);

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        // DejaVu ships with dompdf and covers umlauts.
        $options->setDefaultFont('DejaVu Sans');
        $options->setTempDir($cacheDir);
        $options->setFontCache($cacheDir);
        $options->setChroot([$cacheDir]);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml(view('pdf.temp-passwords', [
            'tenant' => $tenant,
            'sheets' => $sheets,
            'generatedAt' => now(),
        ])->render(), 'UTF-8');
        $dompdf->render();

        $pdf = $dompdf->output();

        if ($pdf === null || $pdf === '') {
            throw new RuntimeException('PDF rendering produced no output.');
        }

        return $pdf;
    }
}
