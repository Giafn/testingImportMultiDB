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

class ProcessKibBChunk implements ShouldQueue
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
                $data = $this->formatData($cells);
                if ($data) {
                    $this->storeData($data);
                }
                $lastNumberRow = $cells[0];
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            Http::post('https://n8n.giafn.my.id/webhook/success-import', [
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            throw $e;
        }

        Http::post('https://n8n.giafn.my.id/webhook/success-import', [
            'status' => 'success',
            'message' => 'Import Chunk KIB B selesai ' . $firstNumberRow . ' sampai ' . $lastNumberRow
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
            "jenis_asset_id" => 2,
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
            "masa_manfaat" => $mapped['masa_manfaat'],
            "created_by" => null,
            "kapitalisasi" => null,
            "is_ditemukan" => false,
            "ppn_harga" => null,
            "nama_hibah" => null,
            "no_bast" => null,
            "hibah_date" => null,
            "cara_perolehan_id" => $mapped['asal_usul'] == "Hibah" ? 2 : 1,
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

        $bahanId = DB::pg('master_bahan_aset')->where('nama', $mapped['bahan'])->first();
        if (empty($bahanId)) {
            $bahan = [
                "nama" => $mapped['bahan'],
                "is_permanent" => 0
            ];
            $bahanId = DB::pg('master_bahan_aset')->insertGetId($bahan);
        } else {
            $bahanId = $bahanId->id;
        }
    
        // insert detail asset
        $detailAsset = [
            "assets_id" => $assetId,
            "masa_manfaat" => $mapped['masa_manfaat'],
            "sisa_masa_manfaat" => null,
            "merek_tipe" => $mapped['merk'] . '/' . $mapped['type'],
            "nomor_polisi" => $mapped['polisi'],
            "nomor_bpkb" => $mapped['bpkb'],
            "nama_pemilik" => null,
            "tipe_kendaraan" => null,
            "jenis_kendaraan" => null,
            "model_kendaraan" => null,
            "tahun_pembuatan" => null,
            "isi_silinder" => $mapped['cc'],
            "nomor_rangka_nik_vin" => $mapped['rangka'],
            "nomor_mesin" => $mapped['mesin'],
            "warna" => null,
            "warna_tnkb" => null,
            "deleted_at" => null,
            "deleted_by" => null,
            "created_at" => null,
            "updated_at" => null,
            "bahan_kendaraan" => $bahanId,
        ];
    
        DB::pg('asset_detail_peralatan')->insertGetId($detailAsset);
    
        // inset AssetHistory
        $assetHistory = [
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
            "details" => json_encode($detailAsset),
            "status" => "pembukuan",
        ];
    
        DB::pg('asset_history')->insertGetId($assetHistory);
        
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
        $this->createPenyusutan($assetId, "Inisiasi Penyusutan Migrasi PM", $mapped['tanggal_perolehan']);
    }

    private function formatData($cells) 
    {
        try {
            return [
                    'no' => $cells[0] ?? null,
                    'id_barang' => $cells[1] ?? null,
                    'tahun' => $cells[2] ?? null,
                    'kode_skpd' => $cells[7] ?? null,
                    'nama_skpd' => $cells[8] ?? null,
                    'kelompok_barang' => $cells[14] ?? null,
                    'kode_barang' => $cells[17] ?? null,
                    'jenis_barang' => $cells[18] ?? null,
                    'no_register' => $cells[19] ?? null,
                    'merk' => $cells[20] ?? null,
                    'type' => $cells[21] ?? null,
                    'cc' => $cells[22] ?? null,
                    'bahan' => $cells[23] ?? null,
                    'tanggal_perolehan' => $this->formatDate($cells[24] ?? null),
                    'pabrik' => $cells[25] ?? null,
                    'rangka' => $cells[26] ?? null,
                    'mesin' => $cells[27] ?? null,
                    'polisi' => $cells[28] ?? null,
                    'bpkb' => $cells[29] ?? null,
                    'asal_usul' => $cells[30] ?? null,
                    'kondisi' => $cells[31] ?? null,
                    'masa_manfaat' => $cells[32] ?? null,
                    'harga' => $cells[33] ?? null,
                    'keterangan' => $cells[34] ?? null,
                    'akumulasi_1_januari_2023' => $cells[35] ?? null,
                    'penyusutan_semester_1' => $cells[36] ?? null,
                    'penyusutan_semester_2' => $cells[37] ?? null,
                    'akumulasi_31_desember_2023' => $cells[38] ?? null,
                    'nilai_buku' => $cells[39] ?? null,
                    'sisa_masa_manfaat' => $cells[40] ?? null,
                ];
        } catch (\Exception $e) {
            dd($e);
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

    private function createPenyusutan($idAsset, $keterangan, $customCreatedAt = null)
    {
        $asset = DB::pg('assets')
            ->join('sub_sub_rincian_objek_assets', 'sub_sub_rincian_objek_assets.id', '=', 'assets.sub_sub_rincian_id')
            ->where('assets.id', $idAsset)
            ->whereIn('jenis_asset_id', [2, 3, 4])
            ->select(
                'assets.*',
                'sub_sub_rincian_objek_assets.kode_kelompok',
                'sub_sub_rincian_objek_assets.kode_jenis'
            )
            ->first();

        if (!$asset || $asset->masa_manfaat <= 0) {
            return [
                'status' => 'success',
                'message' => 'Data asset tidak ditemukan'
            ];
        }

        $masaManfaat = $asset->masa_manfaat;
        $depresiasiPerbulan = $asset->jumlah_harga / $masaManfaat;
        $nilaiBuku = $asset->jumlah_harga;
        $tanggalSelesai = Carbon::createFromDate($asset->tanggal_pembelian)
            ->addMonths($masaManfaat)
            ->endOfMonth();

        // cek jika tidak ada penyusutan sebelumnya
        $cek = DB::pg('asset_penyusutan')->where('asset_id', $idAsset)->count();
        if ($cek <= 0) {
            DB::pg('asset_penyusutan')->insert([
                'asset_id'           => $idAsset,
                'nilai_buku'         => $nilaiBuku,
                'depresiasi_perbulan'=> $depresiasiPerbulan,
                'masa_manfaat'       => $masaManfaat,
                'tanggal_mulai'      => Carbon::createFromDate($asset->tanggal_pembelian)->startOfMonth(),
                'tanggal_selesai'    => $tanggalSelesai,
                'nilai_perolehan'    => (int)$asset->jumlah_harga,
                'keterangan'         => $keterangan,
                'jenis_asset'        => $asset->kode_jenis,
                'type_asset'         => $asset->kode_kelompok == 3 ? 'asset_tetap' : 'asset_lainnya',
                'created_at'         => $customCreatedAt 
                                        ? Carbon::parse($customCreatedAt)->startOfMonth()
                                        : now(),
                'updated_at'         => $customCreatedAt 
                                        ? Carbon::parse($customCreatedAt)->startOfMonth()
                                        : now(),
            ]);
        }

        return [
            'status' => 'success',
            'message' => 'Berhasil menambah penyusutan'
        ];
    }
}
