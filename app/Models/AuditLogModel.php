<?php

namespace App\Models;

use App\Exceptions\SecurityException;
use App\Support\AuditLogChainer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * AUDIT_LOG: apenas INSERT (imutabilidade Frente 3/4). UPDATE/DELETE → SecurityIntegrityException.
 * Nome evita colisão com App\Http\Middleware\AuditLog.
 */
class AuditLogModel extends Model
{
    protected $table = 'AUDIT_LOG';

    protected $primaryKey = 'id';

    public $timestamps = true;

    public static $snakeAttributes = false;

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * @var list<string>
     */
    protected $guarded = [];

    protected static function booted()
    {
        static::deleting(function () {
            if (Schema::hasTable('AUDIT_LOG')) {
                throw new SecurityException(
                    'AUDIT_LOG: remoção proibida; use cópia off-site (StreamAuditToSecureVault).'
                );
            }
        });

        static::saving(function (self $m) {
            if ($m->exists && (bool) config('gente.audit_log.immutability', true)) {
                throw new SecurityException('AUDIT_LOG: não é permitido regravar registos existentes.');
            }
        });

        static::creating(function (self $m) {
            if (! AuditLogChainer::enabled() || $m->HASH_CONCAT !== null) {
                return;
            }
            $attrs = $m->getAttributes();
            if ($attrs === []) {
                return;
            }
            $m->HASH_CONCAT = AuditLogChainer::computeRowHash($attrs);
        });
    }

    /**
     * @return never
     */
    public function delete()
    {
        throw new SecurityException('AUDIT_LOG: delete() proibido.');
    }

    /**
     * @param  array  $options
     */
    public function save(array $options = [])
    {
        if ($this->exists && (bool) config('gente.audit_log.immutability', true)) {
            throw new SecurityException('AUDIT_LOG: save() de registo existente proibido.');
        }

        return parent::save($options);
    }
}
