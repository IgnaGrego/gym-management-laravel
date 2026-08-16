<?php

namespace App\Filament\Resources\WorkoutLogResource\Pages;

use App\Filament\Resources\WorkoutLogResource;
use App\Models\Client;
use App\Models\WorkoutLog;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ClientProgress extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = WorkoutLogResource::class;

    protected static string $view = 'filament.resources.workout-log-resource.pages.client-progress';

    protected static ?string $navigationLabel = 'Progreso del cliente';

    public int $clientId;

    /**
     * The per-client progress page (SPEC-011 FR-003 + FR-004, WL-10).
     *
     * A read-only, chronologically ordered table of one client's logged sets
     * with Target and Actual columns: the grouped history (FR-003, AC-8 — the
     * performed_at column + default sort is the grouping key) and the minimal
     * prescription-vs-actual comparison (FR-004, AC-9; free-log rows show the
     * target columns as '—'). No row actions: logs are immutable (BR-006).
     *
     * The page lives on WorkoutLogResource (ADMIN|TRAINER), never inside the
     * ADMIN-only ClientResource, so TRAINER can reach it (the UI-placement
     * constraint of SPEC-011 §2/§9). Access is enforced server-side via
     * WorkoutLogPolicy::viewAny (Resource::canAccess) and the client-exists
     * check here (ERR-008).
     */
    public function mount(int $client): void
    {
        Client::findOrFail($client);

        $this->clientId = $client;
    }

    public function getTitle(): string
    {
        $client = Client::find($this->clientId);

        return 'Progreso — '.($client?->full_name ?? 'Cliente');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                WorkoutLog::query()
                    ->forClient($this->clientId)
                    ->with(['routineExercise.exercise', 'exercise', 'recordedBy'])
            )
            ->defaultSort('performed_at')
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')
                    ->label('Realizado el')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('exercise_name')
                    ->label('Ejercicio')
                    ->state(fn (WorkoutLog $record): ?string => $record->exerciseName())
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('routineExercise.target_weight')
                    ->label('Peso objetivo (kg)')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('routineExercise.target_reps')
                    ->label('Repeticiones objetivo')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('actual_weight')
                    ->label('Peso real (kg)')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('actual_reps')
                    ->label('Repeticiones reales'),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notas')
                    ->placeholder('—')
                    ->limit(50),
                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Registrado por'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado el')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
