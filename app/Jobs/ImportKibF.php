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

class ImportKibF implements ShouldQueue
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

        $chunkSize = 2000; // jumlah baris per chunk
        $chunk = [];
        $rowIndex = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;

                // skip baris 1–7
                if ($rowIndex < 5) {
                    continue;
                }

                $cells = $row->toArray();
                $chunk[] = $cells;

                if (count($chunk) >= $chunkSize) {
                    ProcessKibFChunk::dispatch($chunk);
                    $chunk = []; // reset
                }
            }
        }

        // sisa baris terakhir
        if (count($chunk) > 0) {
            ProcessKibFChunk::dispatch($chunk);
        }

        $reader->close();

        Http::post('https://n8n.giafn.my.id/webhook/success-import', [
            'status' => 'success',
            'message' => 'Import KIB F Dimulai',
        ]);

        unlink($this->filePath);
    }
}
