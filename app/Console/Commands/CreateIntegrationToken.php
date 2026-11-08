<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateIntegrationToken extends Command
{
    protected $signature = 'integrations:create-token
        {name : Nombre de la integración (ej. "Compras ERP")}
        {--permission=* : Permiso a otorgar (repetible), ej. --permission="manage stock"}';

    protected $description = 'Crea (o reusa) una cuenta de servicio para una integración externa y emite un token Sanctum nuevo';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $permissions = (array) $this->option('permission');

        if ($permissions === []) {
            $this->error('Indicá al menos un --permission.');

            return self::FAILURE;
        }

        $slug = Str::slug($name);

        $user = User::query()->firstOrCreate(
            ['email' => "integration+{$slug}@local"],
            ['name' => $name, 'password' => bcrypt(Str::random(40))],
        );

        $user->syncPermissions($permissions);

        $token = $user->createToken($name)->plainTextToken;

        $this->info("Integración \"{$name}\" lista con permisos: ".implode(', ', $permissions));
        $this->warn('Token (se muestra una sola vez, guardalo ahora):');
        $this->line($token);

        return self::SUCCESS;
    }
}
