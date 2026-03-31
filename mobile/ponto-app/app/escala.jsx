import { useEffect, useState, useCallback } from 'react'
import {
    View, Text, ScrollView, TouchableOpacity,
    ActivityIndicator, StyleSheet, RefreshControl
} from 'react-native'
import { Ionicons } from '@expo/vector-icons'
import { useRouter } from 'expo-router'
import api from '../services/api'

const TURNO_COR   = { M: '#3B82F6', T: '#F59E0B', N: '#6366F1', P: '#10B981', F: '#E5E7EB', AF: '#F87171' }
const TURNO_LABEL = { M: 'Manhã', T: 'Tarde', N: 'Noite', P: 'Plantão', F: 'Folga', AF: 'Afastado' }
const MESES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro']

export default function EscalaScreen() {
    const [escala, setEscala]         = useState([])
    const [competencia, setCompetencia] = useState(() => {
        const d = new Date()
        return `${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
    })
    const [loading, setLoading]       = useState(true)
    const [refreshing, setRefreshing] = useState(false)
    const [erro, setErro]             = useState(null)
    const router = useRouter()

    const carregar = useCallback(async (comp) => {
        try {
            setErro(null)
            const { data } = await api.get('/escala/minha', { params: { competencia: comp } })
            setEscala(data.escala ?? [])
        } catch (e) {
            setErro('Não foi possível carregar a escala.')
        } finally {
            setLoading(false)
            setRefreshing(false)
        }
    }, [])

    useEffect(() => { carregar(competencia) }, [competencia])

    const mudarMes = (delta) => {
        const [mm, yyyy] = competencia.split('/')
        const d = new Date(parseInt(yyyy), parseInt(mm) - 1 + delta, 1)
        setCompetencia(`${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`)
        setLoading(true)
    }

    const escalaMap = {}
    escala.forEach(e => { escalaMap[e.data] = e })

    const [mm, yyyy] = competencia.split('/')
    const diasNoMes  = new Date(parseInt(yyyy), parseInt(mm), 0).getDate()
    const primeiroDia = new Date(parseInt(yyyy), parseInt(mm) - 1, 1).getDay()
    const dias = Array.from({ length: diasNoMes }, (_, i) => i + 1)

    const nomeMes = MESES[parseInt(mm) - 1]

    if (loading) return (
        <View style={s.center}>
            <ActivityIndicator size="large" color="#1D4ED8" />
        </View>
    )

    return (
        <View style={s.container}>
            <View style={s.header}>
                <TouchableOpacity onPress={() => router.back()} style={s.voltar}>
                    <Ionicons name="arrow-back" size={22} color="#1D4ED8" />
                </TouchableOpacity>
                <Text style={s.titulo}>Minha Escala</Text>
            </View>

            <View style={s.navMes}>
                <TouchableOpacity onPress={() => mudarMes(-1)} style={s.navBtn}>
                    <Ionicons name="chevron-back" size={22} color="#1D4ED8" />
                </TouchableOpacity>
                <Text style={s.mesLabel}>{nomeMes} {yyyy}</Text>
                <TouchableOpacity onPress={() => mudarMes(1)} style={s.navBtn}>
                    <Ionicons name="chevron-forward" size={22} color="#1D4ED8" />
                </TouchableOpacity>
            </View>

            {erro && <Text style={s.erro}>{erro}</Text>}

            <ScrollView
                contentContainerStyle={s.calGrid}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); carregar(competencia) }} />}
            >
                {['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'].map(d => (
                    <Text key={d} style={s.diaHeader}>{d}</Text>
                ))}
                {Array.from({ length: primeiroDia }).map((_, i) => (
                    <View key={`e${i}`} style={s.diaVazio} />
                ))}
                {dias.map(dia => {
                    const dataStr = `${yyyy}-${mm}-${String(dia).padStart(2, '0')}`
                    const item    = escalaMap[dataStr]
                    const turno   = item?.turno
                    const cor     = turno ? (TURNO_COR[turno] ?? '#6B7280') : null
                    const hoje    = new Date().toISOString().slice(0, 10) === dataStr

                    return (
                        <View key={dia} style={[s.diaCell, hoje && s.diaCellHoje]}>
                            <Text style={[s.diaNum, hoje && s.diaNumHoje]}>{dia}</Text>
                            {turno && (
                                <View style={[s.turnoBadge, { backgroundColor: cor }]}>
                                    <Text style={s.turnoTexto}>{turno}</Text>
                                </View>
                            )}
                        </View>
                    )
                })}
            </ScrollView>

            {/* Legenda */}
            <View style={s.legenda}>
                {Object.entries(TURNO_LABEL).map(([k, v]) => (
                    <View key={k} style={s.legendaItem}>
                        <View style={[s.legendaDot, { backgroundColor: TURNO_COR[k] ?? '#ccc' }]} />
                        <Text style={s.legendaTxt}>{k} — {v}</Text>
                    </View>
                ))}
            </View>
        </View>
    )
}

const s = StyleSheet.create({
    container:   { flex: 1, backgroundColor: '#F8FAFC' },
    center:      { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header:      { flexDirection: 'row', alignItems: 'center', padding: 16, paddingTop: 52, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#E5E7EB' },
    voltar:      { marginRight: 12 },
    titulo:      { fontSize: 18, fontWeight: '700', color: '#111827' },
    navMes:      { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 12, backgroundColor: '#fff', marginBottom: 4 },
    navBtn:      { padding: 8 },
    mesLabel:    { fontSize: 16, fontWeight: '600', color: '#1F2937' },
    calGrid:     { flexDirection: 'row', flexWrap: 'wrap', padding: 8, gap: 4 },
    diaHeader:   { width: '13%', textAlign: 'center', fontSize: 11, fontWeight: '700', color: '#6B7280', paddingVertical: 4 },
    diaVazio:    { width: '13%' },
    diaCell:     { width: '13%', minHeight: 52, borderRadius: 8, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center', padding: 2, borderWidth: 1, borderColor: '#E5E7EB' },
    diaCellHoje: { borderColor: '#1D4ED8', borderWidth: 2 },
    diaNum:      { fontSize: 13, fontWeight: '500', color: '#374151' },
    diaNumHoje:  { color: '#1D4ED8', fontWeight: '700' },
    turnoBadge:  { marginTop: 2, borderRadius: 4, paddingHorizontal: 4, paddingVertical: 1 },
    turnoTexto:  { fontSize: 9, fontWeight: '700', color: '#fff' },
    legenda:     { flexDirection: 'row', flexWrap: 'wrap', gap: 8, padding: 12, backgroundColor: '#fff', borderTopWidth: 1, borderTopColor: '#E5E7EB' },
    legendaItem: { flexDirection: 'row', alignItems: 'center', gap: 4 },
    legendaDot:  { width: 10, height: 10, borderRadius: 5 },
    legendaTxt:  { fontSize: 11, color: '#4B5563' },
    erro:        { color: '#DC2626', textAlign: 'center', margin: 16 },
})
