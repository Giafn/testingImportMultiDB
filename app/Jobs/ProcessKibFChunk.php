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

class ProcessKibFChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function handle()
    {
        $firstNumberRow = null;
        $lastNumberRow = null;

        $db = DB::connection('pgsql');

        try {
            $db->beginTransaction();
            foreach ($this->rows as $cells) {
                if ($firstNumberRow === null) {
                    $firstNumberRow = $cells[0];
                }
                $lastNumberRow = $cells[0];
                
                $data = $this->formatData($cells);
                if ($data) {
                    $this->storeData($data);
                }
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            Http::post('https://n8n.giafn.my.id/webhook/success-import', [
                'status' => 'success',
                'message' => 'error : ' . $e->getMessage()
            ]);
            throw $e;
        }

        Http::post('https://n8n.giafn.my.id/webhook/success-import', [
            'status' => 'success',
            'message' => 'Import Chunk KIB F selesai ' . $firstNumberRow . ' sampai ' . $lastNumberRow
        ]);
    }

    public function failed(\Throwable $exception)
    {
        Http::post('https://n8n.giafn.my.id/webhook/success-import', [
            'status' => 'error',
            'message' => $exception->getMessage() . ' - ' . json_encode($this->rows)
        ]);
    }

    private function storeData($mapped) {
        $document = [
            "status" => 3,
            "transaksi_id" => 1,
            "cara_perolehan_id" => 1,
            "sumber_dana_id" => 1,
            "metode_pengadaan_id" => 2,
            "mekanisme_pencairan_dana_id" => 1,
            "master_pembayaran_id" => 1,
            "program_id" => null,
            "kegiatan" => null,
            "sub_kegiatan_id" => null,
            "rekening_id" => null,
            "dokumen_kontrak_id" => 1,
            "tanggal_dokumen_kontrak" => $mapped['tanggal_perolehan'],
            "nomor_dokumen_kontrak" => null,
            "file_dokumen_kontrak" => null,
            "dokumen_sumber_id" => null,
            "tanggal_dokumen_sumber" => null,
            "nomor_dokumen_sumber" => null,
            "file_dokumen_sumber" => null,
            "nomor_dokumen_pernyataan" => null,
            "tanggal_dokumen_pernyataan" => null,
            "file_dokumen_pernyataan" => null,
            "jumlah_assets" => 1,
            "distribusi" => null,
            "satuan" => 1,
            "ppn" => 0,
            "atribusi" => 0,
            "jumlah_awal" => $mapped['harga'],
            "jumlah_harga" => $mapped['harga'],
            "deskripsi_status" => null,
            "created_at" => $mapped['tanggal_perolehan'],
            "kapitalisasi" => 0,
            "ppn_harga" => 0,
            "nama_hibah" => null,
            "no_bast" => null,
            "hibah_date" => null,
        ];
        $documentId = DB::pg('asset_dokumen')->insertGetId($document);
        $upb = DB::pg('upb')->where('code', 'like', '%' . $mapped['kode_skpd'] . '%')->first();
        $subUnit = DB::pg('sub_unit')->where('id', $upb->sub_unit_id)->first();
        $unit = DB::pg('unit')->where('id', $subUnit->unit_id)->first();
        $bidang = DB::pg('bidang')->where('id', $unit->bidang_id)->first();
        $urusan = DB::pg('urusan')->where('id', $bidang->urusan_id)->first();
        $mapped['kode_barang'] = implode('.', array_slice(explode('.', $mapped['kode_barang']), 1));
        $subsub = DB::pg('sub_sub_rincian_objek_assets')->where('code', $mapped['kode_barang'])->first();
        $asset = [
            "asset_dokumen_id" => $documentId,
            "status" => 3,
            "jenis_asset_id" => 6,
            "urusan_id" => $urusan->id,
            "bidang_id" => $bidang->id,
            "unit_id" => $unit->id,
            "sub_unit_id" => $subUnit->id,
            "upb_id" => $upb->id,
            "akun_id" => 1,
            "sub_sub_rincian_id" => $subsub->id,
            "tanggal_buku" => $mapped['tanggal_perolehan'],
            "tanggal_pembelian" => $mapped['tanggal_perolehan'],
            "kode_lokasi" => null,
            "kode_register" => $mapped['no_register'],
            "nibar" => null,
            "spesifikasi_nama_barang" => $mapped['jenis_barang'],
            "harga_satuan" => $mapped['harga'],
            "satuan" => 1,
            "ppn" => null,
            "atribusi" => null,
            "jumlah_awal" => $mapped['harga'],
            "jumlah_harga" => $mapped['harga'],
            "kondisi" => 1,
            "spesifikasi_lainnya" => null,
            "keterangan_tambahan" => null,
            "foto" => null,
            "kecamatan" => null,
            "kelurahan_desa" => null,
            "jalan" => null,
            "rt_rw" => null,
            "deskripsi_status" => null,
            "pemanfaatan" => null,
            "deleted_at" => null,
            "deleted_by" => null,
            "created_at" => $mapped['tanggal_perolehan'],
            "updated_at" => null,
            "masa_manfaat" => null,
            "created_by" => null,
            "kapitalisasi" => null,
            "is_ditemukan" => false,
            "ppn_harga" => null,
            "nama_hibah" => null,
            "no_bast" => null,
            "hibah_date" => null,
            "cara_perolehan_id" => 1,
            "sumber_dana_id" => 1,
            "nilai_permeter" => null,
            "metode_pengadaan_id" => 1,
            "mekanisme_pencairan_dana_id" => 1,
            "master_pembayaran_id" => 1,
            "ukuran_aset" => null,
            "harga_per_ukuran" => null,
            "old_id_pemda" => $mapped['id_barang'],
        ];
        
        $assetId = DB::pg('assets')->insertGetId($asset);
        $asset['id'] = $assetId;
    
        // insert detail asset
        $detailAsset = [
            "assets_id" => $assetId,
            "titik_kordinat" => null,
            "nomor_dokumen_kdp" => null,
            "tanggal_dokumen_kdp" => $mapped['tanggal_dokumen_kdp'],
            "nomor_dokumen_laporan" => null,
            "tanggal_dokumen_laporan" => $mapped['tanggal_dokumen_laporan'],
            "nama_dokumen_pendukung" => null,
            "nomor_dokumen_pendukung" => null,
            "tanggal_dokumen_pendukung" => null,
            "seterusnya_dokumen_pendukung" => null,
            "created_at" => $mapped['tanggal_perolehan'],
            "updated_at" => null,
            "luas" => null,
            "status_tanah" => null,
            "jumlah_lantai" => $mapped['bertingkat'] == "Bertingkat" ? 2 : 1,
            "panjang" => null,
            "lebar" => null,
        ];
    
        DB::pg('asset_detail_konstruksi')->insertGetId($detailAsset);

        $detailAwal = $this->getDetailAsset($assetId, $mapped, $mapped['jenis_asset_id']);
    
        // inset AssetHistory
        $historyPengadaan = [
            "asset_id" => $assetId,
            "type" => 'pengadaan',
            "json_before" => json_encode([]), //$asset,
            "json_after" => json_encode($asset), //$asset,
            "created_by" => 1,
            "created_at" => $mapped['tanggal_perolehan'],
            "updated_at" => null,
            "penambahan" => $mapped['harga'],
            "pengurangan" => null,
            "sisa" => $mapped['harga'],
            "upb_before_id" => null,
            "upb_after_id" => null,
            "jenis_before_id" => null,
            "jenis_after_id" => null,
            "asset_reklasifikasi_id" => null,
            "upb_id" => $upb->id,
            "keterangan" => "Pengadaan Migrasi PM",
            "details" => json_encode($detailAwal),
            "status" => "pembukuan",
        ];
    
        DB::pg('asset_history')->insertGetId($historyPengadaan);

        $historyReklasifikasi = [
            "asset_id" => $assetId,
            "type" => 'reklasifikasi',
            "json_before" => json_encode($asset), //$asset,
            "json_after" => json_encode($asset), //$asset,
            "created_by" => 1,
            "created_at" => $mapped['tanggal_perolehan'] . ' 00:01:00',
            "updated_at" => null,
            "penambahan" => 0,
            "pengurangan" => null,
            "sisa" => $mapped['harga'],
            "upb_before_id" => null,
            "upb_after_id" => null,
            "jenis_before_id" => $mapped['jenis_asset_id'],
            "jenis_after_id" => 6,
            "asset_reklasifikasi_id" => null,
            "upb_id" => $upb->id,
            "keterangan" => "Reklasifikasi penggolongan & kodefikasi ke aset",
            "details" => json_encode($detailAwal),
            "status" => "pembukuan",
        ];
    
        $assetHistoryId = DB::pg('asset_history')->insertGetId($historyReklasifikasi);
    
        $assetReklasifikasi = [
            "asset_id" => $assetId,
            "history_id" => $assetHistoryId,
            "penyebab_reklasifikasi" => 1,
            "nama_dokumen_sumber" => "",
            "nomor_dokumen_sumber" => "",
            "tanggal_dokumen_sumber" => $mapped['tanggal_dokumen_kdp'],
            "file_dokumen_sumber" => null,
            "nama_dokumen_pendukung" => "",
            "nomor_dokumen_pendukung" => "",
            "tanggal_dokumen_pendukung" => null,
            "file_dokumen_pendukung" => null,
            "spesifikasi_dokumen_pendukung" => null,
            "created_at" => $mapped['tanggal_perolehan'] . ' 00:01:00',
            "updated_at" => null,
            "keterangan" => $mapped['keterangan'],
            "pilihan_reklasifikasi" => 2,
            "data" => json_encode([
                "from_asset" => $assetId,
                "to_sub_sub_rincian_id" => $asset['sub_sub_rincian_id'],
                "from_jenis_asset" => $asset['jenis_asset_id'],
                "to_jenis_asset" => "6",
                "harga" => $mapped['harga'],
            ]),
        ];

        $assetReklasifikasiId = DB::pg('asset_reklasifikasi')->insertGetId($assetReklasifikasi);

        // update history
        DB::pg('asset_history')->where('id', $assetHistoryId)->update([
            'asset_reklasifikasi_id' => $assetReklasifikasiId
        ]);
        
        // insert asset snapshot
        $assetSnapshot = [
            "asset_id" => $assetId,
            "cara_perolehan_id" => $asset['cara_perolehan_id'],
            "sumber_dana_id" => $asset['sumber_dana_id'],
            "is_ditemukan" => $asset['is_ditemukan'],
            "sub_sub_rincian_id" => $subsub->id,
            "kode_sub_sub_rincian" => $subsub->code,
            "upb_id" => $upb->id,
            "triwulan" => $this->hitungTriwulanDanTahun($mapped['tanggal_perolehan'] ?? null)['triwulan'],
            "tahun" => $this->hitungTriwulanDanTahun($mapped['tanggal_perolehan'] ?? null)['tahun'],
            "kondisi" => 1,
            "nilai_asset" => $asset['jumlah_harga'],
            "akumulasi_penyusutan" => 0,
            "details" => json_encode($detailAsset),
            "created_at" => $mapped['tanggal_perolehan'],
            "updated_at" => null,
            "status" => "pembukuan",
            "masa_manfaat" => null,
            "is_per_unit" => false,
            "nilai_per_unit" => 0,
        ];
    
        DB::pg('asset_snapshots')->insert($assetSnapshot);
    }

    private function formatData($cells) 
    {
        $jenisAssetId = explode(".", $cells[17])[2] ?? null;
        if ($jenisAssetId == "1") {
            $kodeBarang = "1.3.6.1.1.1.1";
        } else if ($jenisAssetId == "2") {
            $kodeBarang = "1.3.6.1.1.1.2";
        } else if ($jenisAssetId == "3") {
            $kodeBarang = "1.3.6.1.1.1.3";
        } else if ($jenisAssetId == "4") {
            $kodeBarang = "1.3.6.1.1.1.4";
        } else if ($jenisAssetId == "5") {
            $kodeBarang = "1.3.6.1.1.1.5";
        }

        try {
            return [
                    'no' => $cells[0] ?? null,
                    'id_barang' => $cells[1] ?? null,
                    'jenis_asset_id' => explode(".", $cells[17])[2] ?? null,
                    'tahun' => $cells[2] ?? null,
                    'kode_skpd' => $cells[7] ?? null,
                    'nama_skpd' => $cells[8] ?? null,
                    'kelompok_barang' => $cells[15] ?? null,
                    'kode_barang_awal' => $cells[17] ?? null,
                    'kode_barang' => $kodeBarang,
                    'jenis_barang' => $cells[18] ?? null,
                    'no_register' => $cells[19] ?? null,
                    'titik_kordinat' => null,
                    'nomor_dokumen_kdp' => null,
                    'tanggal_dokumen_kdp' => $this->formatDate($cells[24] ?? null),
                    'nomor_dokumen_laporan' => null,
                    'tanggal_dokumen_laporan' => $this->formatDate($cells[24] ?? null),
                    'nama_dokumen_pendukung' => null,
                    'nomor_dokumen_pendukung' => null,
                    'tanggal_dokumen_pendukung' => null,
                    'seterusnya_dokumen_pendukung' => null,
                    'luas'=> null,
                    'status_tanah' => null,
                    'jumlah_lantai' => null,
                    'panjang' => null,
                    'lebar' => null,
                    'beton' => $cells[21] ?? null,
                    'bertingkat' => $cells[20] ?? null,
                    'harga' => $cells[28] ?? null,
                    'tanggal_perolehan' => $this->formatDate($cells[24] ?? null),
                    'keterangan' => $cells[29] ?? null,
                ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function formatDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            // sudah object DateTime
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            // Excel date serial number
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        if (is_string($value)) {
            // coba parse string
            return date('Y-m-d', strtotime($value));
        }

        return null;
    }

    public function hitungTriwulanDanTahun($date)
    {
        $date = Carbon::parse($date);
        $month = $date->month;
        $year = $date->year;

        if ($month >= 1 && $month <= 3) {
            return [
                'text_triwulan' => 'I',
                'triwulan' => 1,
                'tahun' => $year,
            ];
        } else if ($month >= 4 && $month <= 6) {
            return [
                'text_triwulan' => 'II',
                'triwulan' => 2,
                'tahun' => $year,
            ];
        } else if ($month >= 7 && $month <= 9) {
            return [
                'text_triwulan' => 'III',
                'triwulan' => 3,
                'tahun' => $year,
            ];
        } else {
            return [
                'text_triwulan' => 'IV',
                'triwulan' => 4,
                'tahun' => $year,
            ];
        }
    }
    
    private function getDetailAsset($assetId, $mapped, $jenisAssetId)
    {
        if ($jenisAssetId == 1) {
            return [
                "assets_id" => $assetId,
                "luas_tanah" => null,
                "satuan_luas_tanah" => null,
                "titik_kordinat" => null,
                "nama_dokumen" => null,
                "nomor_dokumen" => null,
                "tanggal_dokumen" => $mapped['tanggal_perolehan'],
                "nama_kepemilikan_dokumen"  => null,
                "utara"  => null,
                "selatan"  => null,
                "barat"  => null,
                "timur"  => null,
                "deleted_at"  => null,
                "deleted_by"  => null,
                "created_at"  => $mapped['tanggal_perolehan'],
                "updated_at"  => null,
            ];
        } else if ($jenisAssetId == 2) {
            return [
                "assets_id" => $assetId,
                "masa_manfaat" => null,
                "sisa_masa_manfaat" => null,
                "merek_tipe" => null,
                "nomor_polisi" => null,
                "nomor_bpkb" => null,
                "nama_pemilik" => null,
                "tipe_kendaraan" => null,
                "jenis_kendaraan" => null,
                "model_kendaraan" => null,
                "tahun_pembuatan" => null,
                "isi_silinder" => null,
                "nomor_rangka_nik_vin" => null,
                "nomor_mesin" => null,
                "warna" => null,
                "warna_tnkb" => null,
                "deleted_at" => null,
                "deleted_by" => null,
                "created_at" => $mapped['tanggal_perolehan'],
                "updated_at" => null,
                "bahan_kendaraan" => null,
            ];
        } else if ($jenisAssetId == 3) {
            return [
                "assets_id" => $assetId,
                "masa_manfaat" => null,
                "sisa_masa_manfaat" => null,
                "jumlah_lantai" => $mapped['bertingkat'] == "Betingkat" ? 2 : 1,
                "luas_gedung" => null,
                "titik_kordinat" => null,
                "status_kepemilikan" => null,
                "kode_barang_tanah" => null,
                "kode_lokasi_tanah" => null,
                "kode_register_tanah" => null,
                "deleted_at" => null,
                "deleted_by" => null,
                "created_at" => $mapped['tanggal_perolehan'],
                "updated_at" => null,
            ];
        } else if ($jenisAssetId == 4) {
            return [
                "assets_id" => $assetId,
                "masa_manfaat" => null,
                "sisa_masa_manfaat" => null,
                "jenis_perkerasan_barang" => $mapped['beton'] == "Beton" ? 1 : 2,
                "jenis_bahan_struktur_jembatan" => null,
                "nomor_ruas_jalan" => null,
                "nomor_jaringan_irigasi" => null,
                "titik_kordinat" => null,
                "deleted_at" => null,
                "deleted_by" => null,
                "created_at" => $mapped['tanggal_perolehan'],
                "updated_at" => null,
                "luas_jalan" => null,
                "panjang" => null,
                "lebar" => null,
            ];
        } else if ($jenisAssetId == 5) {
            return [
                "assets_id" => $assetId,
                "masa_manfaat" => null,
                "sisa_masa_manfaat" => null,
                "titik_kordinat" => null,
                "deleted_at" => null,
                "deleted_by" => null,
                "created_at" => $mapped['tanggal_perolehan'],
                "updated_at" => null,
                "judul" => null,
                "spesifikasi" => null,
                "asal_daerah" => null,
                "pencipta" => null,
                "bahan" => null,
                "jenis" => null,
                "ukuran" => null,
            ];
        } else {
            return [];
        }
    }
}
