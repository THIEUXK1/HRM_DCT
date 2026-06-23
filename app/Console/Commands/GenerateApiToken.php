<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\User;

class GenerateApiToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:token {email} {--revoke : Revoke the token instead of generating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate or revoke an API token for a user by email';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return 1;
        }

        if ($this->option('revoke')) {
            $user->api_token = null;
            $user->save();
            $this->info('API token revoked.');
            return 0;
        }

        $token = Str::random(80);
        $user->api_token = $token;
        $user->save();

        $this->info("API token for {$email}: {$token}");
        return 0;
    }
}
