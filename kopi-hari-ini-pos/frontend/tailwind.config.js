/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // === Kopi Hari Ini Design System ===
        ink:        '#15120F',   // teks utama
        'ink-70':   '#4A423B',   // teks isi
        'ink-45':   '#7A6F66',   // label
        ground:     '#F7F4EF',   // latar halaman
        surface:    '#FFFFFF',   // kartu
        line:       '#E6E0D8',   // garis dan pembatas
        kopi:       '#9C4A21',   // aksen, tombol utama
        'kopi-10':  '#F7E9DF',   // latar aksen
        laba:       '#1D6B55',   // nilai positif
        perhatian:  '#9A6B06',   // stok menipis
        kritis:     '#A32B2B',   // stok habis, selisih negatif
        offline:    '#1F4571',   // status sinkronisasi
        // Extended shades
        'kopi-dark':  '#7A3818',
        'kopi-light': '#C4774A',
        'ink-dark':   '#0A0906',
        'surface-2':  '#FAF8F5',
        'laba-10':    '#E8F5F1',
        'kritis-10':  '#FDEAEA',
        'perhatian-10': '#FDF6E3',
        'offline-10': '#E8EFF7',
      },
      fontFamily: {
        display: ['"Fraunces"', 'Georgia', 'serif'],
        sans:    ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
        mono:    ['"JetBrains Mono"', '"Courier New"', 'monospace'],
      },
      fontSize: {
        'display': ['54px', { lineHeight: '1.1', fontWeight: '700' }],
        'page':    ['28px', { lineHeight: '1.2', fontWeight: '700' }],
        'card':    ['19px', { lineHeight: '1.3', fontWeight: '600' }],
        'body':    ['14px', { lineHeight: '1.5', fontWeight: '400' }],
        'label':   ['12px', { lineHeight: '1', fontWeight: '700', letterSpacing: '0.08em' }],
      },
      borderRadius: {
        'control': '10px',
        'card':    '16px',
        'pill':    '999px',
      },
      boxShadow: {
        'card':   '0 1px 3px rgba(21,18,15,0.06), 0 4px 12px rgba(21,18,15,0.04)',
        'card-hover': '0 4px 16px rgba(21,18,15,0.1), 0 1px 3px rgba(21,18,15,0.08)',
        'modal':  '0 20px 60px rgba(21,18,15,0.2)',
        'btn':    '0 1px 2px rgba(21,18,15,0.12)',
      },
      spacing: {
        'sidebar': '260px',
        'touch':   '44px',
      },
      animation: {
        'fade-in':   'fadeIn 0.2s ease-out',
        'slide-up':  'slideUp 0.3s ease-out',
        'slide-in':  'slideIn 0.25s ease-out',
        'pulse-dot': 'pulseDot 2s ease-in-out infinite',
        'shimmer':   'shimmer 1.5s ease-in-out infinite',
      },
      keyframes: {
        fadeIn:   { from: { opacity: '0' }, to: { opacity: '1' } },
        slideUp:  { from: { opacity: '0', transform: 'translateY(12px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
        slideIn:  { from: { opacity: '0', transform: 'translateX(-8px)' }, to: { opacity: '1', transform: 'translateX(0)' } },
        pulseDot: { '0%, 100%': { opacity: '1' }, '50%': { opacity: '0.4' } },
        shimmer:  { '0%': { backgroundPosition: '-200%' }, '100%': { backgroundPosition: '200%' } },
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
