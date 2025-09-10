<?php

namespace App\Imports;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Concerns\WithEvents;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportKibA implements ToCollection, WithStartRow, WithChunkReading, ShouldQueue, WithEvents
{   
    private function parseTanggalExcel($value)
    {
        if (empty($value)) {
            return null;
        }

        // Kalau numeric → berarti Excel serial
        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('Y-m-d');
        }

        // Kalau format dd/mm/yyyy
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
            return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
        }

        // Kalau sudah yyyy-mm-dd → biarkan
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return null; // fallback
    }


    public function startRow(): int
    {
        return 8; // data mulai baris 7
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    private function mapping($row) {
        return [
            'no'              => $row[0],
            'id_pemda'       => $row[1],
            'tahun'           => $row[2],
            'kode_skpd'       => $row[7],
            'nama_skpd'       => $row[8],
            'nama_barang' => $row[15],
            'kode_barang'     => $row[17],
            'jenis_barang'    => $row[18],
            'no_register'     => $row[19],
            'luas'            => $row[20],
            'dokumen_tanggal' => $row[21],
            'dokumen_nomor'   => $row[22],
            'alamat'          => $row[23],
            'hak_tanah'       => $row[24],
            'sertifikat_tanggal' => $row[25],
            'sertifikat_nomor'   => $row[26],
            'penggunaan'      => $row[27],
            'tanggal_perolehan' => $row[28],
            'harga'           => $row[29],
            'keterangan'     => $row[30],
        ];

    }

    public function collection(Collection $rows)
    {
        $db = DB::connection('pgsql');
        try {
            $db->beginTransaction();
            foreach ($rows as $row) {
                if (empty($row[0])) {
                    continue;
                }
                $mapped = $this->mapping($row->toArray());
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
                    "tanggal_dokumen_kontrak" => $this->parseTanggalExcel($mapped['dokumen_tanggal'] ?? null),
                    "nomor_dokumen_kontrak" => $mapped['dokumen_nomor'],
                    "file_dokumen_kontrak" => null,
                    "dokumen_sumber_id" => 1,
                    "tanggal_dokumen_sumber" => null,
                    "nomor_dokumen_sumber" => null,
                    "file_dokumen_sumber" => null,
                    "nomor_dokumen_pernyataan" => null,
                    "tanggal_dokumen_pernyataan" => null,
                    "file_dokumen_pernyataan" => null,
                    "jumlah_assets" => 1,
                    "distribusi" => null,
                    "satuan" => 8,
                    "ppn" => 0,
                    "atribusi" => 0,
                    "jumlah_awal" => $mapped['harga'],
                    "jumlah_harga" => $mapped['harga'],
                    "deskripsi_status" => $mapped['hak_tanah'],
                    "created_at" => $this->parseTanggalExcel($mapped['tanggal_perolehan'] ?? null),
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
                    "jenis_asset_id" => 1,
                    "urusan_id" => $urusan->id,
                    "bidang_id" => $bidang->id,
                    "unit_id" => $unit->id,
                    "sub_unit_id" => $subUnit->id,
                    "upb_id" => $upb->id,
                    "akun_id" => 1,
                    "sub_sub_rincian_id" => $subsub->id,
                    "tanggal_buku" => $this->parseTanggalExcel($mapped['tanggal_perolehan'] ?? null),
                    "tanggal_pembelian" => $this->parseTanggalExcel($mapped['tanggal_perolehan'] ?? null),
                    "kode_lokasi" => null,
                    "kode_register" => $mapped['no_register'],
                    "nibar" => null,
                    "spesifikasi_nama_barang" => $mapped['nama_barang'],
                    "harga_satuan" => $mapped['harga'],
                    "satuan" => 8,
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
                    "deskripsi_status" => $mapped['hak_tanah'],
                    "pemanfaatan" => $mapped['penggunaan'],
                    "deleted_at" => null,
                    "deleted_by" => null,
                    "created_at" => $this->parseTanggalExcel($mapped['tanggal_perolehan'] ?? null),
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
                    "old_id_pemda" => $mapped['id_pemda'],
                ];
                
                $assetId = DB::pg('assets')->insertGetId($asset);
                $asset['id'] = $assetId;

                // insert detail asset
                $detailAsset = [
                    "assets_id" => $assetId,
                    "luas_tanah" => $mapped['luas'],
                    "satuan_luas_tanah" => 8,
                    "titik_kordinat" => null,
                    "nama_dokumen" => null,
                    "nomor_dokumen" => null,
                    "tanggal_dokumen" => null,
                    "nama_kepemilikan_dokumen" => null,
                    "utara" => null,
                    "selatan" => null,
                    "barat" => null,
                    "timur" => null,
                    "deleted_at" => null,
                    "deleted_by" => null,
                    "created_at" => null,
                ];

                $idDetailAsset = DB::pg('asset_detail_tanah')->insertGetId($detailAsset);

                // inset AssetHistory
                $assetHistory = [
                    "asset_id" => $assetId,
                    "type" => 'pengadaan',
                    "json_before" => json_encode([]), //$asset,
                    "json_after" => json_encode($asset), //$asset,
                    "created_by" => 1,
                    "created_at" => $this->parseTanggalExcel($mapped['tanggal_perolehan'] ?? null),
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
                    "keterangan" => "Pengadaan Migrasi Tanah",
                    "details" => json_encode($detailAsset),
                    "status" => "pembukuan",
                ];

                $history = DB::pg('asset_history')->insertGetId($assetHistory);
                
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
                    "created_at" => $this->parseTanggalExcel($mapped['tanggal_perolehan'] ?? null),
                    "updated_at" => null,
                    "status" => "pembukuan",
                    "masa_manfaat" => null,
                    "is_per_unit" => false,
                    "nilai_per_unit" => 0,
                ];

                DB::pg('asset_snapshots')->insert($assetSnapshot);
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function hitungTriwulanDanTahun($date)
    {
        $date = Carbon::parse($this->parseTanggalExcel($date));
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

    public static function afterImport(AfterImport $event)
    {
        // auto hit API setelah semua selesai
        Http::post('https://n8n.giafn.my.id/webhook/success-import', [
            'status' => 'success',
            'message' => 'Import KIB A selesai',
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => [self::class, 'afterImport'],
        ];
    }

    // tabel asset_dokumen
    // id
    // status
    // transaksi_id
    // cara_perolehan_id
    // sumber_dana_id
    // metode_pengadaan_id
    // mekanisme_pencairan_dana_id
    // master_pembayaran_id
    // program_id
    // kegiatan
    // sub_kegiatan_id
    // rekening_id
    // dokumen_kontrak_id
    // tanggal_dokumen_kontrak
    // nomor_dokumen_kontrak
    // file_dokumen_kontrak
    // dokumen_sumber_id
    // tanggal_dokumen_sumber
    // nomor_dokumen_sumber
    // file_dokumen_sumber
    // nomor_dokumen_pernyataan
    // tanggal_dokumen_pernyataan
    // file_dokumen_pernyataan
    // jumlah_assets
    // distribusi
    // satuan
    // ppn
    // atribusi
    // jumlah_awal
    // jumlah_harga
    // deskripsi_status
    // deleted_at
    // deleted_by
    // created_at
    // updated_at
    // kapitalisasi
    // ppn_harga
    // nama_hibah
    // no_bast
    // hibah_date

    // tabel assets
    // id
    // asset_dokumen_id
    // status
    // jenis_asset_id
    // urusan_id
    // bidang_id
    // unit_id
    // sub_unit_id
    // upb_id
    // akun_id
    // sub_sub_rincian_id
    // tanggal_buku
    // tanggal_pembelian
    // kode_lokasi
    // kode_register
    // nibar
    // spesifikasi_nama_barang
    // harga_satuan
    // satuan
    // ppn
    // atribusi
    // jumlah_awal
    // jumlah_harga
    // kondisi
    // spesifikasi_lainnya
    // keterangan_tambahan
    // foto
    // kecamatan
    // kelurahan_desa
    // jalan
    // rt_rw
    // deskripsi_status
    // pemanfaatan
    // deleted_at
    // deleted_by
    // created_at
    // updated_at
    // masa_manfaat
    // created_by
    // kapitalisasi
    // is_ditemukan
    // ppn_harga
    // nama_hibah
    // no_bast
    // hibah_date
    // cara_perolehan_id
    // sumber_dana_id
    // nilai_permeter
    // metode_pengadaan_id
    // mekanisme_pencairan_dana_id
    // master_pembayaran_id
    // ukuran_aset
    // harga_per_ukuran


    // tabel history
    // id
    // asset_id
    // type
    // json_before
    // json_after
    // created_by
    // created_at
    // updated_at
    // penambahan
    // pengurangan
    // sisa
    // upb_before_id
    // upb_after_id
    // jenis_before_id
    // jenis_after_id
    // asset_reklasifikasi_id
    // upb_id
    // keterangan
    // details
    // status

    // tabel asset_detail_tanah
    // id
    // assets_id
    // luas_tanah
    // satuan_luas_tanah
    // titik_kordinat
    // nama_dokumen
    // nomor_dokumen
    // tanggal_dokumen
    // nama_kepemilikan_dokumen
    // utara
    // selatan
    // barat
    // timur
    // deleted_at
    // deleted_by
    // created_at
    // updated_at

    // tabel asset_snapshots
    // id
    // asset_id
    // cara_perolehan_id
    // sumber_dana_id
    // is_ditemukan
    // sub_sub_rincian_id
    // kode_sub_sub_rincian
    // upb_id
    // triwulan
    // tahun
    // kondisi
    // nilai_asset
    // akumulasi_penyusutan
    // details
    // created_at
    // updated_at
    // status
    // masa_manfaat
    // is_per_unit
    // nilai_per_unit
}
