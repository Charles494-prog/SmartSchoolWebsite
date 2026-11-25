<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AllocationController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\CrudController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FirstRunController;
use App\Http\Controllers\GradeApprovalController;
use App\Http\Controllers\GradebookController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\OnlinePaymentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SetupWizardController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\ThrottleWrites;
use App\Mail\SignInCodeMail;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------- public
Route::get('/health', [HealthController::class, 'show'])->middleware('throttle:60,1')->name('health');
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));
Route::get('/privacy', fn () => view('public.privacy'))->name('privacy');
Route::get('/school-logo', [AdminController::class, 'logo'])->name('school.logo');
Route::get('/apply', [ApplicationController::class, 'create'])->name('apply.create');
Route::post('/apply', [ApplicationController::class, 'store'])->middleware('throttle:10,60')->name('apply.store');
Route::get('/apply/done', [ApplicationController::class, 'done'])->name('apply.done');
Route::get('/apply/status', [ApplicationController::class, 'status'])->middleware('throttle:30,1')->name('apply.status');

Route::middleware('guest')->group(function () {
    Route::get('/setup', [FirstRunController::class, 'show'])->name('first-run');
    Route::post('/setup', [FirstRunController::class, 'store'])->middleware('throttle:10,1')->name('first-run.store');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/login/code', [AuthController::class, 'showVerify'])->name('login.verify');
    Route::post('/login/code', [AuthController::class, 'verify'])->middleware('throttle:20,1');
    Route::post('/login/code/resend', [AuthController::class, 'resendCode'])->middleware('throttle:6,1')->name('login.resend');
});

// ---------------------------------------------------------------- signed-in
Route::middleware(['auth', ThrottleWrites::class])->group(function () {
    Route::get('/welcome', [WelcomeController::class, 'show'])->name('welcome');
    Route::post('/welcome', [WelcomeController::class, 'save'])->name('welcome.save');
    Route::post('/dev/unlock', [DevController::class, 'unlock'])->middleware('throttle:5,1')->name('dev.unlock');
    Route::post('/dev/mode', [DevController::class, 'mode'])->name('dev.mode');
    Route::post('/dev/login', [DevController::class, 'login'])->name('dev.login');
    Route::post('/dev/back', [DevController::class, 'back'])->name('dev.back');
    Route::post('/dev/setup', [DevController::class, 'setup'])->name('dev.setup');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:10,1')->name('profile.password');
    Route::get('/profile/photo/{user}', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::get('/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/how-it-works', [HelpController::class, 'guide'])->name('guide');
    Route::get('/notifications', [CommunicationController::class, 'notifications'])->name('notifications');
    Route::post('/notifications', [CommunicationController::class, 'updateNotifications'])->middleware('throttle:120,1')->name('notifications.update');
    Route::get('/notifications/{id}/open', [CommunicationController::class, 'openNotification'])->name('notifications.open');

    // Communication (everyone)
    Route::get('/announcements', [CommunicationController::class, 'announcements'])->name('announcements.index');
    Route::post('/announcements', [CommunicationController::class, 'postAnnouncement'])->name('announcements.store');
    Route::delete('/announcements/{announcement}', [CommunicationController::class, 'deleteAnnouncement'])->name('announcements.destroy');
    Route::post('/announcements/{announcement}/read', [CommunicationController::class, 'readAnnouncement'])->name('announcements.read');
    Route::get('/announcements/{announcement}/image', [CommunicationController::class, 'announcementImage'])->name('announcements.image');
    Route::get('/announcements/{announcement}/content', [CommunicationController::class, 'announcementContent'])->name('announcements.content');
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
        Route::get('/section-choice', [AllocationController::class, 'choice'])->middleware('role:student')->name('portal.section-choice');
        Route::post('/section-choice', [AllocationController::class, 'choose'])->middleware(['role:student', 'throttle:20,1'])->name('portal.section-choose');
        Route::get('/account', [PortalController::class, 'account'])->name('portal.account');
        Route::get('/assignments', [PortalController::class, 'assignments'])->name('portal.assignments');
        Route::post('/assignments/{assignment}', [PortalController::class, 'submit'])->name('portal.submit');
        Route::get('/classes', [PortalController::class, 'classes'])->name('portal.classes');
        Route::get('/id-card', [PortalController::class, 'idCard'])->name('portal.id');
        Route::get('/services', [ServiceController::class, 'index'])->name('portal.services');
        Route::post('/services/{student}', [ServiceController::class, 'request'])->name('portal.services.request');
        Route::get('/shop', [ShopController::class, 'store'])->name('portal.shop');
        Route::post('/shop/{student}', [ShopController::class, 'checkout'])->name('portal.shop.checkout');
        Route::post('/pay/{student}', [OnlinePaymentController::class, 'submit'])->name('portal.pay');
        Route::get('/quiz/{assignment}', [QuizController::class, 'take'])->name('quiz.take');
        Route::post('/quiz/{assignment}/start', [QuizController::class, 'start'])->name('quiz.start');
        Route::post('/quiz-attempts/{attempt}', [QuizController::class, 'submit'])->name('quiz.submit');
        Route::get('/classes/{class}', [PortalController::class, 'classroom'])->name('portal.class');
        Route::post('/classes/{class}/color', [PortalController::class, 'color'])->name('portal.color');
    });

    // Students (view rules are checked per student inside the controller)
    Route::get('/students', [StudentController::class, 'index'])->middleware('role:admin,registrar,cashier,nurse,guidance')->name('students.index');
    Route::get('/quick/students', [StudentController::class, 'quick'])->name('students.quick');
    Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
    Route::get('/students/lrn', [StudentController::class, 'lrnIndex'])->name('students.lrn');
    Route::get('/students/lrn/check', [StudentController::class, 'lrnCheck'])->name('students.lrn-check');
    Route::post('/students/{student}/lrn', [StudentController::class, 'lrnSave'])->name('students.lrn.save');
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
    Route::get('/bookstore-items/{item}/image', [ShopController::class, 'image'])->name('shop.image');
    Route::get('/payment-methods/{channel}/qr', [OnlinePaymentController::class, 'qr'])->name('channels.qr');
    Route::get('/payment-proofs/{proof}', [OnlinePaymentController::class, 'screenshot'])->name('proofs.screenshot');
    Route::get('/finance/soa/{enrollment}', [FinanceController::class, 'soa'])->name('finance.soa');
    Route::get('/assignments/{assignment}/attachment', [AssignmentController::class, 'attachment'])->name('assignments.attachment');
    Route::get('/assignments/{assignment}/content', [AssignmentController::class, 'content'])->name('assignments.content');
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/posts/{assignment}/comments', [PostCommentController::class, 'store'])->name('posts.comments.store');
        Route::delete('/post-comments/{comment}', [PostCommentController::class, 'destroy'])->name('posts.comments.destroy');
        Route::post('/posts/{assignment}/settings', [PostCommentController::class, 'settings'])->name('posts.settings');
        Route::post('/classes/{class}/mute/{user}', [PostCommentController::class, 'mute'])->name('posts.mute');
    });
    Route::post('/posts/{assignment}/read', [PostCommentController::class, 'read'])->middleware('throttle:120,1')->name('posts.read');

    // Registrar & admin
    Route::middleware('role:admin,registrar')->group(function () {
        Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
        Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
        Route::post('/applications/{application}/decide', [ApplicationController::class, 'decide'])->name('applications.decide');
        Route::get('/application-documents/{document}', [ApplicationController::class, 'document'])->name('applications.document');

        Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::post('/rooms/generate', [SectionController::class, 'generateRooms'])->name('rooms.generate');
        Route::get('/sections/unsectioned', [SectionController::class, 'unsectioned'])->name('sections.unsectioned');
        Route::post('/sections/unsectioned', [SectionController::class, 'placeUnsectioned'])->name('sections.unsectioned.place');
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
        Route::get('/allocation', [AllocationController::class, 'index'])->name('allocation.index');
        Route::post('/allocation', [AllocationController::class, 'start'])->name('allocation.start');
        Route::get('/allocation/{a}/step/{step}', [AllocationController::class, 'step'])->whereNumber('step')->name('allocation.step');
        Route::post('/allocation/{a}/sections/{section?}', [AllocationController::class, 'saveSection'])->name('allocation.section');
        Route::post('/allocation/{a}/rooms', [AllocationController::class, 'generateRooms'])->name('allocation.rooms');
        Route::post('/allocation/{a}/method', [AllocationController::class, 'method'])->name('allocation.method');
        Route::post('/allocation/{a}/reshuffle', [AllocationController::class, 'reshuffle'])->name('allocation.reshuffle');
        Route::post('/allocation/{a}/plan', [AllocationController::class, 'savePlan'])->name('allocation.plan');
        Route::post('/allocation/{a}/commit', [AllocationController::class, 'commit'])->name('allocation.commit');
        Route::post('/allocation/{a}/undo', [AllocationController::class, 'undo'])->name('allocation.undo');
        Route::post('/allocation/{a}/close', [AllocationController::class, 'close'])->name('allocation.close');
        Route::get('/grade-approvals', [GradeApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/grade-approvals/bulk', [GradeApprovalController::class, 'bulk'])->name('approvals.bulk');
        Route::get('/grade-approvals/{submission}', [GradeApprovalController::class, 'show'])->name('approvals.show');
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
        Route::get('/honors/student/{student}', [GradebookController::class, 'journey'])->name('honors.journey');
        Route::post('/honors/awards', [GradebookController::class, 'saveAward'])->name('honors.awards.store');
        Route::post('/honors/sections/{section}/top', [GradebookController::class, 'setTopN'])->name('honors.top');
        Route::post('/honors/awards/{award}/toggle', [GradebookController::class, 'toggleAward'])->name('honors.awards.toggle');
        Route::post('/honors/nominations', [GradebookController::class, 'nominate'])->name('honors.nominate');
        Route::post('/honors/nominations/{nomination}', [GradebookController::class, 'reviewNomination'])->name('honors.nominations.review');

        Route::get('/attendance/start', [HelpController::class, 'attendance'])->name('attendance.start');
        Route::get('/attendance', [AttendanceController::class, 'sheet'])->name('attendance.sheet');
        Route::post('/attendance', [AttendanceController::class, 'saveSheet'])->name('attendance.save');
        Route::get('/attendance/report', [AttendanceController::class, 'report'])->name('attendance.report');

        Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::post('/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
        Route::get('/assignments/class/{class}', [AssignmentController::class, 'classroom'])->name('assignments.class');
        Route::post('/assignments/class/{class}/color', [AssignmentController::class, 'color'])->name('assignments.color');
        Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
        Route::put('/assignments/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
        Route::post('/assignments/{assignment}/students/{student}/grade', [AssignmentController::class, 'gradeStudent'])->name('assignments.grade-student');
        Route::get('/assignments/{assignment}/quiz', [QuizController::class, 'edit'])->name('quiz.edit');
        Route::put('/assignments/{assignment}/quiz', [QuizController::class, 'save'])->name('quiz.save');
        Route::post('/assignments/{assignment}/quiz/import', [QuizController::class, 'import'])->name('quiz.import');
        Route::get('/assignments/{assignment}/quiz/export', [QuizController::class, 'export'])->name('quiz.export');
        Route::get('/assignments/{assignment}/quiz/results', [QuizController::class, 'results'])->name('quiz.results');
        Route::post('/quiz-attempts/{attempt}/review', [QuizController::class, 'review'])->name('quiz.review');
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
        Route::post('/submissions/{submission}/grade', [AssignmentController::class, 'grade'])->name('assignments.grade');
        Route::get('/submissions/{submission}/file', [AssignmentController::class, 'file'])->name('assignments.file');
    });

    // Finance
    Route::middleware('role:admin,cashier')->prefix('finance')->group(function () {
        Route::get('/bookstore', [ShopController::class, 'awaitingPayment'])->name('finance.bookstore');
        Route::get('/', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('/payments', [FinanceController::class, 'payments'])->name('finance.payments');
        Route::get('/overdue', [FinanceController::class, 'overdue'])->name('finance.overdue');
        Route::post('/remind', [FinanceController::class, 'remind'])->name('finance.remind');
        Route::get('/accounts/{enrollment}', [FinanceController::class, 'show'])->name('finance.show');
        Route::post('/accounts/{enrollment}/assess', [FinanceController::class, 'assess'])->name('finance.assess');
        Route::post('/accounts/{enrollment}/schedule', [FinanceController::class, 'schedule'])->name('finance.schedule');
        Route::post('/accounts/{enrollment}/pay', [FinanceController::class, 'pay'])->name('finance.pay');
        Route::post('/payments/{payment}/void', [FinanceController::class, 'void'])->name('finance.void');
        Route::get('/online', [OnlinePaymentController::class, 'proofs'])->name('finance.proofs');
        Route::post('/online/{proof}', [OnlinePaymentController::class, 'review'])->name('finance.proofs.review');
    });

    // Library
    Route::middleware('role:admin,librarian')->prefix('library')->group(function () {
        Route::get('/', [LibraryController::class, 'index'])->name('library.index');
        Route::post('/books', [LibraryController::class, 'saveBook'])->name('library.books.store');
        Route::post('/books/{book}', [LibraryController::class, 'saveBook'])->name('library.books.update');
        Route::post('/loans', [LibraryController::class, 'borrow'])->name('library.borrow');
        Route::post('/loans/{loan}/return', [LibraryController::class, 'return'])->name('library.return');
        Route::get('/books/{book}/cover', [LibraryController::class, 'cover'])->name('library.cover');
    });

    // HR & payroll
    Route::middleware('role:admin,hr')->prefix('payroll')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/', [PayrollController::class, 'store'])->name('payroll.store');
        Route::get('/rates', [PayrollController::class, 'rates'])->name('payroll.rates');
        Route::put('/rates', [PayrollController::class, 'saveRates'])->name('payroll.rates.update');
        Route::post('/rates/reset', [PayrollController::class, 'resetRates'])->name('payroll.rates.reset');
        Route::get('/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::delete('/{payroll}', [PayrollController::class, 'destroy'])->name('payroll.destroy');
        Route::post('/{payroll}/finalize', [PayrollController::class, 'finalize'])->name('payroll.finalize');
        Route::get('/{payroll}/payslips', [PayrollController::class, 'payslips'])->name('payroll.payslips');
        Route::post('/{payroll}/paid', [PayrollController::class, 'markPaid'])->name('payroll.paid');
        Route::get('/items/{item}/payslip', [PayrollController::class, 'payslip'])->name('payroll.payslip');
        Route::put('/items/{item}', [PayrollController::class, 'updateItem'])->name('payroll.items.update');
    });

    // Administration
    Route::middleware('role:admin,registrar')->prefix('requests')->group(function () {
        Route::get('/', [ServiceController::class, 'manage'])->name('services.manage');
        Route::post('/services', [ServiceController::class, 'save'])->name('services.store');
        Route::post('/services/{service}', [ServiceController::class, 'save'])->name('services.update');
        Route::post('/{req}/notify', [ServiceController::class, 'notify'])->name('services.requests.notify');
        Route::post('/{req}', [ServiceController::class, 'update'])->name('services.requests.update');
    });
    Route::middleware('role:admin,bookstore')->prefix('bookstore')->group(function () {
        Route::get('/', [ShopController::class, 'manage'])->name('shop.manage');
        Route::get('/orders.json', [ShopController::class, 'orders'])->name('shop.orders');
        Route::get('/students', [ShopController::class, 'studentLookup'])->middleware('throttle:120,1')->name('shop.students');
        Route::post('/walk-in', [ShopController::class, 'walkIn'])->middleware('throttle:30,1')->name('shop.walkin');
        Route::get('/orders/{order}/slip', [ShopController::class, 'slip'])->name('shop.slip');
        Route::post('/items', [ShopController::class, 'saveItem'])->name('shop.items.store');
        Route::post('/items/{item}', [ShopController::class, 'saveItem'])->name('shop.items.update');
        Route::delete('/items/{item}', [ShopController::class, 'deleteItem'])->name('shop.items.destroy');
        Route::post('/orders/{order}/notify', [ShopController::class, 'notify'])->name('shop.orders.notify');
        Route::post('/orders/{order}', [ShopController::class, 'setStatus'])->name('shop.orders.status');
    });
    Route::middleware('role:admin')->group(function () {
        Route::get('/payment-methods', [OnlinePaymentController::class, 'channels'])->name('channels.index');
        Route::post('/payment-methods', [OnlinePaymentController::class, 'saveChannel'])->name('channels.store');
        Route::post('/payment-methods/{channel}', [OnlinePaymentController::class, 'saveChannel'])->name('channels.update');
        Route::delete('/payment-methods/{channel}', [OnlinePaymentController::class, 'deleteChannel'])->name('channels.destroy');
        Route::get('/setup-wizard/{step?}', [SetupWizardController::class, 'show'])->name('setup.wizard');
        Route::post('/setup-wizard/finish', [SetupWizardController::class, 'finish'])->name('setup.finish');
        Route::post('/setup-wizard/staff/{user}/reset', [SetupWizardController::class, 'resetPassword'])->name('setup.reset');
        Route::post('/setup-wizard/{step}', [SetupWizardController::class, 'save'])->name('setup.save');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings.edit');
        Route::put('/settings', [AdminController::class, 'saveSettings'])->name('settings.update');
        Route::get('/settings/email-preview', fn () => new SignInCodeMail('482915', explode(' ', auth()->user()->name)[0]))->name('settings.email-preview');
        Route::post('/settings/test-email', [AdminController::class, 'testEmail'])->middleware('throttle:5,1')->name('settings.test-email');
        Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
        Route::get('/go-online', [HelpController::class, 'goOnline'])->name('help.go-online');
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
    Route::post('/manage/{resource}/bulk-delete', [CrudController::class, 'bulkDestroy'])->name('crud.bulk-destroy');
});
