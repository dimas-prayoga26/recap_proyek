<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

Route::view('/project', 'pages.placeholder', [
    'title' => 'Project / Kategori Utama',
    'description' => 'Halaman master untuk memisahkan transaksi berdasarkan project, tim, atau kategori utama.',
    'icon' => 'ti ti-folders',
])->name('project.index');

Route::view('/kategori', 'pages.placeholder', [
    'title' => 'Kategori Transaksi',
    'description' => 'Halaman master kategori untuk uang masuk dan uang keluar.',
    'icon' => 'ti ti-tag',
])->name('kategori.index');

Route::view('/uang-masuk', 'pages.transaction-form', [
    'mode' => 'masuk',
    'title' => 'Input Uang Masuk',
])->name('uang-masuk.index');

Route::view('/uang-keluar', 'pages.transaction-form', [
    'mode' => 'keluar',
    'title' => 'Input Uang Keluar',
])->name('uang-keluar.index');

Route::view('/kelompok-pembayaran', 'pages.placeholder', [
    'title' => 'Kelompok Pembayaran',
    'description' => 'Halaman untuk mengatur satu kuitansi yang dibagi menjadi beberapa termin pembayaran.',
    'icon' => 'ti ti-list-check',
])->name('kelompok-pembayaran.index');

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
