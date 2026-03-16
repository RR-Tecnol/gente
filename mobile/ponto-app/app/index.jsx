import { Redirect } from 'expo-router'
import { useEffect, useState } from 'react'
import * as SecureStore from 'expo-secure-store'
import { View, ActivityIndicator } from 'react-native'

export default function Index() {
    const [loading, setLoading] = useState(true)
    const [logado, setLogado] = useState(false)

    useEffect(() => {
        SecureStore.getItemAsync('jwt_token').then((t) => {
            setLogado(!!t)
            setLoading(false)
        })
    }, [])

    if (loading) {
        return (
            <View style={{ flex: 1, backgroundColor: '#0f172a', justifyContent: 'center', alignItems: 'center' }}>
                <ActivityIndicator color="#1a56db" size="large" />
            </View>
        )
    }

    return <Redirect href={logado ? '/home' : '/login'} />
}
