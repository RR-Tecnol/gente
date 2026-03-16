import React, { useState } from 'react'
import {
    View, Text, TextInput, TouchableOpacity, StyleSheet,
    ActivityIndicator, Alert, KeyboardAvoidingView, Platform, Image,
} from 'react-native'
import * as SecureStore from 'expo-secure-store'
import { useRouter } from 'expo-router'
import api from '../services/api'

export default function LoginScreen() {
    const [cpf, setCpf] = useState('')
    const [senha, setSenha] = useState('')
    const [loading, setLoading] = useState(false)
    const router = useRouter()

    const formatarCpf = (v) => {
        const n = v.replace(/\D/g, '').slice(0, 11)
        return n.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4')
    }

    const handleLogin = async () => {
        const cpfNum = cpf.replace(/\D/g, '')
        if (cpfNum.length < 11 || !senha) {
            return Alert.alert('Atenção', 'Preencha CPF e senha.')
        }
        setLoading(true)
        try {
            const { data } = await api.post('/ponto/app/login', { cpf: cpfNum, senha })
            await SecureStore.setItemAsync('jwt_token', data.token)
            await SecureStore.setItemAsync('usuario_nome', data.nome)
            router.replace('/home')
        } catch (e) {
            const msg = e.response?.data?.erro ?? 'Erro ao conectar ao servidor.'
            Alert.alert('Erro', msg)
        } finally {
            setLoading(false)
        }
    }

    return (
        <KeyboardAvoidingView style={s.bg} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <View style={s.card}>
                {/* Logo / Ícone */}
                <View style={s.logoBox}>
                    <Text style={s.logoIcon}>✋</Text>
                    <Text style={s.logoTitle}>Ponto GENTE</Text>
                    <Text style={s.logoSub}>Registro de Ponto Eletrônico</Text>
                </View>

                {/* Campos */}
                <TextInput
                    style={s.input}
                    placeholder="CPF (000.000.000-00)"
                    placeholderTextColor="#94a3b8"
                    keyboardType="numeric"
                    value={cpf}
                    onChangeText={(v) => setCpf(formatarCpf(v))}
                    maxLength={14}
                />
                <TextInput
                    style={s.input}
                    placeholder="Senha"
                    placeholderTextColor="#94a3b8"
                    secureTextEntry
                    value={senha}
                    onChangeText={setSenha}
                />

                <TouchableOpacity style={s.btn} onPress={handleLogin} disabled={loading}>
                    {loading ? <ActivityIndicator color="#fff" /> : <Text style={s.btnText}>Entrar</Text>}
                </TouchableOpacity>
            </View>
        </KeyboardAvoidingView>
    )
}

const s = StyleSheet.create({
    bg: {
        flex: 1, backgroundColor: '#0f172a',
        alignItems: 'center', justifyContent: 'center',
    },
    card: {
        width: '88%', backgroundColor: '#1e293b',
        borderRadius: 20, padding: 28,
        shadowColor: '#000', shadowOpacity: 0.4, shadowRadius: 20, elevation: 10,
    },
    logoBox: { alignItems: 'center', marginBottom: 32 },
    logoIcon: { fontSize: 52, marginBottom: 8 },
    logoTitle: { fontSize: 26, fontWeight: '700', color: '#f8fafc', letterSpacing: 1 },
    logoSub: { fontSize: 13, color: '#64748b', marginTop: 4 },
    input: {
        backgroundColor: '#0f172a', borderRadius: 12, padding: 14,
        color: '#f1f5f9', fontSize: 15, marginBottom: 14,
        borderWidth: 1, borderColor: '#334155',
    },
    btn: {
        backgroundColor: '#1a56db', borderRadius: 12,
        padding: 16, alignItems: 'center', marginTop: 8,
    },
    btnText: { color: '#fff', fontWeight: '700', fontSize: 16 },
})
