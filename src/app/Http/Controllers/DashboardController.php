<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $files = DB::table('statement_files')->where('status', 'completed');
        $stats = (clone $files)
            ->selectRaw('COUNT(*) AS total, COUNT(allocation_amount) AS ready')
            ->first();

        $files->select('id', 'original_filename', 'payment_year', 'payment_period', 'allocation_amount')
            ->orderByDesc('payment_year')
            ->orderByRaw('CAST(payment_period AS UNSIGNED) DESC')
            ->orderByDesc('id');

        $recent = (clone $files)->limit(6)->get();
        $pending = $files->whereNull('allocation_amount')->paginate(12)->fragment('pending');

        return view('dashboard', compact('stats', 'recent', 'pending'));
    }
}
