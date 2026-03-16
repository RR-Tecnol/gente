import { Slot, Redirect } from 'expo-router'
import { useEffect, useState } from 'react'
import * as SecureStore from 'expo-secure-store'
import { View, ActivityIndicator } from 'react-native'

export default function RootLayout() {
    return <Slot />
}
