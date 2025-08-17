<?php

namespace App\Http\Controllers;

use App\Imports\ImportKibA;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,xls,xlsx',
            'kategori' => 'required|in:A,B,C,D,E,F,G,H',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        Excel::queueImport(new ImportKibA, $request->file('file'));

        return redirect()->back()->with('success', 'Data Mulai Di Import Harap Tunggu kami akan kabari lewat Telegram');
    }
}
