<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Issues temporary passwords and packages them for download: one PDF for a
 * room, or a ZIP with the PDF of every room.
 *
 * @psalm-import-type Sheet from TemporaryPasswordService
 *
 * @psalm-type Download = array{filename: string, mime: string, content: string, new: int, existing: int}
 */
class TemporaryPasswordExport
{
    public function __construct(
        private readonly TemporaryPasswordService $passwords,
        private readonly TemporaryPasswordPdf $pdf,
    ) {}

    /**
     * @return ?Download null when the room has nobody to list
     */
    public function room(Tenant $tenant, string $sheetKey): ?array
    {
        $result = $this->passwords->issue($tenant, $sheetKey);
        $sheet = $result['sheets'][0] ?? null;

        if ($sheet === null) {
            return null;
        }

        return [
            'filename' => $this->pdfName($sheet),
            'mime' => 'application/pdf',
            'content' => $this->pdf->render($tenant, $sheet),
            'new' => $result['new'],
            'existing' => $result['existing'],
        ];
    }

    /**
     * @return ?Download null when the tenant has nobody to list
     */
    public function all(Tenant $tenant): ?array
    {
        $result = $this->passwords->issue($tenant);

        if ($result['sheets'] === []) {
            return null;
        }

        $files = [];
        foreach ($result['sheets'] as $sheet) {
            $files[$this->pdfName($sheet)] = $this->pdf->render($tenant, $sheet);
        }

        return [
            'filename' => 'temp-passwords-'.Str::slug((string) $tenant->instance_code).'-'.now()->format('Y-m-d_His').'.zip',
            'mime' => 'application/zip',
            'content' => $this->zip($files),
            'new' => $result['new'],
            'existing' => $result['existing'],
        ];
    }

    /**
     * @param  Sheet  $sheet
     */
    private function pdfName(array $sheet): string
    {
        $slug = Str::slug($sheet['room']) ?: 'room';

        return ($sheet['room_id'] === null ? $slug : $slug.'-'.$sheet['room_id']).'.pdf';
    }

    /**
     * ZipArchive only writes to a file. It holds plain-text passwords, so it
     * is removed as soon as it has been read back.
     *
     * @param  array<string, string>  $files  name => content
     */
    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tmppw');

        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary file for the ZIP.');
        }

        try {
            $zip = new ZipArchive;

            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Cannot open the ZIP for writing.');
            }

            foreach ($files as $name => $content) {
                $zip->addFromString($name, $content);
            }

            if (! $zip->close()) {
                throw new RuntimeException('Cannot write the ZIP.');
            }

            $content = file_get_contents($path);

            if ($content === false) {
                throw new RuntimeException('Cannot read the ZIP back.');
            }

            return $content;
        } finally {
            @unlink($path);
        }
    }
}
