<?php

namespace App\Actions;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterClient
{
    public function handle(array $data): Client
    {
        return DB::transaction(function () use ($data): Client {
            $client = Client::create([
                'full_name' => $data['full_name'], 'dni' => $data['dni'], 'email' => $data['email'],
                'phone' => $data['phone'] ?? null, 'emergency_contact' => $data['emergency_contact'] ?? null,
                'injuries_notes' => $data['injuries_notes'] ?? null,
                'medical_conditions_notes' => $data['medical_conditions_notes'] ?? null,
                'status' => Client::STATUS_PENDING,
            ]);
            $user = User::create([
                'name' => $data['full_name'], 'email' => $data['email'],
                'password' => $data['password'], 'is_active' => false,
            ]);
            $user->roles()->attach(Role::firstOrCreate(['name' => Role::CLIENT]));
            $client->user()->associate($user);
            $client->save();
            return $client;
        });
    }
}
