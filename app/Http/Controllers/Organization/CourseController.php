<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CourseRequest;
use App\Models\Course;
use App\Models\Shift;
use App\Models\Specialty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $specialtyId = $request->integer('specialty_id') ?: null;
        $shiftId = $request->integer('shift_id') ?: null;

        $courses = Course::query()
            ->with(['specialty', 'shiftRef'])
            ->withCount(['students as active_students_count' => fn ($q) => $q->where('active', true)])
            ->when($specialtyId, fn ($q) => $q->where('specialty_id', $specialtyId))
            ->when($shiftId, fn ($q) => $q->where('shift_id', $shiftId))
            ->ordered()
            ->get();

        return view('organization.courses.index', [
            'courses' => $courses,
            'specialties' => Specialty::ordered()->get(),
            'shifts' => Shift::ordered()->get(),
            'specialtyId' => $specialtyId,
            'shiftId' => $shiftId,
        ]);
    }

    public function store(CourseRequest $request): RedirectResponse
    {
        $shift = $request->ensureUniqueCombination();

        $course = Course::create([
            'year' => (int) $request->validated('year'),
            'division' => (string) $request->validated('division'),
            'specialty_id' => (int) $request->validated('specialty_id'),
            'shift_id' => $shift->id,
            'shift' => $shift->slug,
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('status', "Curso «{$course->full_name}» creado.");
    }

    public function update(CourseRequest $request, Course $course): RedirectResponse
    {
        $shift = $request->ensureUniqueCombination($course);

        $course->update([
            'year' => (int) $request->validated('year'),
            'division' => (string) $request->validated('division'),
            'specialty_id' => (int) $request->validated('specialty_id'),
            'shift_id' => $shift->id,
            'shift' => $shift->slug,
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('status', "Curso «{$course->full_name}» actualizado.");
    }

    public function toggleActive(Course $course): RedirectResponse
    {
        $course->update(['active' => ! $course->active]);

        return back()->with('status', $course->active ? "Curso «{$course->full_name}» activado." : "Curso «{$course->full_name}» desactivado lógicamente.");
    }
}
