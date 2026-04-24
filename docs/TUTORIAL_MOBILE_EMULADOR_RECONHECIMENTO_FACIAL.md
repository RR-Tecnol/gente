# Tutorial — Inicializar mobile no emulador (reconhecimento facial)

Este tutorial usa a estrutura real de `mobile/ponto-app`.

## 1) Pre-requisitos

- Node 18+ instalado
- Android Studio com emulador criado (AVD) **ou** iOS Simulator (macOS)
- Backend Laravel rodando e acessivel na rede/emulador

## 2) Preparar o backend antes do app

No projeto Laravel:

```bash
cd "/home/DK01/Área de trabalho/PROJECTS/GENTE/gente/gente"
php artisan serve --host=0.0.0.0 --port=8000
```

> Observacao: no seu `.env`, `DB_HOST=sqlserver`. Se o container SQL Server nao estiver ativo, o backend vai falhar.

## 3) Ajustar URL da API no mobile

Arquivo:
- `mobile/ponto-app/services/api.js`

Ajuste `BASE_URL` para o host correto:

- Android Emulator (AVD): `http://10.0.2.2:8000/api/v3`
- Genymotion: `http://10.0.3.2:8000/api/v3`
- iOS Simulator: `http://127.0.0.1:8000/api/v3`
- Dispositivo fisico: `http://SEU_IP_LOCAL:8000/api/v3`

## 4) Instalar dependencias e iniciar Expo

```bash
cd "/home/DK01/Área de trabalho/PROJECTS/GENTE/gente/gente/mobile/ponto-app"
npm install
npx expo start
```

Atalhos:
- `a` abre no Android Emulator
- `i` abre no iOS Simulator (macOS)

## 5) Permissoes e teste de reconhecimento facial

No app:
1. Fazer login (tela `LoginScreen.jsx` usa `/ponto/app/login`)
2. Abrir batida (`BaterPontoScreen.jsx`)
3. Conceder permissao de camera e localizacao
4. Centralizar rosto na moldura ate completar progresso
5. App envia:
   - `face_ok: true`
   - `foto_base64`
   - `latitude/longitude`
   para `/api/v3/ponto/app/registrar`

## 6) Validacao tecnica esperada

- Sem erro de permissao de camera
- Sem erro de localizacao
- Sem erro 404 nos endpoints mobile de ponto
- Registro retorna `ok: true` e `hora`

## 7) Problemas comuns e diagnostico

### Erro 404 em `/ponto/app/login`
- Causa provável: `routes/ponto_app.php` nao esta incluido no `routes/web.php`.
- Acao: incluir esse arquivo no grupo correto de rotas `/api/v3`.

### Timeout de banco / erro SQL ao iniciar backend
- Causa provável: SQL Server indisponivel (`DB_HOST=sqlserver`).
- Acao: subir Docker/SQL Server e testar conexao antes do mobile.

### Emulador sem camera funcional
- No Android Emulator, habilitar webcam em `Extended controls > Camera`.
- Para teste inicial, usar camera virtual/frontal configurada.

### Falha de localizacao no emulador
- Definir coordenadas em `Extended controls > Location`.
- Se nao houver GPS valido, o backend pode rejeitar por raio de terminal.

## 8) Importante sobre “reconhecimento facial” atual

No estado atual, o app usa `expo-face-detector` (detecção de rosto local), com validação:
- 1 rosto detectado
- tamanho minimo do rosto no frame

Nao há biometria de identidade 1:1/1:N com base de faces.  
Se quiser reconhecimento biometrico real (AWS Rekognition/Azure), a troca está planejada em `services/FaceService.js`.
