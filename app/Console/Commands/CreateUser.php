<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('user:create {name} {email} {--password= : Kata sandi (jika kosong akan ditanyakan)}')]
#[Description('Buat user baru. Semua user terdaftar menerima notifikasi alert.')]
class CreateUser extends Command
{
    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Kata sandi (min. 8 karakter)');

        $validator = Validator::make([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create($validator->validated());
        $this->info('User '.$this->argument('email').' dibuat.');

        return self::SUCCESS;
    }
}
