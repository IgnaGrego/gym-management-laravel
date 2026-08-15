<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Register - Gym Management</title>
<style>body{font-family:system-ui,sans-serif;margin:0;background:#f3f4f6;color:#111827;display:flex;min-height:100vh;align-items:center;justify-content:center}form{background:#fff;padding:2rem;border-radius:.5rem;width:100%;max-width:520px;box-shadow:0 1px 3px #0002}label{display:block;margin:1rem 0 .25rem;font-size:.9rem;font-weight:600}input,textarea{width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:.4rem;box-sizing:border-box}textarea{min-height:4rem}.error{color:#b91c1c;font-size:.85rem}button{margin-top:1.5rem;width:100%;padding:.6rem;background:#111827;color:#fff;border:0;border-radius:.4rem}</style>
</head><body><form method="POST" action="{{ route('register.store') }}">@csrf
<h1>Create an account</h1>
@foreach ([['full_name','Full name','text'],['dni','DNI','text'],['email','Email','email'],['phone','Phone','text'],['emergency_contact','Emergency contact','text']] as [$name,$label,$type])
<label for="{{ $name }}">{{ $label }}</label><input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name) }}" @required(in_array($name,['full_name','dni','email'],true))>@error($name)<p class="error">{{ $message }}</p>@enderror
@endforeach
<label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="new-password">@error('password')<p class="error">{{ $message }}</p>@enderror
<label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
<label for="injuries_notes">Injuries notes</label><textarea id="injuries_notes" name="injuries_notes">{{ old('injuries_notes') }}</textarea>
<label for="medical_conditions_notes">Medical conditions notes</label><textarea id="medical_conditions_notes" name="medical_conditions_notes">{{ old('medical_conditions_notes') }}</textarea>
<button type="submit">Register</button><p><a href="{{ route('login') }}">Already have an account? Log in</a></p></form></body></html>
