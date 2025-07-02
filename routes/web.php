<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SessionsController;
use App\Http\Controllers\MoodleUserController;
use App\Http\Controllers\MoodleReportController;
use App\Http\Controllers\MoodleCertificateController;
            

Route::get('/', function () {return redirect('sign-in');})->middleware('guest');

// Dashboard routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/export/users-by-country', [DashboardController::class, 'exportUsersByCountry'])->name('dashboard.export.users-by-country');
    // Add other dashboard-related export routes here
});

Route::get('sign-up', [RegisterController::class, 'create'])->middleware('guest')->name('register');
Route::post('sign-up', [RegisterController::class, 'store'])->middleware('guest');
Route::get('sign-in', [SessionsController::class, 'create'])->middleware('guest')->name('login');
Route::post('sign-in', [SessionsController::class, 'store'])->middleware('guest');
Route::post('verify', [SessionsController::class, 'show'])->middleware('guest');
Route::post('reset-password', [SessionsController::class, 'update'])->middleware('guest')->name('password.update');
Route::get('verify', function () {
	return view('sessions.password.verify');
})->middleware('guest')->name('verify'); 
Route::get('/reset-password/{token}', function ($token) {
	return view('sessions.password.reset', ['token' => $token]);
})->middleware('guest')->name('password.reset');

Route::post('sign-out', [SessionsController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('profile', [ProfileController::class, 'create'])->middleware('auth')->name('profile');
Route::post('user-profile', [ProfileController::class, 'update'])->middleware('auth');
Route::group(['middleware' => 'auth'], function () {
	Route::get('billing', function () {
		return view('pages.billing');
	})->name('billing');
	Route::get('tables', function () {
		return view('pages.tables');
	})->name('tables');
	Route::get('rtl', function () {
		return view('pages.rtl');
	})->name('rtl');
	Route::get('virtual-reality', function () {
		return view('pages.virtual-reality');
	})->name('virtual-reality');
	Route::get('notifications', function () {
		return view('pages.notifications');
	})->name('notifications');
	Route::get('static-sign-in', function () {
		return view('pages.static-sign-in');
	})->name('static-sign-in');
	Route::get('static-sign-up', function () {
		return view('pages.static-sign-up');
	})->name('static-sign-up');
	Route::get('user-management', function () {
		return view('pages.laravel-examples.user-management');
	})->name('user-management');
	Route::get('user-profile', function () {
		return view('pages.laravel-examples.user-profile');
	})->name('user-profile');

    // Moodle User Management Routes
    Route::prefix('moodle')->name('moodle.')->group(function () {
        Route::resource('users', MoodleUserController::class)->only(['index', 'show']);
        // Later, we can add routes for create, store, edit, update, destroy if direct manipulation via BackOffice is needed

        // Routes for Mass User Creation
        Route::get('users/mass-create', [MoodleUserController::class, 'showMassCreateForm'])->name('users.mass-create.form');
        Route::post('users/mass-create', [MoodleUserController::class, 'handleMassCreateUpload'])->name('users.mass-create.upload');

        // Routes for Mass User Update
        Route::get('users/mass-update', [MoodleUserController::class, 'showMassUpdateForm'])->name('users.mass-update.form');
        Route::post('users/mass-update', [MoodleUserController::class, 'handleMassUpdateUpload'])->name('users.mass-update.upload');

        // Routes for Course Enrolment
        Route::get('enrolments/create', [MoodleUserController::class, 'showMassEnrolmentForm'])->name('enrolments.mass-create.form');
        Route::post('enrolments/create', [MoodleUserController::class, 'handleMassEnrolment'])->name('enrolments.mass-create.submit');

        // Moodle Reports Routes
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('course-progress', [MoodleReportController::class, 'showCourseProgressReportForm'])->name('course-progress.form');
            Route::post('course-progress', [MoodleReportController::class, 'generateCourseProgressReport'])->name('course-progress.generate');
            Route::get('course-progress/export', [MoodleReportController::class, 'exportCourseProgressReport'])->name('course-progress.export');
        });

        // Moodle Certificate Management Routes
        Route::prefix('certificates')->name('certificates.')->group(function () {
            Route::get('issued', [MoodleCertificateController::class, 'indexIssued'])->name('issued.index');
            // Add export route for issued certificates later
            // Route::get('issued/export', [MoodleCertificateController::class, 'exportIssuedCertificates'])->name('issued.export');

            Route::get('issue', [MoodleCertificateController::class, 'showIssueForm'])->name('issue.form');
            Route::post('issue', [MoodleCertificateController::class, 'handleIssueCertificate'])->name('issue.submit');
        });
    });
});