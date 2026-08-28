<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentTermController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectOfferController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/dashboard/active-project', [DashboardController::class, 'updateActiveProject'])->name('dashboard.active-project');

Route::get('/project', [ProjectController::class, 'index'])->name('project.index');
Route::post('/project', [ProjectController::class, 'store'])->name('project.store');

Route::redirect('/penawaran', '/kategori-pekerjaan');
Route::get('/kategori-pekerjaan', [ProjectOfferController::class, 'index'])->name('kategori-pekerjaan.index');
Route::post('/kategori-pekerjaan', [ProjectOfferController::class, 'store'])->name('kategori-pekerjaan.store');
Route::put('/kategori-pekerjaan/{projectOffer}', [ProjectOfferController::class, 'update'])->name('kategori-pekerjaan.update');

Route::view('/kategori', 'pages.placeholder', [
    'title' => 'Kategori Transaksi',
    'description' => 'Halaman master kategori untuk uang masuk dan uang keluar.',
    'icon' => 'ti ti-tag',
])->name('kategori.index');

Route::get('/uang-masuk', [TransactionController::class, 'createIncome'])->name('uang-masuk.index');

Route::get('/uang-keluar', [TransactionController::class, 'createExpense'])->name('uang-keluar.index');
Route::post('/transaksi', [TransactionController::class, 'store'])->name('transactions.store');

Route::redirect('/kelompok-pembayaran', '/termin-pembayaran')->name('kelompok-pembayaran.index');
Route::get('/termin-pembayaran', [PaymentTermController::class, 'index'])->name('termin-pembayaran.index');
Route::patch('/termin-pembayaran/{workItem}', [PaymentTermController::class, 'update'])->name('termin-pembayaran.update');

Route::view('/bukti-transaksi', 'pages.placeholder', [
    'title' => 'Bukti Transaksi',
    'description' => 'Halaman arsip bukti transaksi dengan upload gambar yang nanti bisa otomatis dikompresi.',
    'icon' => 'ti ti-photo',
])->name('bukti-transaksi.index');

Route::view('/laporan', 'pages.placeholder', [
    'title' => 'Laporan',
    'description' => 'Halaman rekap uang masuk dan uang keluar dengan export PDF atau Excel.',
    'icon' => 'ti ti-file-report',
])->name('laporan.index');

Route::view('/pengaturan', 'pages.placeholder', [
    'title' => 'Pengaturan',
    'description' => 'Halaman pengaturan user, profile aplikasi, dan preferensi sistem.',
    'icon' => 'ti ti-settings',
])->name('pengaturan.index');
