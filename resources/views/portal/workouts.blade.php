@extends('layouts.app')

@section('title', 'Workouts - El Area Gym')

@section('content')
    <section>
        <h1 class="text-3xl font-bold tracking-tight text-stone-900">Workouts</h1>

        @include('partials.portal-nav')

        @if ($errors->any())
            <div class="mt-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-red-800" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('status'))
            <div class="mt-6 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-green-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        <h2 class="mt-8 text-xl font-semibold text-stone-900">Log a workout</h2>

        <form method="POST" action="{{ route('portal.workouts.store') }}" class="mt-4 rounded-lg border border-stone-200 bg-white p-4"
              x-data="{ referenceType: 'routine' }">
            @csrf

            <div class="flex gap-4">
                <label class="inline-flex items-center gap-1 text-sm font-medium text-stone-700">
                    <input type="radio" name="reference_type" value="routine" x-model="referenceType" checked>
                    From assigned routine
                </label>
                <label class="inline-flex items-center gap-1 text-sm font-medium text-stone-700">
                    <input type="radio" name="reference_type" value="free" x-model="referenceType">
                    Free exercise
                </label>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div x-show="referenceType === 'routine'">
                    <label for="routine_exercise_id" class="block text-sm font-semibold text-stone-700">Prescribed set</label>
                    <select id="routine_exercise_id" name="routine_exercise_id" class="mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900">
                        <option value="">Select a prescribed set</option>
                        @if ($routine)
                            @foreach ($routine->days as $day)
                                @foreach ($day->exercises as $row)
                                    <option value="{{ $row->id }}" @selected(old('routine_exercise_id') == $row->id)>
                                        Day {{ $day->day_number }} · {{ $row->exercise?->name ?? '—' }}
                                        — {{ $row->target_weight === null ? 'Bodyweight' : $row->target_weight.' kg' }} × {{ $row->target_reps }} (Set {{ $row->set_number }})
                                    </option>
                                @endforeach
                            @endforeach
                        @endif
                    </select>
                </div>

                <div x-show="referenceType === 'free'">
                    <label for="exercise_id" class="block text-sm font-semibold text-stone-700">Exercise</label>
                    <select id="exercise_id" name="exercise_id" class="mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900">
                        <option value="">Select an exercise</option>
                        @foreach ($exercises as $exercise)
                            <option value="{{ $exercise->id }}" @selected(old('exercise_id') == $exercise->id)>{{ $exercise->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="performed_at" class="block text-sm font-semibold text-stone-700">Performed at</label>
                    <input type="datetime-local" id="performed_at" name="performed_at" value="{{ old('performed_at', now()->format('Y-m-d\TH:i')) }}"
                           class="mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900">
                </div>

                <div>
                    <label for="actual_weight" class="block text-sm font-semibold text-stone-700">Actual weight (kg)</label>
                    <input type="number" id="actual_weight" name="actual_weight" step="0.01" min="0" value="{{ old('actual_weight') }}"
                           class="mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900">
                </div>

                <div>
                    <label for="actual_reps" class="block text-sm font-semibold text-stone-700">Actual reps</label>
                    <input type="number" id="actual_reps" name="actual_reps" min="1" value="{{ old('actual_reps') }}"
                           class="mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900">
                </div>

                <div>
                    <label for="notes" class="block text-sm font-semibold text-stone-700">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900">{{ old('notes') }}</textarea>
                </div>
            </div>

            <button type="submit" class="mt-4 rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                Save workout
            </button>
        </form>

        <h2 class="mt-8 text-xl font-semibold text-stone-900">History</h2>

        @if ($workoutLogs->isEmpty())
            <p class="mt-4 text-stone-600">No workouts logged yet.</p>
        @else
            @foreach ($workoutLogs->groupBy(fn ($log) => $log->performed_at->format('Y-m-d')) as $date => $logs)
                <h3 class="mt-4 text-lg font-semibold text-stone-800">{{ $date }}</h3>

                <ul class="mt-2 space-y-2">
                    @foreach ($logs as $log)
                        <li class="rounded-lg border border-stone-200 bg-white p-4">
                            <p class="font-semibold text-stone-900">
                                {{ $log->exerciseName() ?? '—' }}
                                · {{ $log->performed_at->format('H:i') }}
                            </p>
                            <p class="mt-1 text-sm text-stone-600">
                                {{ $log->actual_weight === null ? '—' : $log->actual_weight }} kg × {{ $log->actual_reps }}
                            </p>
                            @if ($log->notes)
                                <p class="mt-1 text-sm text-stone-500">{{ $log->notes }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </section>
@endsection
