<?php

/*
 * Landing page presentation tests (SPEC-015 FR-004, FR-008, FR-009; AC-3,
 * AC-4, AC-5, AC-10, AC-11, AC-14).
 */

beforeEach(function () {
    $this->withoutVite();
});

it('renders the landing page with the shared layout and branding', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('El Area Gym')
        ->assertSee('<title>', false)
        ->assertSee('<header', false)
        ->assertSee('<nav', false)
        ->assertSee('<main', false)
        ->assertSee('<footer', false);
});

it('shows a visible login entry point', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Iniciar sesión')
        ->assertSee(route('login'), false);
});

it('drops the inline page stylesheet from the scoped views', function () {
    // AC-3: the restyled views no longer carry their own inline <style> block.
    // Verified at source level because Livewire (a Filament dependency) may
    // auto-inject its own styles into the rendered HTML independently of the
    // page CSS being removed.
    foreach (['welcome', 'auth/login', 'portal'] as $view) {
        expect(file_get_contents(resource_path("views/{$view}.blade.php")))
            ->not->toContain('<style');
    }
});

it('preserves the session status notice', function () {
    $this->withSession(['status' => 'Tu cuenta aún no tiene roles asignados. Contactá a un administrador.'])
        ->get('/')
        ->assertOk()
        ->assertSee('Tu cuenta aún no tiene roles asignados. Contactá a un administrador.');
});

it('loads assets through the shared layout via @vite', function () {
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($layout)->toContain("@vite(['resources/css/app.css', 'resources/js/app.js'])");

    foreach (['welcome', 'auth/login', 'portal'] as $view) {
        expect(file_get_contents(resource_path("views/{$view}.blade.php")))
            ->toContain("@extends('layouts.app')");
    }
});
