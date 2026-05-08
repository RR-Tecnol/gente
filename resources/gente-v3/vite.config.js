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
  // Output do build de produção
  // O build vai pra ../../public/build-v3/ (relativo a resources/gente-v3/)
  // O Laravel lê o manifest em public_path('build-v3/.vite/manifest.json')
  build: {
    outDir: '../../public/build-v3',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: 'src/main.js',
    },
  },
  // Base URL para asset paths no manifest e no HTML do app.blade.php
  // Em produção, os assets ficam em /build-v3/assets/<hash>.js
  base: '/build-v3/',
  server: {
    host: '127.0.0.1',
    port: 5173,
    proxy: {
      // ⚠️  Apenas rotas REAIS do Laravel — todas as outras vão para o Vue Router
      '^/(api|csrf-cookie|sanctum|storage|remessa)': {
        target: 'http://127.0.0.1:8081',
        changeOrigin: true,
        secure: false,
        // Reescreve o domínio dos cookies para que o browser em :5173 os aceite
        cookieDomainRewrite: '127.0.0.1',
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

