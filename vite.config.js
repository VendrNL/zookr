import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
  ],

  server: {
    host: '0.0.0.0',
    port: Number(process.env.VITE_PORT) || 5174,
    strictPort: false,

    // HMR over websocket richting je browser op Windows/localhost
    hmr: {
      host: 'localhost',
      protocol: 'ws',
    },
  },
});
