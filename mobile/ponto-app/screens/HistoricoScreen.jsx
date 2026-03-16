import React, { useEffect, useState } from 'react'
import {
    View, Text, ScrollView, StyleSheet,
    ActivityIndicator, RefreshControl, TouchableOpacity,
} from 'react-native'
import { useRouter } from 'expo-router'
import api from '../services/api'

const TIPO_COR = {
    ENTRADA: '#22c55e', PAUSA: '#eab308',
    RETORNO: '#3b82f6', SAIDA: '#ef4444',
}
const TIPO_ICONE = {
    ENTRADA: '🟢', PAUSA: '🟡', RETORNO: '🔵', SAIDA: '🔴',
}

export default function HistoricoScreen() {
    const [dias, setDias] = useState([])
    const [loading, setLoading] = useState(true)
    const [refreshing, setRefreshing] = useState(false)
    const router = useRouter()

    const carregar = async () => {
        try {
            const { data } = await api.get('/ponto/app/registros')
            setDias(data)
        } catch {
            setDias([])
        } finally {
            setLoading(false)
            setRefreshing(false)
        }
    }

    useEffect(() => { carregar() }, [])

    const formatarData = (iso) => {
        const d = new Date(iso + 'T00:00:00')
        return d.toLocaleDateString('pt-BR', { weekday: 'short', day: 'numeric', month: 'short' })
    }

    if (loading) {
        return <View style={s.center}><ActivityIndicator color="#1a56db" size="large" /></View>
    }

    return (
        <ScrollView
            style={s.bg}
            contentContainerStyle={{ padding: 20, paddingBottom: 40 }}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); carregar() }} />}
        >
            {/* Header */}
            <View style={s.header}>
                <TouchableOpacity onPress={() => router.back()} style={s.btnVoltar}>
                    <Text style={s.btnVoltarTxt}>← Voltar</Text>
                </TouchableOpacity>
                <Text style={s.titulo}>Histórico</Text>
            </View>

            {dias.length === 0 && (
                <View style={s.vazio}>
                    <Text style={{ fontSize: 40, marginBottom: 12 }}>📋</Text>
                    <Text style={s.vazioTxt}>Nenhum registro nos últimos 30 dias.</Text>
                </View>
            )}

            {dias.map((dia) => (
                <View key={dia.data} style={s.diaCard}>
                    <Text style={s.diaLabel}>{formatarData(dia.data)}</Text>
                    {dia.registros.map((r) => (
                        <View key={r.id} style={s.regRow}>
                            <Text style={s.regIcone}>{TIPO_ICONE[r.tipo] ?? '⚪'}</Text>
                            <View style={{ flex: 1 }}>
                                <Text style={[s.regTipo, { color: TIPO_COR[r.tipo] ?? '#94a3b8' }]}>{r.tipo}</Text>
                                <Text style={s.regHora}>{r.hora}</Text>
                            </View>
                            <View style={s.badgeWrap}>
                                {r.face_ok && <View style={s.badgeFace}><Text style={s.badgeTxt}>📷 Facial</Text></View>}
                                {r.distancia_m !== null && (
                                    <View style={s.badgeGps}>
                                        <Text style={s.badgeTxt}>📍 {r.distancia_m}m</Text>
                                    </View>
                                )}
                            </View>
                        </View>
                    ))}
                </View>
            ))}
        </ScrollView>
    )
}

const s = StyleSheet.create({
    bg: { flex: 1, backgroundColor: '#0f172a' },
    center: { flex: 1, backgroundColor: '#0f172a', alignItems: 'center', justifyContent: 'center' },
    header: { flexDirection: 'row', alignItems: 'center', marginBottom: 20, paddingTop: 16 },
    btnVoltar: { marginRight: 16 },
    btnVoltarTxt: { color: '#1a56db', fontSize: 15, fontWeight: '600' },
    titulo: { fontSize: 22, fontWeight: '700', color: '#f1f5f9' },
    vazio: { alignItems: 'center', marginTop: 60 },
    vazioTxt: { color: '#64748b', fontSize: 15 },
    diaCard: {
        backgroundColor: '#1e293b', borderRadius: 14, padding: 16, marginBottom: 14,
    },
    diaLabel: { fontSize: 13, color: '#64748b', textTransform: 'capitalize', marginBottom: 10 },
    regRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 8, borderTopWidth: 1, borderTopColor: '#0f172a' },
    regIcone: { fontSize: 20, marginRight: 12 },
    regTipo: { fontSize: 13, fontWeight: '700', textTransform: 'uppercase' },
    regHora: { fontSize: 20, fontWeight: '700', color: '#f1f5f9', marginTop: 2 },
    badgeWrap: { flexDirection: 'column', gap: 4, alignItems: 'flex-end' },
    badgeFace: { backgroundColor: '#1e3a5f', borderRadius: 6, paddingHorizontal: 8, paddingVertical: 3 },
    badgeGps: { backgroundColor: '#1a3a2a', borderRadius: 6, paddingHorizontal: 8, paddingVertical: 3 },
    badgeTxt: { color: '#94a3b8', fontSize: 11 },
})
