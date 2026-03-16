import React, { useState, useRef, useEffect } from 'react'
import {
    View, Text, TouchableOpacity, StyleSheet,
    ActivityIndicator, Alert, Dimensions,
} from 'react-native'
import { CameraView, useCameraPermissions } from 'expo-camera'
import * as FaceDetector from 'expo-face-detector'
import * as Location from 'expo-location'
import { useRouter, useLocalSearchParams } from 'expo-router'
import api from '../services/api'
import { validarRostoDetectado } from '../services/FaceService'

const { width } = Dimensions.get('window')

export default function BaterPontoScreen() {
    const [permission, requestPermission] = useCameraPermissions()
    const [faceStatus, setFaceStatus] = useState('Centralize seu rosto')
    const [faceOk, setFaceOk] = useState(false)
    const [progresso, setProgresso] = useState(0)
    const [capturado, setCapturado] = useState(false)
    const [enviando, setEnviando] = useState(false)
    const [resultado, setResultado] = useState(null) // { ok, msg, hora }
    const cameraRef = useRef(null)
    const router = useRouter()
    const params = useLocalSearchParams()
    const tipo = params.tipo ?? 'ENTRADA'

    useEffect(() => { if (!permission?.granted) requestPermission() }, [])

    const onFacesDetected = ({ faces }) => {
        if (capturado || enviando) return
        const { ok, message } = validarRostoDetectado(faces)
        setFaceStatus(message)
        setFaceOk(ok)
        if (ok) {
            setProgresso((p) => {
                const novo = Math.min(p + 8, 100)
                if (novo >= 100) capturarEEnviar()
                return novo
            })
        } else {
            setProgresso((p) => Math.max(p - 4, 0))
        }
    }

    const capturarEEnviar = async () => {
        if (capturado || enviando || !cameraRef.current) return
        setCapturado(true)
        setEnviando(true)

        try {
            // Captura foto
            const foto = await cameraRef.current.takePictureAsync({ quality: 0.5, base64: true })

            // Pega localização
            const locPerm = await Location.requestForegroundPermissionsAsync()
            let lat = null, lng = null
            if (locPerm.granted) {
                const loc = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High })
                lat = loc.coords.latitude
                lng = loc.coords.longitude
            }

            // Envia para o backend
            const { data } = await api.post('/ponto/app/registrar', {
                tipo,
                latitude: lat,
                longitude: lng,
                face_ok: true,
                foto_base64: foto.base64,
            })

            setResultado({ ok: true, msg: `✅ ${data.tipo} registrada às ${data.hora}`, hora: data.hora })
        } catch (e) {
            const msg = e.response?.data?.erro ?? 'Erro ao registrar ponto.'
            setResultado({ ok: false, msg })
        } finally {
            setEnviando(false)
        }
    }

    if (!permission) return <View style={s.bg} />
    if (!permission.granted) {
        return (
            <View style={s.bg}>
                <Text style={s.txt}>Permissão de câmera necessária.</Text>
                <TouchableOpacity style={s.btn} onPress={requestPermission}>
                    <Text style={s.btnTxt}>Permitir Câmera</Text>
                </TouchableOpacity>
            </View>
        )
    }

    // Tela de resultado
    if (resultado) {
        return (
            <View style={[s.bg, { justifyContent: 'center', alignItems: 'center', padding: 32 }]}>
                <Text style={{ fontSize: 64, marginBottom: 24 }}>{resultado.ok ? '✅' : '❌'}</Text>
                <Text style={[s.txt, { fontSize: 18, textAlign: 'center', marginBottom: 32 }]}>{resultado.msg}</Text>
                <TouchableOpacity style={s.btn} onPress={() => router.replace('/home')}>
                    <Text style={s.btnTxt}>Voltar ao Início</Text>
                </TouchableOpacity>
            </View>
        )
    }

    return (
        <View style={s.bg}>
            {/* Câmera */}
            <CameraView
                ref={cameraRef}
                style={StyleSheet.absoluteFill}
                facing="front"
                faceDetectorSettings={{
                    mode: FaceDetector.FaceDetectorMode.fast,
                    detectLandmarks: FaceDetector.FaceDetectorLandmarks.none,
                    runClassifications: FaceDetector.FaceDetectorClassifications.none,
                    minDetectionInterval: 150,
                    tracking: true,
                }}
                onFacesDetected={onFacesDetected}
            />

            {/* Overlay */}
            <View style={s.overlay}>
                {/* Tipo da batida */}
                <View style={s.tipoBadge}>
                    <Text style={s.tipoText}>Registrando: {tipo}</Text>
                </View>

                {/* Guia do rosto */}
                <View style={[s.faceGuide, { borderColor: faceOk ? '#22c55e' : '#64748b' }]} />

                {/* Barra de progresso */}
                <View style={s.progressoWrap}>
                    <View style={[s.progressoBar, { width: `${progresso}%`, backgroundColor: faceOk ? '#22c55e' : '#64748b' }]} />
                </View>

                {/* Status */}
                <View style={s.statusBox}>
                    {enviando
                        ? <ActivityIndicator color="#fff" />
                        : <Text style={s.statusText}>{faceStatus}</Text>
                    }
                </View>

                {/* Botão cancelar */}
                <TouchableOpacity style={s.btnCancel} onPress={() => router.back()}>
                    <Text style={s.btnCancelTxt}>Cancelar</Text>
                </TouchableOpacity>
            </View>
        </View>
    )
}

const s = StyleSheet.create({
    bg: { flex: 1, backgroundColor: '#0f172a', justifyContent: 'center' },
    overlay: {
        ...StyleSheet.absoluteFillObject,
        alignItems: 'center', justifyContent: 'space-between',
        paddingVertical: 60, paddingHorizontal: 24,
    },
    tipoBadge: {
        backgroundColor: 'rgba(26,86,219,0.85)', borderRadius: 20,
        paddingHorizontal: 20, paddingVertical: 8,
    },
    tipoText: { color: '#fff', fontWeight: '700', fontSize: 15 },
    faceGuide: {
        width: width * 0.65, height: width * 0.65, borderRadius: width * 0.35,
        borderWidth: 3, borderStyle: 'dashed',
    },
    progressoWrap: {
        width: '80%', height: 6, backgroundColor: '#1e293b', borderRadius: 3, overflow: 'hidden',
    },
    progressoBar: { height: '100%', borderRadius: 3 },
    statusBox: {
        backgroundColor: 'rgba(15,23,42,0.85)', borderRadius: 12,
        paddingHorizontal: 20, paddingVertical: 12, minWidth: 200, alignItems: 'center',
    },
    statusText: { color: '#f1f5f9', fontSize: 14, textAlign: 'center' },
    btnCancel: {
        backgroundColor: 'rgba(239,68,68,0.85)', borderRadius: 12,
        paddingHorizontal: 28, paddingVertical: 12,
    },
    btnCancelTxt: { color: '#fff', fontWeight: '700', fontSize: 15 },
    txt: { color: '#f1f5f9', fontSize: 16, textAlign: 'center', marginBottom: 16 },
    btn: { backgroundColor: '#1a56db', borderRadius: 12, padding: 14, alignItems: 'center' },
    btnTxt: { color: '#fff', fontWeight: '700', fontSize: 15 },
})
