<?php

namespace App\Jobs;

use Carbon\Carbon;
use OpenSpout\Reader\XLSX\Reader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ImportKibC implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reader = new Reader();
        $reader->open($this->filePath);

        $chunkSize = 500;
        $rowIndex = 0;

        $groups = [];
        $groupCounter = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            // hanya sheet ke-2 (index 1)
            if ($sheet->getIndex() !== 1) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;

                // skip baris 1–7
                if ($rowIndex < 2) {
                    continue;
                }

                $cells = $row->toArray();
                $groupKey = $cells[0] ?? null;

                if ($groupKey === null || $groupKey === '') {
                    continue; // lewati kalau kosong
                }

                // tambahkan ke grup
                $groups[$groupKey][] = $cells;
            }
        }

        // Sekarang kirim per 500 grup
        $chunkGroups = [];
        foreach ($groups as $groupKey => $rows) {
            $chunkGroups[$groupKey] = $rows;
            $groupCounter++;

            if ($groupCounter >= $chunkSize) {
                ProcessKibCChunk::dispatch($chunkGroups);
                $chunkGroups = [];
                $groupCounter = 0;
            }
        }

        // Kirim sisa grup kalau masih ada
        if (!empty($chunkGroups)) {
            ProcessKibCChunk::dispatch($chunkGroups);
        }

        $reader->close();

        Http::post('https://n8n.giafn.my.id/webhook/success-import', [
            'status' => 'success',
            'message' => 'Import KIB C Dimulai',
        ]);

        unlink($this->filePath);
    }
}
