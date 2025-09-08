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

class ImportKibE implements ShouldQueue
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

        $chunkSize = 10000; // jumlah baris per chunk
        $chunk = [];
        $rowIndex = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;

                if ($rowIndex < 5) {
                    continue;
                }

                $cells = $row->toArray();

                // skip kalau baris kosong
                if (empty(array_filter($cells))) {
                    continue;
                }

                $chunk[] = $cells;

                if (count($chunk) >= $chunkSize) {
                    // clone array sebelum dispatch
                    ProcessKibEChunk::dispatch(collect($chunk)->toArray());
                    $chunk = [];
                }
            }
        }

        // sisa terakhir
        if (!empty($chunk)) {
            ProcessKibEChunk::dispatch(collect($chunk)->toArray());
        }

        $reader->close();

        Http::post('https://n8n.giafn.my.id/webhook/success-import', [
            'status' => 'success',
            'message' => 'Import KIB E Dimulai',
        ]);
    }
}
