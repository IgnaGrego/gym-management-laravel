<?php

use App\Filament\Resources\ExerciseResource;

/*
 * Spanish localization contract tests (SPEC-016 FR-001, FR-002, FR-003,
 * FR-007; AC-1, AC-2, AC-6, AC-9; ERR-001).
 */

it('configures Spanish as the default locale with an English fallback and the gym brand', function () {
    expect(config('app.locale'))->toBe('es')
        ->and(config('app.fallback_locale'))->toBe('en')
        ->and(config('app.faker_locale'))->toBe('es_ES')
        ->and(config('app.name'))->toBe('El Area Gym');
});

it('documents the Spanish locale values in .env.example', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)->toContain('APP_LOCALE=es')
        ->and($env)->toContain('APP_FALLBACK_LOCALE=en')
        ->and($env)->toContain('APP_FAKER_LOCALE=es_ES')
        ->and($env)->toContain('APP_NAME="El Area Gym"');
});

it('resolves the Laravel message catalogs to Spanish', function () {
    expect(trans('auth.failed'))->toBe('Estas credenciales no coinciden con nuestros registros.')
        ->and(trans('validation.required', ['attribute' => 'email']))->toBe('El campo email es obligatorio.')
        ->and(trans('validation.min.numeric', ['attribute' => 'precio', 'min' => '0.01']))->toBe('El tamaño de precio debe ser de al menos 0.01.')
        ->and(trans('passwords.reset'))->toBe('Tu contraseña ha sido restablecida.')
        ->and(trans('pagination.previous'))->toBe('&laquo; Anterior');
});

it('falls back to English for a key missing from the Spanish catalog', function () {
    // ERR-001: a key present in the framework's English catalog but absent
    // from lang/es resolves to English (never the raw key, never an exception).
    $result = trans('validation.prohibited_if_accepted');

    expect($result)->toBeString()
        ->and($result)->not->toBe('validation.prohibited_if_accepted')
        ->and($result)->toContain('field');
});

it('renders Spanish Filament resource labels', function () {
    expect(ExerciseResource::getNavigationLabel())->toBe('Ejercicios')
        ->and(ExerciseResource::getNavigationGroup())->toBe('Entrenamiento')
        ->and(ExerciseResource::getModelLabel())->toBe('Ejercicio')
        ->and(ExerciseResource::getPluralModelLabel())->toBe('Ejercicios');
});
