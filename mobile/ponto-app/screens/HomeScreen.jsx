import React, { useEffect, useState, useCallback } from 'react'
import {
    View, Text, ScrollView, TouchableOpacity,
    StyleSheet, ActivityIndicator, RefreshControl,
} from 'react-native'
import * as SecureStore from 'expo-secure-store'
import { useRouter, useFocusEffect } from 'expo-router'
import { Ionicons } from '@expo/vector-icons'
import api from '../services/api'

const TIPOS = {
    ENTRADA: { label: 'Entrada', icon: '🟢', cor: '#22c55e' },
    PAUSA: { label: 'Pausa', icon: '🟡', cor: '#eab308' },
    RETORNO: { label: 'Retorno', icon: '🔵', cor: '#3b82f6' },
    SAIDA: { label: 'Saída', icon: '🔴', cor: '#ef4444' },
}

export default function HomeScreen() {
    const [nome, setNome] = useState('')
    const [status, setStatus] = useState(null)
    const [loading, setLoading] = useState(true)
    const [refreshing, setRefreshing] = useState(false)
    const router = useRouter()

    const carregar = async () => {
        try {
            const n = await SecureStore.getItemAsync('usuario_nome')
            setNome(n ?? '')
            const { data } = await api.get('/ponto/app/status-hoje')
            setStatus(data)
        } catch {
            setStatus(null)
        } finally {
            setLoading(false)
            setRefreshing(false)
        }
    }

    useFocusEffect(useCallback(() => { carregar() }, []))

    const sair = async () => {
        await SecureStore.deleteItemAsync('jwt_token')
        await SecureStore.deleteItemAsync('usuario_nome')
        router.replace('/')
    }

    if (loading) {
        return <View style={s.center}><ActivityIndicator color="#1a56db" size="large" /></View>
    }

    const proximaLabel = status?.proxima ? TIPOS[status.proxima]?.label : null

    return (
        <ScrollView
            style={s.bg}
            contentContainerStyle={{ paddingBottom: 40 }}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); carregar() }} />}
        >
            {/* Header */}
            <View style={s.header}>
                <View>
                    <Text style={s.saudacao}>👋 Olá,</Text>
                    <Text style={s.nomeText}>{nome || 'Servidor'}</Text>
                </View>
                <TouchableOpacity onPress={sair} style={s.btnSair}>
                    <Text style={s.btnSairText}>Sair</Text>
                </TouchableOpacity>
            </View>

            {/* Data */}
            <Text style={s.dataHoje}>
                {new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' })}
            </Text>

            {/* Cards de ponto */}
            <View style={s.cardsGrid}>
                {Object.entries(TIPOS).map(([key, t]) => (
                    <View key={key} style={[s.card, { borderLeftColor: t.cor }]}>
                        <Text style={s.cardIcon}>{t.icon}</Text>
                        <Text style={s.cardLabel}>{t.label}</Text>
                        <Text style={[s.cardHora, { color: status?.[key.toLowerCase()] ? '#f1f5f9' : '#475569' }]}>
                            {status?.[key.toLowerCase()] ?? '--:--'}
                        </Text>
                    </View>
                ))}
            </View>

            {/* Próxima batida */}
            {proximaLabel && (
                <View style={s.proximaBox}>
                    <Text style={s.proximaText}>Próxima batida: <Text style={s.proximaBold}>{proximaLabel}</Text></Text>
                </View>
            )}

            {/* Botão principal */}
            <TouchableOpacity
                style={[s.btnPonto, !proximaLabel && s.btnPontoDisabled]}
                disabled={!proximaLabel}
                onPress={() => router.push('/bater-ponto')}
            >
                <Text style={s.btnPontoIcon}>✋</Text>
                <Text style={s.btnPontoText}>
                    {proximaLabel ? `Registrar ${proximaLabel}` : 'Dia encerrado'}
                </Text>
            </TouchableOpacity>

            {/* Link histórico */}
            <TouchableOpacity style={s.btnHistorico} onPress={() => router.push('/historico')}>
                <Text style={s.btnHistoricoText}>📋 Ver Histórico de Ponto</Text>
            </TouchableOpacity>

            <TouchableOpacity style={s.btnSecundario} onPress={() => router.push('/holerites')}>
                <Ionicons name="document-text-outline" size={20} color="#3b82f6" />
                <Text style={s.btnSecundarioText}>Meus Holerites</Text>
            </TouchableOpacity>

            <TouchableOpacity style={s.btnSecundario} onPress={() => router.push('/escala')}>
                <Ionicons name="calendar-outline" size={20} color="#3b82f6" />
                <Text style={s.btnSecundarioText}>Minha Escala</Text>
            </TouchableOpacity>
        </ScrollView>
    )
}

const s = StyleSheet.create({
    bg: { flex: 1, backgroundColor: '#0f172a' },
    center: { flex: 1, backgroundColor: '#0f172a', alignItems: 'center', justifyContent: 'center' },
    header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 24, paddingTop: 56 },
    saudacao: { fontSize: 14, color: '#64748b' },
    nomeText: { fontSize: 22, fontWeight: '700', color: '#f1f5f9' },
    btnSair: { backgroundColor: '#1e293b', borderRadius: 8, paddingHorizontal: 14, paddingVertical: 8 },
    btnSairText: { color: '#94a3b8', fontSize: 13 },
    dataHoje: { fontSize: 14, color: '#64748b', paddingHorizontal: 24, marginTop: -8, marginBottom: 20, textTransform: 'capitalize' },
    cardsGrid: { flexDirection: 'row', flexWrap: 'wrap', paddingHorizontal: 16, gap: 12, marginBottom: 20 },
    card: {
        width: '47%', backgroundColor: '#1e293b', borderRadius: 14,
        padding: 16, borderLeftWidth: 4,
    },
    cardIcon: { fontSize: 20, marginBottom: 6 },
    cardLabel: { fontSize: 12, color: '#94a3b8', marginBottom: 4 },
    cardHora: { fontSize: 20, fontWeight: '700' },
    proximaBox: { marginHorizontal: 24, backgroundColor: '#1e293b', borderRadius: 12, padding: 14, marginBottom: 24 },
    proximaText: { color: '#94a3b8', textAlign: 'center', fontSize: 14 },
    proximaBold: { color: '#1a56db', fontWeight: '700' },
    btnPonto: {
        marginHorizontal: 24, backgroundColor: '#1a56db', borderRadius: 16,
        padding: 18, alignItems: 'center', flexDirection: 'row', justifyContent: 'center', gap: 10,
        shadowColor: '#1a56db', shadowOpacity: 0.4, shadowRadius: 12, elevation: 8,
    },
    btnPontoDisabled: { backgroundColor: '#334155', shadowOpacity: 0 },
    btnPontoIcon: { fontSize: 22 },
    btnPontoText: { color: '#fff', fontWeight: '700', fontSize: 17 },
    btnHistorico: { marginHorizontal: 24, marginTop: 14, padding: 14, alignItems: 'center' },
    btnHistoricoText: { color: '#64748b', fontSize: 14 },
    btnSecundario: { marginHorizontal: 24, marginTop: 12, backgroundColor: '#1e293b', borderRadius: 12, padding: 14, alignItems: 'center', flexDirection: 'row', justifyContent: 'center', gap: 8 },
    btnSecundarioText: { color: '#f1f5f9', fontWeight: '600', fontSize: 15 },
})
