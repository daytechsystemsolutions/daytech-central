<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/** Cria o usuário da Central ou troca a senha dele (não há cadastro nem "esqueci a senha" na tela). */
class CentralUsuario extends Command
{
    protected $signature = 'central:usuario {email : e-mail de login}
        {--zerar-2fa : desliga os 2 fatores (celular perdido); na próxima entrada configura de novo}';

    protected $description = 'Cria o usuário da Central ou troca a senha';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('E-mail inválido.');

            return self::FAILURE;
        }

        $usuario = User::where('email', $email)->first();

        if ($this->option('zerar-2fa')) {
            if (! $usuario) {
                $this->error('Usuário não encontrado.');

                return self::FAILURE;
            }
            $usuario->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
            $this->derrubarSessoes($usuario);
            $this->info('2 fatores desligados e sessões encerradas. Na próxima entrada a Central pede para configurar de novo.');

            return self::SUCCESS;
        }

        $nome = $usuario?->name ?? text('Nome', required: true);
        $senha = password('Senha (mínimo 12 caracteres)', required: true, validate: fn ($v) => mb_strlen($v) < 12 ? 'Use pelo menos 12 caracteres.' : null);
        if (password('Repita a senha', required: true) !== $senha) {
            $this->error('As senhas não batem. Nada foi alterado.');

            return self::FAILURE;
        }

        $usuario ??= new User(['email' => $email]);
        $usuario->forceFill(['name' => $nome, 'password' => $senha])->save();
        $this->derrubarSessoes($usuario);
        $this->info($usuario->wasRecentlyCreated ? "Usuário criado: {$email}" : "Senha trocada e sessões encerradas: {$email}");

        return self::SUCCESS;
    }

    /** Senha trocada ou 2FA zerado: ninguém continua logado com a sessão ou o "manter conectado" antigos. */
    private function derrubarSessoes(User $usuario): void
    {
        DB::table('sessions')->where('user_id', $usuario->id)->delete();
        $usuario->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
