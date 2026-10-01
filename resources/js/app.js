// Prueba de agente de voz: solo se carga en /web_test/*
if (document.querySelector('[data-web-test]')) {
    import('./web-test');
}