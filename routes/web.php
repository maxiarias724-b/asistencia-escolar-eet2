<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DailyStudentAttendanceController;
use App\Http\Controllers\Director\AssignmentController;
use App\Http\Controllers\Director\AuditController;
use App\Http\Controllers\Director\AuditLogController;
use App\Http\Controllers\Director\CalendarController;
use App\Http\Controllers\Director\CertificateController;
use App\Http\Controllers\Director\DashboardController;
use App\Http\Controllers\Director\UserController;
use App\Http\Controllers\Docente\HomeController as DocenteHomeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JustificationController;
use App\Http\Controllers\Operator\HomeController as OperatorHomeController;
use App\Http\Controllers\Organization\ActivityController;
use App\Http\Controllers\Organization\CourseController;
use App\Http\Controllers\Organization\ScopeController;
use App\Http\Controllers\Organization\ShiftController;
use App\Http\Controllers\Organization\SpecialtyController;
use App\Http\Controllers\People\MassImportController;
use App\Http\Controllers\People\StudentController;
use App\Http\Controllers\People\TeacherController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RosterController;
use App\Http\Controllers\TeacherAttendanceController;
use Illuminate\Support\Facades\Route;

/* ------------------------------ Autenticación ------------------------------ */
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/', HomeController::class)->middleware('active.user')->name('home');

    /* ------------------------- Panel Operador / Preceptor ------------------------- */
    Route::get('/mis-cursos', OperatorHomeController::class)
        ->middleware('role:operador')
        ->name('operator.home');

    /* ------------------------------ Panel Docente ------------------------------- */
    Route::get('/docente', DocenteHomeController::class)
        ->middleware('role:docente')
        ->name('docente.home');

    /* ------------------- Módulo Personas (Estudiantes / Docentes / Importar) ------------------- */
    Route::prefix('personas')->group(function () {
        Route::middleware('role:director,operador')->group(function () {
            Route::get('/', fn () => redirect()->route('people.students.index'))->name('people.index');
            Route::get('/nomina', [RosterController::class, 'landing'])->name('roster.landing');

            // Padrón de Estudiantes SINIDE (sin DELETE físico — baja lógica estricta)
            Route::get('/estudiantes', [StudentController::class, 'index'])->name('people.students.index');
            Route::get('/estudiantes/crear', [StudentController::class, 'create'])->name('people.students.create');
            Route::post('/estudiantes', [StudentController::class, 'store'])->name('people.students.store');
            Route::get('/estudiantes/{student}/editar', [StudentController::class, 'edit'])->whereNumber('student')->name('people.students.edit');
            Route::put('/estudiantes/{student}', [StudentController::class, 'update'])->whereNumber('student')->name('people.students.update');
            Route::patch('/estudiantes/{student}/estado', [StudentController::class, 'toggleActive'])->whereNumber('student')->name('people.students.toggle');

            // Importador Masivo Excel / CSV en 9 Pasos (Upsert por DNI)
            Route::get('/importar', [MassImportController::class, 'index'])->name('people.import.index');
            Route::get('/importar/plantilla/{entity}', [MassImportController::class, 'template'])->name('people.import.template');
            Route::post('/importar/vista-previa', [MassImportController::class, 'preview'])->name('people.import.preview');
            Route::post('/importar/confirmar', [MassImportController::class, 'confirm'])->name('people.import.confirm');
            Route::get('/importar/resultado', [MassImportController::class, 'result'])->name('people.import.result');
        });

        // Padrón Institucional de Docentes (exclusivo Director — baja lógica estricta)
        Route::middleware('role:director')->group(function () {
            Route::get('/docentes', [TeacherController::class, 'index'])->name('people.teachers.index');
            Route::get('/docentes/crear', [TeacherController::class, 'create'])->name('people.teachers.create');
            Route::post('/docentes', [TeacherController::class, 'store'])->name('people.teachers.store');
            Route::get('/docentes/{teacher}/editar', [TeacherController::class, 'edit'])->whereNumber('teacher')->name('people.teachers.edit');
            Route::put('/docentes/{teacher}', [TeacherController::class, 'update'])->whereNumber('teacher')->name('people.teachers.update');
            Route::patch('/docentes/{teacher}/estado', [TeacherController::class, 'toggleActive'])->whereNumber('teacher')->name('people.teachers.toggle');
        });
    });

    /* -------------------------- Módulo Organización Escolar -------------------------- */
    Route::middleware('role:director')->prefix('organizacion')->name('organization.')->group(function () {
        Route::get('/', fn () => redirect()->route('organization.courses.index'))->name('index');

        Route::get('/cursos', [CourseController::class, 'index'])->name('courses.index');
        Route::post('/cursos', [CourseController::class, 'store'])->name('courses.store');
        Route::put('/cursos/{course}', [CourseController::class, 'update'])->whereNumber('course')->name('courses.update');
        Route::patch('/cursos/{course}/estado', [CourseController::class, 'toggleActive'])->whereNumber('course')->name('courses.toggle');

        Route::get('/especialidades', [SpecialtyController::class, 'index'])->name('specialties.index');
        Route::post('/especialidades', [SpecialtyController::class, 'store'])->name('specialties.store');
        Route::put('/especialidades/{specialty}', [SpecialtyController::class, 'update'])->whereNumber('specialty')->name('specialties.update');
        Route::patch('/especialidades/{specialty}/estado', [SpecialtyController::class, 'toggleActive'])->whereNumber('specialty')->name('specialties.toggle');

        Route::get('/ambitos', [ScopeController::class, 'index'])->name('scopes.index');
        Route::post('/ambitos', [ScopeController::class, 'store'])->name('scopes.store');
        Route::put('/ambitos/{scope}', [ScopeController::class, 'update'])->whereNumber('scope')->name('scopes.update');
        Route::patch('/ambitos/{scope}/estado', [ScopeController::class, 'toggleActive'])->whereNumber('scope')->name('scopes.toggle');

        Route::get('/actividades', [ActivityController::class, 'index'])->name('activities.index');
        Route::post('/actividades', [ActivityController::class, 'store'])->name('activities.store');
        Route::put('/actividades/{activity}', [ActivityController::class, 'update'])->whereNumber('activity')->name('activities.update');
        Route::patch('/actividades/{activity}/estado', [ActivityController::class, 'toggleActive'])->whereNumber('activity')->name('activities.toggle');

        Route::get('/turnos', [ShiftController::class, 'index'])->name('shifts.index');
        Route::post('/turnos', [ShiftController::class, 'store'])->name('shifts.store');
        Route::put('/turnos/{shift}', [ShiftController::class, 'update'])->whereNumber('shift')->name('shifts.update');
        Route::patch('/turnos/{shift}/estado', [ShiftController::class, 'toggleActive'])->whereNumber('shift')->name('shifts.toggle');
    });

    /* ------------- Módulo Preceptores (Asignaciones de Responsabilidades) ------------- */
    Route::middleware('role:director')->prefix('preceptores')->name('director.assignments.')->group(function () {
        Route::get('/', [AssignmentController::class, 'index'])->name('index');
        Route::post('/', [AssignmentController::class, 'store'])->name('store');
        Route::delete('/{assignment}', [AssignmentController::class, 'destroy'])->whereNumber('assignment')->name('destroy');
    });

    /*
     * Asistencia y nómina contextualizada por ámbito y curso.
     * `course.assignment` bloquea cursos o ámbitos no asignados por URL, query o formulario.
     */
    Route::middleware('course.assignment')->prefix('asistencia')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('attendance.index');

        // Toma de asistencia diaria contextualizada (Fecha -> Turno -> Ámbito -> Curso -> Actividad)
        Route::get('/estudiantes', [DailyStudentAttendanceController::class, 'index'])->name('attendance.daily');
        Route::post('/estudiantes', [DailyStudentAttendanceController::class, 'store'])->name('attendance.daily.store');

        Route::prefix('{activity}/{course}')->whereNumber('course')->group(function () {
            // Registro mensual (grilla estilo SINIDE)
            Route::get('/', [AttendanceController::class, 'show'])->name('attendance.show');
            Route::put('/registro', [AttendanceController::class, 'store'])->name('attendance.store');
            Route::delete('/registro', [AttendanceController::class, 'destroy'])->name('attendance.destroy');
            Route::post('/registro/dia', [AttendanceController::class, 'fillDay'])->name('attendance.fill-day');

            // Justificaciones
            Route::post('/justificar', [JustificationController::class, 'store'])->name('attendance.justify');
            Route::get('/justificaciones/{attendance}/certificado', [JustificationController::class, 'certificate'])
                ->whereNumber('attendance')
                ->name('attendance.certificate');

            // Nómina SINIDE por curso
            Route::get('/nomina', [RosterController::class, 'index'])->name('roster.index');
            Route::post('/nomina', [RosterController::class, 'store'])->name('roster.store');
            Route::get('/nomina/plantilla', [RosterController::class, 'template'])->name('roster.template');
            Route::post('/nomina/importar', [RosterController::class, 'import'])->name('roster.import');
            Route::post('/nomina/importar/confirmar', [RosterController::class, 'confirmImport'])->name('roster.import.confirm');
            Route::put('/nomina/{student}', [RosterController::class, 'update'])->whereNumber('student')->name('roster.update');
            Route::delete('/nomina/{student}', [RosterController::class, 'destroy'])->whereNumber('student')->name('roster.destroy');
        });
    });

    /* ------------- Asistencia de Docentes y Accesos Directos de Consulta ------------- */
    Route::middleware('role:director,operador')->prefix('asistencia')->group(function () {
        Route::get('/docentes', [TeacherAttendanceController::class, 'index'])->name('attendance.teachers.index');
        Route::post('/docentes', [TeacherAttendanceController::class, 'store'])->name('attendance.teachers.store');
        Route::get('/consultar', [ReportController::class, 'index'])->name('attendance.consult');
        Route::get('/historial', [ReportController::class, 'studentHistorySearch'])->name('attendance.history');
    });

    /* ------- Módulo de Reportes, Planilla Mensual (01-31), Ranking y Exportación ------- */
    Route::middleware('role:director,operador')->prefix('reportes')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/ranking', [ReportController::class, 'ranking'])->name('ranking');
        Route::get('/planilla-mensual', [ReportController::class, 'monthlySheet'])->name('monthly-sheet');
        Route::get('/estudiantes', [ReportController::class, 'studentHistorySearch'])->name('student-search');
        Route::get('/estudiantes/{student}', [ReportController::class, 'studentHistory'])
            ->whereNumber('student')
            ->name('student-history');
    });

    /* ----------------------------- Panel del Director ----------------------------- */
    Route::middleware('role:director')->prefix('director')->name('director.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/auditoria', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/bitacora', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/justificaciones/{justification}/certificado', CertificateController::class)
            ->name('justifications.certificate');

        Route::get('/calendario', [CalendarController::class, 'index'])->name('calendar.index');
        Route::post('/calendario', [CalendarController::class, 'store'])->name('calendar.store');
        Route::delete('/calendario/{day}', [CalendarController::class, 'destroy'])->name('calendar.destroy');

        Route::patch('/usuarios/{user}/estado', [UserController::class, 'toggleActive'])->name('users.toggle');
        Route::resource('usuarios', UserController::class)
            ->except('show')
            ->parameters(['usuarios' => 'user'])
            ->names('users');
    });
});
