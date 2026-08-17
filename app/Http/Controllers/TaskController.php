<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(): Response
    {
        $tasks = Task::query()
            ->orderByRaw("CASE status WHEN 'doing' THEN 0 WHEN 'todo' THEN 1 ELSE 2 END")
            ->orderByRaw('due_on IS NULL')
            ->orderBy('due_on')
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->get()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status,
                'priority' => $t->priority,
                'due_on' => $t->due_on?->format('Y-m-d'),
                'notes' => $t->notes,
                'is_overdue' => $t->is_overdue,
            ]);

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'counts' => [
                'todo' => $tasks->where('status', Task::TODO)->count(),
                'doing' => $tasks->where('status', Task::DOING)->count(),
                'done' => $tasks->where('status', Task::DONE)->count(),
                'overdue' => $tasks->where('is_overdue', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Task::create($this->validated($request));

        return back()->with('flash', 'Tâche ajoutée.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $data = $this->validated($request);

        // Horodate le passage à « terminé », et le remet à null si on rouvre.
        if (($data['status'] ?? $task->status) === Task::DONE) {
            $data['completed_at'] = $task->completed_at ?? now();
        } else {
            $data['completed_at'] = null;
        }

        $task->update($data);

        return back()->with('flash', 'Tâche mise à jour.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return back()->with('flash', 'Tâche supprimée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'status' => ['required', Rule::in(Task::STATUSES)],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
