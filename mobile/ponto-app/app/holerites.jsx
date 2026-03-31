import { useEffect, useState, useCallback } from 'react'
import {
    View, Text, FlatList, TouchableOpacity,
    ActivityIndicator, StyleSheet, RefreshControl, Linking
} from 'react-native'
import { Ionicons } from '@expo/vector-icons'
import { useRouter } from 'expo-router'
import api from '../services/api'

const TURNO_COR = { M: '#3B82F6', T: '#F59E0B', N: '#6366F1', P: '#10B981' }
const TURNO_LABEL = { M: 'Manhã', T: 'Tarde', N: 'Noite', P: 'Plantão', F: 'Folga' }

export default function HoleritesScreen() {
    const [holerites, setHolerites] = useState([])
    const [loading, setLoading]     = useState(true)
    const [refreshing, setRefreshing] = useState(false)
    const [erro, setErro]           = useState(null)
    const router = useRouter()

    const carregar = useCallback(async () => {
        try {
            setErro(null)
            const { data } = await api.get('/meus-holerites')
            setHolerites(data.holerites ?? data.items ?? [])
        } catch (e) {
            setErro('Não foi possível carregar os holerites.')
        } finally {
            setLoading(false)
            setRefreshing(false)
        }
    }, [])

    useEffect(() => { carregar() }, [])

    const abrirPdf = async (item) => {
        const url = `${api.defaults.baseURL}/holerite-pdf/${item.detalhe_folha_id ?? item.id}`
        await Linking.openURL(url)
    }

    const formatarCompetencia = (comp) => {
        if (!comp) return '—'
        const s = String(comp)
        if (s.length === 6) return `${s.slice(4, 6)}/${s.slice(0, 4)}`
        return s
    }

    const formatarValor = (v) =>
        Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

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
                <Text style={s.titulo}>Meus Holerites</Text>
            </View>

            {erro && <Text style={s.erro}>{erro}</Text>}

            <FlatList
                data={holerites}
                keyExtractor={(_, i) => String(i)}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); carregar() }} />}
                contentContainerStyle={{ padding: 16, gap: 12 }}
                ListEmptyComponent={<Text style={s.vazio}>Nenhum holerite encontrado.</Text>}
                renderItem={({ item }) => (
                    <TouchableOpacity style={s.card} onPress={() => abrirPdf(item)} activeOpacity={0.85}>
                        <View style={s.cardLeft}>
                            <Ionicons name="document-text-outline" size={28} color="#1D4ED8" />
                        </View>
                        <View style={s.cardBody}>
                            <Text style={s.competencia}>
                                {formatarCompetencia(item.competencia ?? item.folha_competencia)}
                            </Text>
                            <Text style={s.liquido}>
                                Líquido: <Text style={s.valor}>{formatarValor(item.liquido ?? item.detalhe_folha_liquido)}</Text>
                            </Text>
                        </View>
                        <Ionicons name="download-outline" size={22} color="#6B7280" />
                    </TouchableOpacity>
                )}
            />
        </View>
    )
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: { flexDirection: 'row', alignItems: 'center', padding: 16, paddingTop: 52, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#E5E7EB' },
    voltar: { marginRight: 12 },
    titulo: { fontSize: 18, fontWeight: '700', color: '#111827' },
    card: { backgroundColor: '#fff', borderRadius: 12, padding: 16, flexDirection: 'row', alignItems: 'center', gap: 12, shadowColor: '#000', shadowOpacity: 0.06, shadowRadius: 4, elevation: 2 },
    cardLeft: { width: 44, alignItems: 'center' },
    cardBody: { flex: 1 },
    competencia: { fontSize: 16, fontWeight: '600', color: '#1F2937' },
    liquido: { fontSize: 13, color: '#6B7280', marginTop: 2 },
    valor: { color: '#059669', fontWeight: '700' },
    erro: { color: '#DC2626', textAlign: 'center', margin: 16 },
    vazio: { textAlign: 'center', color: '#9CA3AF', marginTop: 40 },
})
