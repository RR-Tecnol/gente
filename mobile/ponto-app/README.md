# 📱 Ponto GENTE — App Mobile

App de registro de ponto com reconhecimento facial e validação de geolocalização.

## Pré-requisitos

- [Node.js 18+](https://nodejs.org)
- [Expo Go](https://expo.dev/client) instalado no celular (Android/iOS)

## ⚙️ Configuração antes de rodar

### 1. Ajuste a URL do backend

Edite o arquivo `services/api.js` e altere `BASE_URL` para o IP do seu computador:

```js
const BASE_URL = 'http://SEU_IP_LOCAL:8000/api/v3'
// Exemplo: 'http://192.168.1.105:8000/api/v3'
```

> Para descobrir seu IP: `ipconfig` no Windows → "Endereço IPv4"

### 2. Cadastre o terminal de ponto no sistema web

No painel admin, cadastre um Terminal de Ponto e preencha:
- **Latitude / Longitude** do local de trabalho
- **Raio** (padrão: 50 metros)

## 🚀 Rodando o app

```bash
cd mobile/ponto-app
npm install
npx expo start
```

Escaneie o QR Code com o **Expo Go** no celular (Android) ou com a **câmera** (iOS).

> ⚠️ O celular e o computador precisam estar na **mesma rede Wi-Fi**.

## 🏗️ Build para produção (APK / IPA)

```bash
# Instale o EAS CLI
npm install -g eas-cli
eas login

# Build Android (APK)
eas build --platform android --profile preview

# Build iOS
eas build --platform ios
```

## 📂 Estrutura

```
mobile/ponto-app/
├── app/                  ← Rotas (expo-router)
│   ├── _layout.jsx
│   ├── index.jsx         ← Redireciona para login ou home
│   ├── login.jsx
│   ├── home.jsx
│   ├── bater-ponto.jsx
│   └── historico.jsx
├── screens/              ← Telas
│   ├── LoginScreen.jsx
│   ├── HomeScreen.jsx
│   ├── BaterPontoScreen.jsx
│   └── HistoricoScreen.jsx
├── services/
│   ├── api.js            ← Axios + JWT interceptor
│   └── FaceService.js    ← Abstração facial (trocar aqui para AWS/Azure)
├── app.json
└── package.json
```

## 🔄 Migrar reconhecimento facial para AWS/Azure

Edite apenas `services/FaceService.js` — as telas não precisam mudar.
