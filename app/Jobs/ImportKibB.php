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

class ImportKibB implements ShouldQueue
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
        DB::transaction(function () {
            // ambil semua asset id sesuai jenis
            $assetIds = DB::pg('assets')
                ->where('jenis_asset_id', 2)
                ->pluck('id');

            if ($assetIds->isEmpty()) {
                return;
            }

            // hapus turunan dulu
            DB::pg('asset_detail_peralatan')->whereIn('assets_id', $assetIds)->delete();
            DB::pg('asset_history')->whereIn('asset_id', $assetIds)->delete();
            DB::pg('asset_snapshots')->whereIn('asset_id', $assetIds)->delete();
            DB::pg('asset_penyusutan')->whereIn('asset_id', $assetIds)->delete();

            // hapus dokumen terkait (opsional, hati2 kalau ada share)
            $dokumenIds = DB::pg('assets')
                ->whereIn('id', $assetIds)
                ->pluck('asset_dokumen_id')
                ->filter(); // buang null

            if ($dokumenIds->isNotEmpty()) {
                DB::pg('asset_dokumen')->whereIn('id', $dokumenIds)->delete();
            }

            // terakhir hapus assets
            DB::pg('assets')->whereIn('id', $assetIds)->delete();
        });

        $reader = new Reader();
        $reader->open($this->filePath);

        $chunkSize = 2000; // jumlah baris per chunk
        $chunk = [];
        $rowIndex = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;

                // skip baris 1–7
                if ($rowIndex < 6) {
                    continue;
                }

                $cells = $row->toArray();
                $chunk[] = $cells;

                if (count($chunk) >= $chunkSize) {
                    // dispatch job kecil untuk proses chunk
                    ProcessKibBChunk::dispatch($chunk);
                    $chunk = []; // reset
                }
            }
        }

        // sisa baris terakhir
        if (count($chunk) > 0) {
            ProcessKibBChunk::dispatch($chunk);
        }

        $reader->close();

        Http::post('https://n8n.giafn.my.id/webhook/success-import', [
            'status' => 'success',
            'message' => 'Import KIB B Dimulai',
        ]);

        unlink($this->filePath);
    }

    public function failed(\Throwable $exception)
    {
        Http::post('https://n8n.giafn.my.id/webhook/failure-import', [
            'status' => 'error',
            'message' => $exception->getMessage()
        ]);
    }
}
