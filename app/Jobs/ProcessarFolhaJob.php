<?php

namespace App\Jobs;

use App\Models\Folha;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessarFolhaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $request;
    private $userId;

    public function __construct($request, $userId)
    {
        $this->request = $request;
        $this->userId = $userId;
    }

    public function handle()
    {
        if ($this->request["FOLHA_ID"] == null) {
            Folha::processarFolha($this->request, $this->userId);
        } else {
            Folha::reprocessarFolha($this->request["FOLHA_ID"], $this->userId);
        }
    }
}
