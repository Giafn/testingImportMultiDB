<?php

namespace App\Http\Controllers;

use App\Imports\ImportKibA;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;

class MigrateController extends Controller
{
    public function index()
    {
        return view('welcome');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xls,xlsx',
            'kategori' => 'required|in:A,B,C,D,E,F,G,H',
        ]);

        Excel::queueImport(new ImportKibA, $request->file('file'));

        return response()->json([
            'message' => 'Data berhasil diimport.',
        ]);
    }
}
