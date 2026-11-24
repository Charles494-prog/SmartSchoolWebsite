<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\CrudController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GradebookController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------- public
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));
Route::get('/privacy', fn () => view('public.privacy'))->name('privacy');
Route::get('/school-logo', [AdminController::class, 'logo'])->name('school.logo');
Route::get('/apply', [ApplicationController::class, 'create'])->name('apply.create');
Route::post('/apply', [ApplicationController::class, 'store'])->middleware('throttle:10,60')->name('apply.store');
Route::get('/apply/done', [ApplicationController::class, 'done'])->name('apply.done');
Route::get('/apply/status', [ApplicationController::class, 'status'])->middleware('throttle:30,1')->name('apply.status');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// ---------------------------------------------------------------- signed-in
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/how-it-works', [HelpController::class, 'guide'])->name('guide');
    Route::get('/notifications', [CommunicationController::class, 'notifications'])->name('notifications');

    // Communication (everyone)
    Route::get('/announcements', [CommunicationController::class, 'announcements'])->name('announcements.index');
    Route::post('/announcements', [CommunicationController::class, 'postAnnouncement'])->name('announcements.store');
    Route::delete('/announcements/{announcement}', [CommunicationController::class, 'deleteAnnouncement'])->name('announcements.destroy');
    Route::get('/calendar', [CommunicationController::class, 'calendar'])->name('calendar');
    Route::get('/messages', fn () => view('messages.chat'))->name('messages.index');
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/contacts', [ChatController::class, 'contacts'])->name('chat.contacts');
    Route::post('/chat/start', [ChatController::class, 'start'])->middleware('throttle:30,1')->name('chat.start');
    Route::get('/chat/{conversation}', [ChatController::class, 'messages'])->name('chat.messages');
    Route::post('/chat/{conversation}', [ChatController::class, 'send'])->middleware('throttle:60,1')->name('chat.send');
    Route::get('/messages/{conversation}', fn () => redirect()->route('messages.index'))->name('messages.show');

    // Portal (students & parents)
    Route::middleware('role:student,parent')->prefix('portal')->group(function () {
        Route::get('/', [PortalController::class, 'home'])->name('portal');
        Route::get('/grades', [PortalController::class, 'grades'])->name('portal.grades');
        Route::get('/attendance', [PortalController::class, 'attendance'])->name('portal.attendance');
        Route::get('/schedule', [PortalController::class, 'schedule'])->name('portal.schedule');
        Route::get('/account', [PortalController::class, 'account'])->name('portal.account');
        Route::get('/assignments', [PortalController::class, 'assignments'])->name('portal.assignments');
        Route::post('/assignments/{assignment}', [PortalController::class, 'submit'])->name('portal.submit');
    });

    // Students (view rules are checked per student inside the controller)
    Route::get('/students', [StudentController::class, 'index'])->middleware('role:admin,registrar,teacher,cashier,nurse,guidance,librarian')->name('students.index');
    Route::get('/quick/students', [StudentController::class, 'quick'])->name('students.quick');
    Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::post('/students/{student}/guardians', [StudentController::class, 'addGuardian'])->name('students.guardians.add');
    Route::delete('/students/{student}/guardians/{guardian}', [StudentController::class, 'removeGuardian'])->name('students.guardians.remove');
    Route::post('/students/{student}/account', [StudentController::class, 'studentAccount'])->name('students.account');
    Route::post('/students/{student}/enroll', [StudentController::class, 'enroll'])->name('students.enroll');
    Route::post('/students/{student}/transfer', [StudentController::class, 'transferOut'])->name('students.transfer');
    Route::get('/students/{student}/photo', [StudentController::class, 'photo'])->name('students.photo');
    Route::get('/students/{student}/id-card', [StudentController::class, 'idCard'])->name('students.id');
    Route::get('/students/{student}/sf10', [StudentController::class, 'permanentRecord'])->name('students.sf10');
    Route::get('/students/{student}/export', [StudentController::class, 'export'])->name('students.export');
    Route::get('/report-card/{enrollment}', [StudentController::class, 'reportCard'])->name('students.sf9');
    Route::get('/sections/{section}/id-cards', [StudentController::class, 'sectionIdCards'])->name('sections.ids');

    // Finance documents (parents can open their own child's receipts/SOA)
    Route::get('/finance/receipt/{payment}', [FinanceController::class, 'receipt'])->name('finance.receipt');
    Route::get('/finance/soa/{enrollment}', [FinanceController::class, 'soa'])->name('finance.soa');
    Route::get('/assignments/{assignment}/attachment', [AssignmentController::class, 'attachment'])->name('assignments.attachment');

    // Registrar & admin
    Route::middleware('role:admin,registrar')->group(function () {
        Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
        Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
        Route::post('/applications/{application}/decide', [ApplicationController::class, 'decide'])->name('applications.decide');
        Route::get('/application-documents/{document}', [ApplicationController::class, 'document'])->name('applications.document');

        Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::get('/sections/{section}', [SectionController::class, 'show'])->name('sections.show');
        Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
        Route::post('/sections/{section}/students', [SectionController::class, 'assignStudents'])->name('sections.students.assign');
        Route::delete('/sections/{section}/students/{enrollment}', [SectionController::class, 'removeStudent'])->name('sections.students.remove');
        Route::post('/sections/{section}/classes', [SectionController::class, 'addClass'])->name('classes.store');
        Route::put('/classes/{class}', [SectionController::class, 'updateClass'])->name('classes.update');
        Route::delete('/classes/{class}', [SectionController::class, 'removeClass'])->name('classes.destroy');
        Route::post('/classes/{class}/slots', [SectionController::class, 'addSlot'])->name('slots.store');
        Route::post('/classes/{class}/place', [SectionController::class, 'placeSlot'])->name('slots.place');
        Route::patch('/slots/{slot}', [SectionController::class, 'moveSlot'])->name('slots.move');
        Route::delete('/slots/{slot}', [SectionController::class, 'removeSlot'])->name('slots.destroy');
        Route::get('/qr-cards', [HelpController::class, 'qrCards'])->name('qr.cards');
        Route::get('/promotion', [SectionController::class, 'promotion'])->name('promotion.index');
        Route::post('/promotion', [SectionController::class, 'promote'])->name('promotion.run');
        Route::post('/grade-submissions/{submission}', [GradebookController::class, 'review'])->name('gradebook.review');
    });

    // QR scanning: guard at the gate, teachers in their own classes, admin/registrar both
    Route::middleware('role:admin,registrar,teacher,guard')->group(function () {
        Route::get('/attendance/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->middleware('throttle:240,1')->name('attendance.checkin');
    });

    // Teaching
    Route::middleware('role:admin,registrar,teacher')->group(function () {
        Route::get('/timetable', [SectionController::class, 'timetable'])->name('timetable');
        Route::get('/gradebook', [GradebookController::class, 'index'])->name('gradebook.index');
        Route::get('/gradebook/{class}', [GradebookController::class, 'show'])->name('gradebook.show');
        Route::post('/gradebook/{class}/assessments', [GradebookController::class, 'addAssessment'])->name('gradebook.assessments.store');
        Route::delete('/assessments/{assessment}', [GradebookController::class, 'deleteAssessment'])->name('gradebook.assessments.destroy');
        Route::post('/gradebook/{class}/scores', [GradebookController::class, 'saveScores'])->name('gradebook.scores');
        Route::post('/gradebook/{class}/submit', [GradebookController::class, 'submit'])->name('gradebook.submit');
        Route::post('/gradebook/{class}/weights', [GradebookController::class, 'requestWeights'])->name('gradebook.weights');
        Route::post('/grading-requests/{gradingRequest}', [GradebookController::class, 'reviewWeights'])->name('gradebook.weights.review');
        Route::delete('/gradebook/{class}/weights', [GradebookController::class, 'resetWeights'])->name('gradebook.weights.reset');
        Route::get('/honors', [GradebookController::class, 'honors'])->name('honors');

        Route::get('/attendance/start', [HelpController::class, 'attendance'])->name('attendance.start');
        Route::get('/attendance', [AttendanceController::class, 'sheet'])->name('attendance.sheet');
        Route::post('/attendance', [AttendanceController::class, 'saveSheet'])->name('attendance.save');
        Route::get('/attendance/report', [AttendanceController::class, 'report'])->name('attendance.report');

        Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::post('/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
        Route::post('/submissions/{submission}/grade', [AssignmentController::class, 'grade'])->name('assignments.grade');
        Route::get('/submissions/{submission}/file', [AssignmentController::class, 'file'])->name('assignments.file');
    });

    // Finance
    Route::middleware('role:admin,cashier')->prefix('finance')->group(function () {
        Route::get('/', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('/payments', [FinanceController::class, 'payments'])->name('finance.payments');
        Route::get('/overdue', [FinanceController::class, 'overdue'])->name('finance.overdue');
        Route::post('/remind', [FinanceController::class, 'remind'])->name('finance.remind');
        Route::get('/accounts/{enrollment}', [FinanceController::class, 'show'])->name('finance.show');
        Route::post('/accounts/{enrollment}/assess', [FinanceController::class, 'assess'])->name('finance.assess');
        Route::post('/accounts/{enrollment}/schedule', [FinanceController::class, 'schedule'])->name('finance.schedule');
        Route::post('/accounts/{enrollment}/pay', [FinanceController::class, 'pay'])->name('finance.pay');
        Route::post('/payments/{payment}/void', [FinanceController::class, 'void'])->name('finance.void');
    });

    // Library
    Route::middleware('role:admin,librarian')->prefix('library')->group(function () {
        Route::get('/', [LibraryController::class, 'index'])->name('library.index');
        Route::post('/books', [LibraryController::class, 'saveBook'])->name('library.books.store');
        Route::post('/books/{book}', [LibraryController::class, 'saveBook'])->name('library.books.update');
        Route::post('/loans', [LibraryController::class, 'borrow'])->name('library.borrow');
        Route::post('/loans/{loan}/return', [LibraryController::class, 'return'])->name('library.return');
    });

    // HR & payroll
    Route::middleware('role:admin,hr')->prefix('payroll')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/', [PayrollController::class, 'store'])->name('payroll.store');
        Route::get('/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::delete('/{payroll}', [PayrollController::class, 'destroy'])->name('payroll.destroy');
        Route::post('/{payroll}/finalize', [PayrollController::class, 'finalize'])->name('payroll.finalize');
        Route::get('/{payroll}/payslips', [PayrollController::class, 'payslips'])->name('payroll.payslips');
        Route::put('/items/{item}', [PayrollController::class, 'updateItem'])->name('payroll.items.update');
    });

    // Administration
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings.edit');
        Route::put('/settings', [AdminController::class, 'saveSettings'])->name('settings.update');
        Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
        Route::get('/backups', [AdminController::class, 'backups'])->name('backups.index');
        Route::post('/backups', [AdminController::class, 'runBackup'])->name('backups.run');
        Route::get('/backups/{name}', [AdminController::class, 'downloadBackup'])->name('backups.download');
    });

    // Simple record screens (each resource checks its own roles)
    Route::get('/manage/{resource}', [CrudController::class, 'index'])->name('crud.index');
    Route::get('/manage/{resource}/create', [CrudController::class, 'create'])->name('crud.create');
    Route::post('/manage/{resource}', [CrudController::class, 'store'])->name('crud.store');
    Route::get('/manage/{resource}/{id}/edit', [CrudController::class, 'edit'])->name('crud.edit');
    Route::put('/manage/{resource}/{id}', [CrudController::class, 'update'])->name('crud.update');
    Route::delete('/manage/{resource}/{id}', [CrudController::class, 'destroy'])->name('crud.destroy');
});
