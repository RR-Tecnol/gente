<?php

namespace App\Observers;

use App\MyLibs\AuditHelper;
use Illuminate\Database\Eloquent\Model;

class BaseAuditObserver
{
    public function created(Model $model)
    {
        AuditHelper::saveCreated($model->getTable(), $model->getKey(), $model);
    }

    public function updating(Model $model)
    {
        $alterados = $model->getDirty();
        $original = $model->getOriginal();
        foreach ($alterados as $key => $alterado) {
            AuditHelper::saveUpdating($model->getTable(), $model->getKey(), $key, $original[$key] ?? null, $alterado);
        }
    }

    public function deleting(Model $model)
    {
        AuditHelper::saveDeleting($model->getTable(), $model->getKey(), $model);
    }
}
