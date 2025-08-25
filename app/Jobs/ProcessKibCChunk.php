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

class ProcessKibCChunk implements ShouldQueue
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
            $countGroup = 0;
            foreach ($this->rows as $group) {
                $assetId = null;
                $upbId = null;
                $groupCapital = [];
                foreach ($group as $cells) {
                    if ($firstNumberRow === null) {
                        $firstNumberRow = $cells[0];
                    }
                    $data = $this->formatData($cells);
                    if ($data['is_parent']) {
                        $result = $this->storeData($data);
                        $assetId = $result['assetId'];
                        $upbId = $result['upbId'];
                    } else {
                        $countGroup++;
                        $groupCapital[$assetId][] = $data;
                    }
                    $lastNumberRow = $cells[0];
                }
                if (count($groupCapital) > 0) {
                    $sortedAsc = collect($groupCapital[$assetId])
                        ->sortBy(fn($item) => \Carbon\Carbon::parse($item['tanggal_perubahan']))
                        ->toArray();
                    $asset = DB::pg('assets')->where('id', $assetId)->first();
                    $details = DB::pg('asset_detail_gedung')->where('assets_id', $assetId)->first();
                    $nilaiAsset = $asset->jumlah_harga;
                    $masaManfaat = $asset->masa_manfaat;
                    foreach ($sortedAsc as $item) {
                        $nilaiAsset += $item['harga'];
                        $masaManfaat += (int) $item['masa_manfaat'];
                        $this->storeKapitalisasi($asset, $details, $item, $upbId, $nilaiAsset, $masaManfaat);
                    }
                }
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
            'message' => 'Import Chunk KIB C selesai ' . $firstNumberRow . ' sampai ' . $lastNumberRow
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
            "jenis_asset_id" => 3,
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
            "keterangan_tambahan" => $mapped['keterangan'],
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
            "cara_perolehan_id" => 1,
            "sumber_dana_id" => 1,
            "nilai_permeter" => null,
            "metode_pengadaan_id" => 1,
            "mekanisme_pencairan_dana_id" => 1,
            "master_pembayaran_id" => 1,
            "ukuran_aset" => null,
            "harga_per_ukuran" => null,
        ];
        
        $assetId = DB::pg('assets')->insertGetId($asset);
        $asset['id'] = $assetId;
    
        // insert detail asset
        $detailAsset = [
            "assets_id" => $assetId,
            "masa_manfaat" => $mapped['masa_manfaat'],
            "jumlah_lantai" => (int) $mapped['details']['jumlah_lantai'],
            "luas_gedung" => $mapped['details']['luas_gedung'] == "NULL" ? 0 : (float) $mapped['details']['luas_gedung'],
            "titik_kordinat" => null,
            "status_kepemilikan" => null,
        ];
    
        DB::pg('asset_detail_gedung')->insertGetId($detailAsset);
    
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
            "keterangan" => "Pengadaan Migrasi Gedung",
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

        return [
            'assetId' => $assetId,
            'upbId' => $upb->id
        ];
    }

    private function storeKapitalisasi($asset, $details, $data, $upbId, $nilaiSekarang, $masaManfaatSekarang) {
        $penambahanMasaManfaat = $data['masa_manfaat'] ? (int) $data['masa_manfaat'] : 0;
        DB::pg('assets')->where('id', $asset->id)->update([
            'masa_manfaat' => $masaManfaatSekarang,
            'jumlah_harga' => $nilaiSekarang
        ]);

        // insert asset history
        $assetHistoryId = DB::pg('asset_history')->insertGetId([
            'asset_id'      => $asset->id,
            'upb_id'        => $upbId,
            'type'          => 'rehab',
            'json_before'   => json_encode([]), // simpan sebagai JSON
            'json_after'    => json_encode([
                'spesisfikasi_nama_barang' => $asset->spesifikasi_nama_barang,
                'sub_sub_rincian_id'       => $asset->sub_sub_rincian_id,
                'nominal'                  => $asset->jumlah_harga + $data['harga'],
                'keterangan'               => $data['keterangan'],
                'jenis_asset_id'           => $asset->jenis_asset_id,
                'kode_register'            => $asset->kode_register,
                'satuan'                   => $asset->satuan,
                'kondisi'                  => $asset->kondisi,
                'is_ditemukan'             => $asset->is_ditemukan,
                'masa_manfaat'             => $masaManfaatSekarang,
            ]),
            'pengurangan'   => 0,
            'penambahan'    => $data['harga'],
            'sisa'          => $nilaiSekarang,
            'created_by'    => 1,
            'details'       => json_encode($details), // kalau kolom details JSON
            'keterangan'    => 'Penambahan kapitalisasi rehab - ' . ($data['keterangan'] ?: ''),
            'status'        => "pembukuan",
            'created_at'    => $data['tanggal_perubahan'],
            'updated_at'    => $data['tanggal_perubahan'],
        ]);

        $penyusutan = $this->updatePenyusutan($asset->id, $data['harga'], $penambahanMasaManfaat, "Rehab/penambahan kapitalisasi Rp. " . number_format($data['harga'], 0, ',', '.'), $data['tanggal_perubahan']);

        // // Insert ke asset_kapitalisasi
        DB::pg('asset_kapitalisasi')->insert([
            'asset_id'   => $asset->id,
            'nominal'    => $data['harga'],
            'file'       => null,
            'keterangan' => 'Inputan penambahan nilai rehab - ' . ($data['keterangan'] ?: ''),
            'rehab_date' => $data['tanggal_perubahan'],
            'type'       => 'rehab',
            'created_at' => $data['tanggal_perubahan'],
            'updated_at' => $data['tanggal_perubahan'],
        ]);
    }

    private function formatData($cells) 
    {
        try {
            return [
                    'is_parent' => $cells[21] !== '' ? false : true,
                    'id_barang' => $cells[0] ?? null,
                    'tahun' => $cells[3] ?? null,
                    'kode_skpd' => $cells[4] . '.' . $cells[5] . '.' . $cells[6] . '.' . $cells[7] ?? null,
                    'nama_skpd' => $cells[8] ?? null,
                    'kelompok_barang' => $cells[14] ?? null,
                    'kode_barang' => $cells[9] . '.' . $cells[10] . '.' . $cells[11] . '.' . $cells[12] . '.' . $cells[13] . '.' . $cells[15] . '.' . $cells[16] ?? null,
                    'jenis_barang' => $cells[17] ?? null,
                    'no_register' => $cells[18] ?? null,
                    'tanggal_perolehan' => $this->formatDate($cells[19] ?? null),
                    'tanggal_pembukuan' => $this->formatDate($cells[20] ?? null),
                    'tanggal_perubahan' => $this->formatDate($cells[21] ?? null),
                    'asal_usul' => $cells[30] ?? null,
                    'kondisi' => $cells[31] ?? null,
                    'masa_manfaat' => $cells[40] ?? null,
                    'harga' => $cells[33] ?? null,
                    'penambahan' => $cells[34] ?? null,
                    'harga_saat_ini' => $cells[35] ?? null,
                    'nilai_buku_2024' => $cells[38] ?? null,
                    'keterangan' => $cells[41] ?? null,
                    'is_beton' => $cells[24] !== '' ? true : false,
                    'details' => [
                        "jumlah_lantai" => $cells[23] == "Bertingkat" ? 2 : 1,
                        "luas_gedung" => $cells[25] ?? null,
                        "titik_kordinat" => null,
                    ]
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

    // update penyusutan
    private function updatePenyusutan($idAsset, $penambahanNilai, $penambahanMasaManfaat, $keterangan, $customCreatedAt = null)
    {
        $db = DB::connection('pgsql');
        try {
            $db->beginTransaction();

            $penambahanNilai = (int) $penambahanNilai;
            $asset = DB::pg('assets')
                ->join('sub_sub_rincian_objek_assets', 'sub_sub_rincian_objek_assets.id', '=', 'assets.sub_sub_rincian_id')
                ->where('assets.id', $idAsset)
                ->whereIn('jenis_asset_id', [2, 3, 4])
                ->select('assets.*', 'sub_sub_rincian_objek_assets.kode_kelompok', 'sub_sub_rincian_objek_assets.kode_jenis')
                ->first();
    
            $nilaiPerolehan = (int) DB::pg('asset_history')->where('asset_id', $idAsset)->orderBy('created_at', 'asc')
                ->where('type', 'pengadaan')
                ->select('penambahan')
                ->first()->penambahan;
                
            
            $totalKapitalisasiAwal = DB::pg('asset_kapitalisasi')->where('asset_id', $idAsset)->sum('nominal');
            $totalKapitalisasi = $totalKapitalisasiAwal + $penambahanNilai;
    
            $cekAkumulasi = $this->getAkumulasiPenyusutan($idAsset, $customCreatedAt ?? date('Y-m-d'));
    
            $penyusutanTerakhir = DB::pg('asset_penyusutan')
                ->where('asset_id', $idAsset)
                ->orderByDesc('tanggal_selesai')
                ->first();
            
            $tanggalSelesaiBaru = $customCreatedAt
                ? Carbon::parse($customCreatedAt)->endOfMonth()
                : Carbon::now()->endOfMonth();
    
            DB::pg('asset_penyusutan')
                ->where('id', $penyusutanTerakhir->id)
                ->update([
                    'tanggal_selesai' => $tanggalSelesaiBaru,
                    'updated_at'      => now(),
                ]);
    
            $penyusutanTerakhir = DB::pg('asset_penyusutan')
                ->where('asset_id', $idAsset)
                ->orderByDesc('tanggal_selesai')
                ->first();
            
            // hitung nilai buku dan masa manfaat dalam bulan yang baru menjadi acuan depresiasi perbulan
            $tanggalBeli = Carbon::parse($asset->tanggal_pembelian)->endOfMonth();
            $diff = $tanggalBeli->diffInMonths(Carbon::create($customCreatedAt)->endOfMonth());

            // pastikan semua angka dalam string agar cocok dengan BCMath
            $akumulasiPenyusutanBulanSebelumnya = bcsub((string)$cekAkumulasi, (string)$penyusutanTerakhir->depresiasi_perbulan, 8);

            $nilaiBukuBulanSebelumnya = bcsub(
                bcadd((string)$nilaiPerolehan, (string)$totalKapitalisasiAwal, 8),
                (string)$akumulasiPenyusutanBulanSebelumnya,
                8
            );

            $nilaiPerolehanUntukHitungPenyusutanBaru = bcadd(
                (string)$nilaiBukuBulanSebelumnya,
                (string)$penambahanNilai,
                8
            );

            $TotalMasaManfaat = bcadd((string)$penyusutanTerakhir->masa_manfaat, (string)$penambahanMasaManfaat, 8);
            $sisaMasaManfaat  = bcsub((string)$TotalMasaManfaat, (string)$diff, 8);
            $sisaMasaManfaat  = (string)round((float)$sisaMasaManfaat);

            // bagi dengan presisi tinggi
            $depresiasiPerbulan = bcdiv((string)$nilaiPerolehanUntukHitungPenyusutanBaru, (string)$sisaMasaManfaat, 8);

            $akumulasiPenyusutan = bcadd((string)$akumulasiPenyusutanBulanSebelumnya, (string)$depresiasiPerbulan, 8);

            $nilaiBukuBaru = bcsub(
                bcadd((string)$nilaiPerolehan, (string)$totalKapitalisasi, 8),
                (string)$akumulasiPenyusutan,
                8
            );

            $cek = $nilaiBukuBaru + $penambahanNilai;
            if ($cek > 0) {
                $masaManfaat = $asset->masa_manfaat;
                $sisaMasaManfaat = $masaManfaat - $diff;
    
                $tanggalMulai = $customCreatedAt ? Carbon::parse($customCreatedAt)->startOfMonth() : Carbon::now()->startOfMonth();
                $tanggalSelesai = $customCreatedAt ? Carbon::parse($customCreatedAt)->addMonths($sisaMasaManfaat)->endOfMonth() : Carbon::now()->addMonths($sisaMasaManfaat)->endOfMonth();
        
                DB::pg('asset_penyusutan')->insertGetId([
                    'asset_id'                => $idAsset,
                    'nilai_buku'              => $nilaiBukuBaru,
                    'depresiasi_perbulan'     => $depresiasiPerbulan,
                    'masa_manfaat'            => (int) $masaManfaat,
                    'tanggal_mulai'           => $tanggalMulai,
                    'tanggal_selesai'         => $tanggalSelesai,
                    'penambahan_nilai'        => $penambahanNilai,
                    'penambahan_masa_manfaat' => $penambahanMasaManfaat,
                    'nilai_perolehan'         => $penyusutanTerakhir->nilai_perolehan + $penambahanNilai,
                    'keterangan'              => $keterangan,
                    'jenis_asset'             => $asset->kode_jenis,
                    'type_asset'              => $asset->kode_kelompok == 3 ? 'asset_tetap' : 'asset_lainnya',
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]);
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return [
            'status' => 'success',
            'message' => 'Berhasil memperbarui penyusutan'
        ];
    }

    // format list penyusutan
    private function formatListPenyusutan($penyusutans, $tahun = null, $now = null)
    {
        if (!$tahun) {
            $tahun = Carbon::now()->year;
        }

        if (!$now) {
            $now = Carbon::now()->startOfMonth();
        } else {
            $now = Carbon::parse($now)->startOfMonth();
        }

        $arr = [];
        $first = true;

        foreach ($penyusutans as $key => $penyusutan) {
            // pastikan semua angka string untuk BCMath
            $nilaiBuku = (string)$penyusutan->nilai_buku;
            $penguranganPerBulan = (string)$penyusutan->depresiasi_perbulan;

            $tanggalMulai   = Carbon::parse($penyusutan->tanggal_mulai)->startOfMonth();
            $tanggalSelesai = Carbon::parse($penyusutan->tanggal_selesai)->endOfMonth();

            $data = [];
            $nilaiSisa = $nilaiBuku;
            $currentDate = $tanggalMulai->copy()->startOfMonth();

            // dikurangi 1 bulan hanya sekali
            if ($first) {
                $currentDate->subMonth();
                $first = false;
            }

            while ($currentDate <= $tanggalSelesai) {
                if ($currentDate->year == $tahun && $currentDate >= $tanggalMulai && bccomp($nilaiSisa, "-1", 8) === 1) {

                    if ($currentDate->month == $tanggalMulai->month && $currentDate->year == $tanggalMulai->year) {
                        $penyusutan->keterangan = $penyusutan->keterangan;
                        $penambahan_nilai = $penyusutan->penambahan_nilai ?? '';
                        $penambahan_masa_manfaat = $penyusutan->penambahan_masa_manfaat ?? '';
                    } else {
                        $penyusutan->keterangan = '';
                        $penambahan_nilai = '';
                        $penambahan_masa_manfaat = '';
                    }

                    $isNow = ($currentDate->month == $now->month && $currentDate->year == $now->year);

                    // hitung akumulasi pakai bcsub
                    $akumulasi = bcsub((string)$penyusutan->nilai_perolehan, $nilaiSisa, 8);

                    $data[] = [
                        'nilai_perolehan'         => $penyusutan->nilai_perolehan,
                        'bulan'                   => $currentDate->format('m'),
                        'pengurangan'             => $penguranganPerBulan,
                        'akumulasi_pengurangan'   => $akumulasi,
                        'nilai_awal'              => $nilaiBuku,
                        'nilai_sisa'              => $nilaiSisa,
                        'penambahan_nilai'        => $penambahan_nilai !== 0 ? $penambahan_nilai : '',
                        'penambahan_masa_manfaat' => $penambahan_masa_manfaat !== 0 ? $penambahan_masa_manfaat : '',
                        'keterangan'              => $penyusutan->keterangan,
                        'is_now'                  => $isNow,
                    ];
                }

                $nilaiBuku = $nilaiSisa;
                $nilaiSisa = bcsub($nilaiSisa, $penguranganPerBulan, 8);
                $currentDate->addMonth();
            }

            $arr[$key] = $data;
        }

        // flatten array
        $list = [];
        foreach ($arr as $data) {
            foreach ($data as $value) {
                $list[] = $value;
            }
        }

        return $list;
    }

    private function getAkumulasiPenyusutan($id, $date = null)
    {
        if (!$date) {
            $date = Carbon::now();
        } else {
            $date = Carbon::parse($date);
        }

        $penyusutans = DB::pg('asset_penyusutan')->where('asset_id', $id)->get();
        $tahun = Carbon::create($date)->year;

        $list = $this->formatListPenyusutan($penyusutans, $tahun, $date);

        $collection = collect($list);

        // Mendapatkan elemen terakhir dengan is_now = true
        $lastIsNowTrue = $collection->where('is_now', true)->last();

        $akumulasiPengurangan = 0;
        // Memeriksa apakah elemen ditemukan dan mendapatkan akumulasi_pengurangan
        if ($lastIsNowTrue) {
            $akumulasiPengurangan = $lastIsNowTrue['akumulasi_pengurangan'];
        }
        
        return $akumulasiPengurangan;
    }

}
