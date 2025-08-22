<?php

namespace App\Http\Controllers;

use App\Imports\ImportKibA;
use App\Jobs\ImportKibB;
use App\Jobs\ImportKibC;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use OpenSpout\Reader\XLSX\Reader;

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

        if ($request->kategori === 'A') {
            Excel::queueImport(new ImportKibA, $request->file('file'));
        } else if ($request->kategori === 'B') {
            $file = $request->file('file');
            
            $path = $file->storeAs(
                'imports',
                uniqid() . '.' . $file->getClientOriginalExtension()
            );

            // path full
            $fullPath = storage_path('app/private/' . $path);
            ImportKibB::dispatch($fullPath);
        } else if ($request->kategori === 'C') {
            $file = $request->file('file');
            
            $path = $file->storeAs(
                'imports',
                uniqid() . '.' . $file->getClientOriginalExtension()
            );

            // path full
            $fullPath = storage_path('app/private/' . $path);
            ImportKibC::dispatch($fullPath);
        }

        return redirect()->back()->with('success', 'Data Mulai Di Import Harap Tunggu kami akan kabari lewat Telegram');
    }
}
