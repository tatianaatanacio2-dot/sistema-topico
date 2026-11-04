<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('topico:coordinator {email} {--name=Coordinador de tópico}')]
#[Description('Crea el coordinador inicial con una contraseña aleatoria')]
class CreateCoordinator extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists()) {
            $this->error('El correo no es válido o ya existe.');

            return self::FAILURE;
        }
        $password = Str::password(18);
        User::create(['name' => $this->option('name'), 'email' => $email, 'password' => $password, 'role' => 'coordinator', 'active' => true]);
        $this->info('Coordinador creado. Guarda estas credenciales:');
        $this->line('Correo: '.$email);
        $this->line('Contraseña: '.$password);

        return self::SUCCESS;
    }
}
