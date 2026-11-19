<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Renders one room's temporary passwords as cut-out cards, eight to an A4
 * page.
 *
 * @psalm-import-type Sheet from TemporaryPasswordService
 */
class TemporaryPasswordPdf
{
    private const string MEMORY_LIMIT = '1G';

    /**
     * @param  Sheet  $sheet
     */
    public function render(Tenant $tenant, array $sheet): string
    {
        // dompdf's memory use grows with the number of cards.
        $previousLimit = ini_set('memory_limit', self::MEMORY_LIMIT);

        try {
            return $this->renderSheet($tenant, $sheet);
        } finally {
            if ($previousLimit !== false) {
                ini_set('memory_limit', $previousLimit);
            }
        }
    }

    /**
     * @param  Sheet  $sheet
     */
    private function renderSheet(Tenant $tenant, array $sheet): string
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
            'sheet' => $sheet,
        ])->render(), 'UTF-8');
        $dompdf->render();

        $pdf = $dompdf->output();

        if ($pdf === null || $pdf === '') {
            throw new RuntimeException('PDF rendering produced no output.');
        }

        return $pdf;
    }
}
