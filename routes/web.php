<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\PayerController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'index')->name('index');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::view('/dashboard', 'index')->middleware('auth')->name('dashboard');
Route::view('/transactions', 'index')->middleware('auth')->name('transactions');
Route::view('/payers', 'index')->middleware('auth')->name('payers');
Route::get('/data/payers', [PayerController::class, 'index'])->middleware('auth')->name('payers.index');
Route::view('/tags', 'index')->middleware('auth')->name('tags');
Route::view('/reports', 'index')->middleware('auth')->name('reports');
Route::get('/reports/download', [ReportController::class, 'download'])->middleware('auth')->name('reports.download');
Route::get('/data/tags', [TagController::class, 'index'])->middleware('auth')->name('tags.index');
Route::post('/data/tags', [TagController::class, 'store'])->middleware('auth')->name('tags.store');
Route::patch('/data/tags/{tag}', [TagController::class, 'update'])->whereNumber('tag')->middleware('auth')->name('tags.update');
Route::delete('/data/tags/{tag}', [TagController::class, 'destroy'])->whereNumber('tag')->middleware('auth')->name('tags.destroy');
Route::redirect('/page', '/dashboard')->middleware('auth');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('auth')->name('expenses.index');
Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('auth')->name('expenses.store');

Route::get('/incomes', [IncomeController::class, 'index'])->middleware('auth')->name('incomes.index');
Route::post('/incomes', [IncomeController::class, 'store'])->middleware('auth')->name('incomes.store');

Route::patch('/expenses/{expense}', [ExpenseController::class, 'update'])->whereNumber('expense')->middleware('auth')->name('expenses.update');
Route::patch('/incomes/{income}', [IncomeController::class, 'update'])->whereNumber('income')->middleware('auth')->name('incomes.update');

Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->whereNumber('expense')->middleware('auth')->name('expenses.destroy');

Route::delete('/incomes/{income}', [IncomeController::class, 'destroy'])->whereNumber('income')->middleware('auth')->name('incomes.destroy');

Route::view('/settings', 'index')->middleware('auth')->name('settings');
Route::patch('/settings', [SettingsController::class, 'update'])->middleware('auth')->name('settings.update');
