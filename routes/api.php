<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Bridge;
use App\Http\Controllers\Api\Sso\UserInfoController;
use App\Http\Middleware\RecordBridgeApiRequest;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckToken;

/*
|--------------------------------------------------------------------------
| SSO — identitas pemegang token
|--------------------------------------------------------------------------
|
| Dua kanal ini sengaja terpisah. Campus Bridge di bawah memakai token Sanctum
| yang diterbitkan untuk *aplikasi* konsumen dan membaca data kampus secara
| umum. Endpoint ini memakai token OAuth yang diterbitkan atas nama *seseorang*
| yang menekan tombol "Izinkan", dan hanya bercerita tentang orang itu.
|
| Hanya terdaftar bila SSO dinyalakan — permukaan yang tidak dipakai tidak
| perlu menyala.
|
*/

if (config('sso.enabled')) {
    Route::middleware(['auth:api', CheckToken::using('identitas')])
        ->get('sso/userinfo', UserInfoController::class)
        ->name('sso.userinfo');
}

/*
|--------------------------------------------------------------------------
| Campus Bridge — Read API v1
|--------------------------------------------------------------------------
|
| The only sanctioned way for an external system to read Open Academic data.
| The database is never shared: consumers authenticate with a Sanctum token,
| every endpoint declares the scope it needs, and traffic is logged for the
| Bridge console.
|
| Contract lives in docs/openapi/bridge.yaml and is spec-first — change the
| spec, then the code.
|
*/

Route::prefix('bridge/v1')
    ->name('bridge.')
    ->middleware([
        'auth:sanctum',
        RecordBridgeApiRequest::class,
        'throttle:'.config('bridge.api.rate_limit'),
    ])
    ->group(function (): void {

        Route::middleware('bridge.scope:students.read')->group(function (): void {
            Route::get('students', [Bridge\StudentController::class, 'index'])->name('students.index');
            Route::get('students/statistics', [Bridge\StudentController::class, 'statistics'])->name('students.statistics');
            Route::get('students/{uuid}', [Bridge\StudentController::class, 'show'])->name('students.show');
        });

        Route::middleware('bridge.scope:lecturers.read')->group(function (): void {
            Route::get('lecturers', [Bridge\LecturerController::class, 'index'])->name('lecturers.index');
            Route::get('lecturers/{uuid}', [Bridge\LecturerController::class, 'show'])->name('lecturers.show');
        });

        Route::middleware('bridge.scope:classes.read')->group(function (): void {
            Route::get('classes', [Bridge\ClassController::class, 'index'])->name('classes.index');
            Route::get('classes/{uuid}', [Bridge\ClassController::class, 'show'])->name('classes.show');
        });

        Route::middleware('bridge.scope:activities.read')->group(function (): void {
            Route::get('student-activities', [Bridge\StudentActivityController::class, 'index'])
                ->name('activities.index');
        });

        Route::middleware('bridge.scope:graduates.read')->group(function (): void {
            Route::get('graduates', [Bridge\GraduateController::class, 'index'])->name('graduates.index');
        });

        // Cacahan fakta lintas entitas. Membutuhkan seluruh scope baca yang
        // menyusunnya — endpoint ringkas tidak boleh menjadi jalan pintas
        // membaca data yang scope-nya tidak diberikan.
        Route::middleware([
            'bridge.scope:students.read',
            'bridge.scope:lecturers.read',
            'bridge.scope:classes.read',
            'bridge.scope:activities.read',
            'bridge.scope:graduates.read',
        ])->get('iku-data', Bridge\IkuDataController::class)->name('iku-data');

        // Menyebut dosen, jadi butuh scope dosen juga — endpoint agregat tidak
        // boleh menjadi jalan memetakan nama dosen yang scope-nya tidak diberikan.
        Route::middleware([
            'bridge.scope:evaluations.read',
            'bridge.scope:lecturers.read',
        ])->get('teaching-evaluations', Bridge\TeachingEvaluationController::class)
            ->name('teaching-evaluations');

        // Alasan yang sama: bentuk per-dosen dari endpoint ini adalah berkas
        // kepegawaian seseorang, bukan sekadar cacahan.
        Route::middleware([
            'bridge.scope:workload.read',
            'bridge.scope:lecturers.read',
        ])->get('lecturer-workload', Bridge\LecturerWorkloadController::class)
            ->name('lecturer-workload');

        Route::middleware('bridge.scope:terms.read')->group(function (): void {
            Route::get('academic-terms', [Bridge\AcademicTermController::class, 'index'])->name('terms.index');
            Route::get('academic-terms/current', [Bridge\AcademicTermController::class, 'current'])->name('terms.current');
        });
    });
