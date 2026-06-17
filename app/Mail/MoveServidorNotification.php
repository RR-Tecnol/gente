<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MoveServidorNotification extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string,mixed> $dados */
    public function __construct(
        public array $dados,
        public string $pdfBinary,
        public string $fileName
    ) {
    }

    public function build(): self
    {
        $matricula = $this->dados['matricula'] ?? 'sem-matricula';

        return $this->subject("[GENTE v3] Nova Portaria de Lotação Disponível - Matrícula {$matricula}")
            ->view('emails.move_servidor_notification')
            ->attachData($this->pdfBinary, $this->fileName, [
                'mime' => 'application/pdf',
            ]);
    }
}

