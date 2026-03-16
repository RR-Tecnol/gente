<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UsuarioMail extends Mailable
{
    use Queueable, SerializesModels;

    public $usuario;
    public $senhaTemporaria;

    /**
     * Envia credenciais de primeiro acesso ao usuário recém-criado.
     */
    public function __construct($usuario, string $senhaTemporaria = '')
    {
        $this->usuario = $usuario;
        $this->senhaTemporaria = $senhaTemporaria;
    }

    public function build()
    {
        return $this
            ->from(config('mail.from.address'), config('app.name') . ' — ' . config('app.desenvolvido_por'))
            ->subject('Bem-vindo ao ' . config('app.name') . ' — Suas credenciais de acesso')
            ->view('mail.usuario', [
                'user' => $this->usuario,
                'senhaTemporaria' => $this->senhaTemporaria,
            ]);
    }
}
