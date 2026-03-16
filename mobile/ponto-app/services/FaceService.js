/**
 * FaceService — Camada de abstração para reconhecimento facial.
 *
 * FASE 1 (atual): usa expo-face-detector (gratuito, local no dispositivo).
 * FASE 2 (futuro): trocar apenas este arquivo para usar AWS Rekognition
 *                  ou qualquer outro provider, sem alterar as telas.
 */

// ── Fase 1: detecção local via expo-face-detector ──────────────────
// O expo-face-detector roda diretamente na câmera em tempo real.
// A tela de câmera chama `onFacesDetected` e passa aqui para validar.

/**
 * Verifica se o resultado da detecção facial é válido.
 * @param {Array} faces - Array retornado pelo expo-face-detector
 * @returns {{ ok: boolean, message: string }}
 */
export function validarRostoDetectado(faces) {
    if (!faces || faces.length === 0) {
        return { ok: false, message: 'Nenhum rosto detectado. Centralize seu rosto na câmera.' }
    }
    if (faces.length > 1) {
        return { ok: false, message: 'Mais de um rosto detectado. Fique sozinho na câmera.' }
    }

    const face = faces[0]
    const bounds = face.bounds

    // Verifica tamanho mínimo do rosto no frame (garante que está perto o suficiente)
    if (bounds.size.width < 100 || bounds.size.height < 100) {
        return { ok: false, message: 'Aproxime o rosto da câmera.' }
    }

    return { ok: true, message: 'Rosto detectado!' }
}

// ── Fase 2 (descomentar para migrar para AWS/Azure) ─────────────────
//
// import { RekognitionClient, DetectFacesCommand } from '@aws-sdk/client-rekognition'
//
// const client = new RekognitionClient({ region: 'us-east-1', credentials: { ... } })
//
// export async function validarRostoCloud(fotoBase64) {
//   const bytes = Buffer.from(fotoBase64, 'base64')
//   const cmd = new DetectFacesCommand({ Image: { Bytes: bytes } })
//   const res = await client.send(cmd)
//   return { ok: res.FaceDetails.length === 1, message: '...' }
// }
