<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;

class HoneytokenTriggered
{
    use Dispatchable, SerializesModels;

    /** @var 'honey_funcionario'|'canary_route' */
    public string $kind;

    public ?int $funcionarioId;

    public string $ip;

    public string $path;

    public int $userId;

    public string $userAgent;

    public function __construct(
        string $kind,
        Request $request,
        ?int $funcionarioId = null
    ) {
        $this->kind = $kind;
        $this->funcionarioId = $funcionarioId;
        $this->ip = (string) $request->ip();
        $this->path = (string) $request->getPathInfo();
        $u = $request->user();
        $this->userId = $u && isset($u->USUARIO_ID) ? (int) $u->USUARIO_ID : 0;
        $this->userAgent = (string) substr((string) $request->userAgent(), 0, 255);
    }
}
