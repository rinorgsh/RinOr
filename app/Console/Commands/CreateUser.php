<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\text;

/**
 * Le seul moyen de créer ou de modifier le compte. L'app n'expose aucune route
 * d'inscription : sur une URL publique, il n'y a donc rien à forcer.
 */
class CreateUser extends Command
{
    protected $signature = 'app:create-user
                            {--email= : Adresse e-mail}
                            {--name= : Nom affiché}';

    protected $description = "Crée le compte d'accès à RinOr (ou change son mot de passe)";

    public function handle(): int
    {
        $interactive = $this->isInteractive();

        $name = $this->option('name')
            ?: ($interactive ? text(label: 'Nom', default: 'Rinor', required: true) : 'Rinor');

        $email = $this->option('email');

        if (! $email) {
            if (! $interactive) {
                $this->error('En non-interactif, --email est obligatoire.');

                return self::FAILURE;
            }

            $email = text(label: 'Adresse e-mail', required: true);
        }

        if ($interactive) {
            $password = promptPassword(
                label: 'Mot de passe',
                hint: '12 caractères minimum — c\'est la seule chose qui protège tes finances.',
                required: true,
            );

            $confirmation = promptPassword(label: 'Confirme le mot de passe', required: true);
        } else {
            // Lu sur l'entrée standard plutôt que passé en option : un mot de
            // passe en argument atterrit dans l'historique du shell et dans la
            // liste des processus.
            //   echo 'secret' | php artisan app:create-user --email=…
            $password = trim((string) fgets(STDIN));
            $confirmation = $password;

            if ($password === '') {
                $this->error('Aucun mot de passe reçu sur stdin.');
                $this->line("Usage : echo 'mot-de-passe' | php artisan app:create-user --email=toi@exemple.be");

                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email'],
                'password' => ['required', 'string', 'min:12'],
            ],
            ['password.min' => 'Le mot de passe doit faire au moins 12 caractères.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($password !== $confirmation) {
            $this->error('Les deux mots de passe ne correspondent pas.');

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)],
        );

        $this->newLine();
        $this->info($existing
            ? "Mot de passe mis à jour pour {$user->email}."
            : "Compte créé : {$user->email}.");

        if (User::count() > 1) {
            $this->warn('Attention : il y a maintenant '.User::count().' comptes. '
                .'RinOr est pensée pour un seul utilisateur — les données ne sont pas cloisonnées par compte.');
        }

        return self::SUCCESS;
    }

    /** Y a-t-il un vrai terminal en face ? Sinon on lit stdin. */
    private function isInteractive(): bool
    {
        return function_exists('stream_isatty')
            ? @stream_isatty(STDIN)
            : $this->input->isInteractive();
    }
}
