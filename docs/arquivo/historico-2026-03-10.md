## [2026-03-10] BOM em web.php corrompendo todas as respostas JSON

**Sintoma:**
> Dashboard exibe "Bom dia, Usuário" e valores "Undefined" mesmo após login bem-sucedido com `admin`. O `authStore.user` no Pinia contém uma **string** com BOM em vez de um objeto JS. Acesso a `user.nome`, `user.perfil` retorna `undefined`.

**Causa:**
`routes/web.php` continha BOM UTF-8 (bytes `EF BB BF`) no início do arquivo — introduzido por editor de texto no Windows. Quando o PHP carrega o arquivo, esse BOM vazava para o body HTTP **antes dos headers JSON**, impedindo o Axios de parsear a resposta como objeto. Resultado: `authStore.user` era uma string `"﻿{...}"` em vez de `{...}`.

**Solução:**
1. Detectar BOM com PowerShell:
```powershell
Get-Content 'routes\web.php' -Encoding Byte -TotalCount 3
# BOM = 239 187 191 (EF BB BF)
# OK  = 60 63 112  (< ? p)
```
2. Remover BOM:
```powershell
$bytes = [IO.File]::ReadAllBytes('routes\web.php')
if ($bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
    [IO.File]::WriteAllBytes('routes\web.php', $bytes[3..($bytes.Length-1)])
}
```
3. No `auth.js`, parse defensivo como proteção extra:
```js
if (typeof data === 'string') {
    this.user = JSON.parse(data.replace(/^\uFEFF/, '').trim())
} else {
    this.user = data
}
```

**Como identificar no futuro:**
```powershell
Get-Content 'routes\web.php' -Encoding Byte -TotalCount 3
# 239 187 191 = BOM presente = vai corromper respostas JSON
# 60 63 112   = OK
```
No browser: `JSON.stringify(pinia.state.value.auth.user)` começa com `\ufeff`.

---

## [2026-03-10] Login admin exibia perfil "Gestor" no dashboard

**Sintoma:**
> Usuário `admin` logava com sucesso mas o dashboard exibia "Bom dia, Gestor" sem menu de admin.

**Causa:**
Endpoint `/me` em `routes/web.php` consultava `usuarioPerfis` primeiro. O admin tinha registro "Gestor" nessa tabela, sobrepondo o fallback.

**Solução:**
Verificar `USUARIO_LOGIN === 'admin'` **antes** de consultar a relação em `/me`:
```php
if (strtolower($user->USUARIO_LOGIN) === 'admin') {
    $perfilNome = 'admin';
} else {
    $perfilNome = optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? null;
    ...
}
```

**Como identificar no futuro:**
Acessar `/api/auth/me` logado como admin; se `perfil` ≠ `admin`, verificar lógica `/me` em `routes/web.php`.

---
