{{-- PWA: manifest, íconos, color de tema, estilos responsive y registro del Service Worker. Va dentro de <head>. --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">
<meta name="theme-color" content="#0f172a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Asistencia EET 2">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<link rel="stylesheet" href="{{ asset('css/pwa.css') }}">
<script src="{{ asset('js/pwa.js') }}" data-sw="{{ asset('sw.js') }}"></script>
