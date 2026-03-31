import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'
import path from 'path'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vuetify({ autoImport: true }),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    }
  },
  server: {
    host: '127.0.0.1',
    port: 5173,
    proxy: {
      // ⚠️  Apenas rotas REAIS do Laravel — todas as outras vão para o Vue Router
      '^/(api|csrf-cookie|sanctum|storage|remessa)': {
        target: 'http://127.0.0.1:8080',
        changeOrigin: true,
        secure: false,
        // Reescreve o domínio dos cookies para que o browser em :5173 os aceite
        cookieDomainRewrite: 'localhost',
        // Garante que o header Set-Cookie não tem Secure (sem HTTPS)
        configure: (proxy) => {
          proxy.on('proxyRes', (proxyRes) => {
            const setCookie = proxyRes.headers['set-cookie']
            if (setCookie) {
              // Remove a flag Secure e SameSite=Strict para funcionar em http dev
              proxyRes.headers['set-cookie'] = setCookie.map(cookie =>
                cookie
                  .replace(/;\s*Secure/gi, '')
                  .replace(/;\s*SameSite=Strict/gi, '; SameSite=Lax')
              )
            }
          })
        }
      }
    },
    historyApiFallback: true,
  }
})

