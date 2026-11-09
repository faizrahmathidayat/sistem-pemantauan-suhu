<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('user:create {name} {email} {--password= : Kata sandi (jika kosong akan ditanyakan)} {--role=Administrator : Nama role user}')]
#[Description('Buat user baru dengan sebuah role (bawaan: Administrator). Alert dikirim ke user yang rolenya boleh menerima notifikasi.')]
class CreateUser extends Command
{
    public function handle(): int
    {
        $role = Role::where('name', $this->option('role'))->first();
        if ($role === null) {
            $this->error('Role "'.$this->option('role').'" tidak ditemukan. Role yang ada: '.Role::orderBy('name')->pluck('name')->implode(', ').'.');

            return self::FAILURE;
        }

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

        User::create($validator->validated() + ['role_id' => $role->id]);
        $this->info('User '.$this->argument('email').' dibuat dengan role '.$role->name.'.');

        return self::SUCCESS;
    }
}
