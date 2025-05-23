<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\profile\PrimaryProfileSetupController;
use App\Http\Controllers\profile\TeachersPrimaryProfileController;
use App\Http\Controllers\course\coursecontroller;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\PaymentController;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    Route::post('/save-profile', [PrimaryProfileSetupController::class, 'store']);

    Route::post('/student/profile/update', [PrimaryProfileSetupController::class, 'update']);

    Route::post('/teacher/profile/update', [TeachersPrimaryProfileController::class, 'update']);

    Route::post('/create-payment-intent', [PaymentController::class, 'createPaymentIntent']);
    Route::post('/apply-teacher', [PaymentController::class, 'applyForTeacher']);
    Route::post('/verify-payment', [PaymentController::class, 'verifyPayment']);

    Route::get('/student/profile/{userId}', [PrimaryProfileSetupController::class, 'show']);
    Route::get('/teacher/profile/{userId}', [TeachersPrimaryProfileController::class, 'show']);

    Route::post('/teacher/create-course', [coursecontroller::class, 'store']);
    Route::get('/courses/teacher/{userId}', [CourseController::class, 'index']);
    Route::get('/course/{id}', [CourseController::class, 'show']);

    Route::post('/modules', [CourseController::class, 'addModule']);
    Route::get('/courses/{courseId}/modules', [CourseController::class, 'getModules']);
    Route::get('/modules/{moduleId}', [CourseController::class, 'getModuleContent']);

    Route::post('/videos', [CourseController::class, 'addVideo']);
    Route::post('/live-classes', [CourseController::class, 'addLiveClass']);

    Route::get('/scheduled-classes/{userID}', [CourseController::class, 'getScheduledClass']);


    Route::get('/courses/top-rated', [CourseController::class, 'topRated']);
    Route::get('/courses/top-selling', [CourseController::class, 'topSelling']);
    Route::get('/courses/suggested', [CourseController::class, 'suggested']);


    Route::get('/courses/all', [CourseController::class, 'allCourses']);
    Route::get('/courses/categories', [CourseController::class, 'categories']);

    Route::get('/courses/{location}/{id}/{teacherEmail}', [CourseController::class, 'daynamicCourse']);

    Route::post('/enrollments', [EnrollmentController::class, 'store']);
    Route::get('/enrollments', [EnrollmentController::class, 'index']);
    Route::get('/enrollments/check/{courseId}', [EnrollmentController::class, 'checkEnrollment']);


    Route::get('/courses/full-course/{courseId}', [CourseController::class, 'fullCourse']);


    Route::get('/get-location/{email}', [AuthenticatedSessionController::class, 'locations']);



});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');


});


// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/save-profile', [PrimaryProfileSetupController::class, 'store']);
// });


