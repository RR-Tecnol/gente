const mix = require('laravel-mix');

/*
 | O frontend legado (Vue 2 + Vuetify) foi removido em 04/03/2026.
 | O novo frontend está em resources/gente-v3 (Vue 3 + Vite).
 | Este arquivo mantém apenas a compilação do SASS global do Laravel.
 */

mix.sass('resources/sass/app.scss', 'public/css');