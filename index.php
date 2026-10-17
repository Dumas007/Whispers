<?php
require __DIR__ . '/config.php';
$wa = ajuste('whatsapp_ventas', '50200000000');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Whispers — Enterate de lo que tus clientes no te dicen</title>
<meta name="description" content="Encuestas por QR en cada mesa, alertas en tiempo real y reportes con IA para restaurantes y negocios en Guatemala.">
<meta name="theme-color" content="#141A3D">
<meta property="og:title" content="Whispers — Tus clientes no se quejan. Simplemente no regresan.">
<meta property="og:description" content="Encuestas por QR, alertas al momento y análisis con IA para tu negocio.">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%239D8CFF'/%3E%3Cpath d='M8 11c3 0 3 10 6 10s3-10 6-10 3 10 6 10' fill='none' stroke='%23141A3D' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/landing.css')) ?>?v=2">
</head>
<body>

<header class="cabecera">
  <div class="envoltura">
    <a class="logo" href="#inicio" aria-label="Whispers, inicio">
      <svg class="logo-marca" viewBox="0 0 32 32" aria-hidden="true">
        <rect width="32" height="32" rx="9" fill="#9D8CFF"/>
        <path d="M8 11c3 0 3 10 6 10s3-10 6-10 3 10 6 10" fill="none" stroke="#141A3D" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Whispers
    </a>
    <nav aria-label="Secciones">
      <a href="#como-funciona">Cómo funciona</a>
      <a href="#que-recibis">Qué recibís</a>
      <a href="#planes">Planes</a>
      <a href="#preguntas">Preguntas</a>
      <a class="entrar" href="<?= e(url('login.php')) ?>">Entrar</a>
      <a class="btn btn-verde js-wa" href="#" data-msg="Hola, quiero ver una demo de Whispers para mi negocio.">Pedir demo</a>
    </nav>
  </div>
</header>

<main id="inicio">

  <section class="hero">
    <div class="envoltura">
      <div>
        <h1>Tus clientes no se quejan. <span>Simplemente no regresan.</span></h1>
        <p class="hero-lead">Whispers pone un QR en cada mesa para que tus clientes te digan lo que no te dicen en persona. Vos te enterás en el momento, no cuando ya perdiste al cliente.</p>
        <div class="hero-ctas">
          <a class="btn btn-verde js-wa" href="#" data-msg="Hola, quiero ver una demo de Whispers para mi negocio.">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.3-.4.8-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.3-.9.9-.9 2.2s.9 2.5 1 2.7c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3z"/></svg>
            Pedir una demo por WhatsApp
          </a>
          <a class="btn btn-claro" href="#como-funciona">Ver cómo funciona</a>
        </div>
      </div>

      <div class="demo" aria-label="Demostración interactiva">
        <p class="demo-guia">Probalo: calificá como si fueras tu cliente.</p>
        <div class="telefono">
          <div class="pantalla">
            <div class="pantalla-top"><span>Tu restaurante</span><span>Mesa 2</span></div>
            <h3>Contanos cómo te fue</h3>
            <div class="preg">
              <p id="demo-preg">¿Qué tal el tiempo de espera?</p>
              <div class="estrellas" role="radiogroup" aria-labelledby="demo-preg">
                <button type="button" role="radio" aria-checked="false" data-v="1" aria-label="1 de 5"><svg viewBox="0 0 24 24"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4L2.8 9.5l6.4-.8z"/></svg></button>
                <button type="button" role="radio" aria-checked="false" data-v="2" aria-label="2 de 5"><svg viewBox="0 0 24 24"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4L2.8 9.5l6.4-.8z"/></svg></button>
                <button type="button" role="radio" aria-checked="false" data-v="3" aria-label="3 de 5"><svg viewBox="0 0 24 24"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4L2.8 9.5l6.4-.8z"/></svg></button>
                <button type="button" role="radio" aria-checked="false" data-v="4" aria-label="4 de 5"><svg viewBox="0 0 24 24"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4L2.8 9.5l6.4-.8z"/></svg></button>
                <button type="button" role="radio" aria-checked="false" data-v="5" aria-label="5 de 5"><svg viewBox="0 0 24 24"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4L2.8 9.5l6.4-.8z"/></svg></button>
              </div>
              <p class="estrellas-pista" id="demo-pista">Tocá una estrella</p>
            </div>
            <div class="preg preg-fija" aria-hidden="true"><div class="barrita"></div><div class="barrita"></div></div>
          </div>
        </div>

        <div class="tablero" aria-live="polite">
          <div class="tablero-tit"><span>Tu panel</span><span>Hoy</span></div>
          <div class="tablero-nota" id="demo-nota">4.3<small> /5</small></div>
          <div class="mini-barras" aria-hidden="true">
            <i style="height:55%"></i><i style="height:70%"></i><i style="height:45%"></i><i style="height:80%"></i><i style="height:65%"></i><i style="height:40%" id="demo-barra"></i>
          </div>
          <div class="aviso" id="demo-aviso"><strong>Esperando respuestas</strong>Cada encuesta llega aquí en cuanto el cliente la envía.</div>
        </div>
      </div>
    </div>
  </section>

  <section class="bloque problema">
    <div class="envoltura">
      <div>
        <h2 class="titulo-sec">Lo que pasa en tu negocio y no te enterás</h2>
        <p class="intro-sec">El cliente molesto casi nunca le reclama al mesero. Paga, sonríe y se va. Y la próxima vez escoge otro lugar.</p>
      </div>
      <ul class="problema-lista">
        <li><strong>“Todo bien, gracias”</strong><span>Es lo que te dicen en la mesa, aunque la comida llegó fría.</span></li>
        <li><strong>Reseñas de 1 estrella en Google</strong><span>Te enterás días después, cuando ya las vio todo el mundo.</span></li>
        <li><strong>No sabés qué turno o qué mesa falla</strong><span>Sin datos, todo se resuelve con suposiciones.</span></li>
      </ul>
    </div>
  </section>

  <section class="bloque" id="como-funciona">
    <div class="envoltura">
      <h2 class="titulo-sec">Así funciona</h2>
      <p class="intro-sec">Nosotros lo instalamos todo. Tu equipo solo tiene que dejar el QR a la vista.</p>
      <ol class="pasos">
        <li><h3>El cliente escanea el QR</h3><p>Está en la mesa o en la cuenta. Abre en el celular, sin descargar ninguna app, y se responde en menos de un minuto.</p></li>
        <li><h3>Califica lo que importa</h3><p>Comida, atención, tiempo de espera, limpieza y precio. Si quiere, deja un comentario o su WhatsApp.</p></li>
        <li><h3>Vos actuás a tiempo</h3><p>Si algo sale mal te llega una alerta en ese momento. Si salió bien, lo invitamos a dejarte una reseña en Google.</p></li>
      </ol>
    </div>
  </section>

  <section class="bloque recibis" id="que-recibis">
    <div class="envoltura">
      <h2 class="titulo-sec">Qué recibís con Whispers</h2>
      <div class="recibis-grid">
        <article class="destacado">
          <h3>Alertas cuando un cliente se va molesto</h3>
          <p>Cuando alguien califica bajo, el encargado lo sabe al instante y puede acercarse a la mesa antes de que el cliente salga por la puerta.</p>
          <div class="notif-lista" aria-hidden="true">
            <div class="notif mal"><b>2.0</b><strong>Mesa 4 · Tiempo de espera</strong><span>“Tardaron mucho con las bebidas”</span></div>
            <div class="notif bien"><b>5.0</b><strong>Mesa 1 · Invitado a reseñar</strong><span>Le mostramos tu link de Google</span></div>
          </div>
        </article>
        <article class="funcion">
          <h3>Panel en tiempo real</h3>
          <p>Ves tu calificación por día, por mesa y por sucursal. Sabés si el problema es la cocina, el servicio o la limpieza.</p>
        </article>
        <article class="funcion">
          <h3>Análisis de comentarios con IA</h3>
          <p>Leemos todos los comentarios por vos y te decimos qué se repite: “la música está muy alta”, “el café llega tibio”.</p>
        </article>
        <div class="funciones-mini">
          <article class="funcion"><h3>Más reseñas en Google</h3><p>Solo a los clientes contentos les pedimos reseña. Tu puntuación sube sin que tengás que rogar.</p></article>
          <article class="funcion"><h3>Reporte mensual</h3><p>Un resumen claro de cómo te fue, qué mejoró y en qué enfocarte el próximo mes.</p></article>
          <article class="funcion"><h3>Cupones que los hacen volver</h3><p>Premio por responder y cupón de disculpa por WhatsApp para el cliente molesto. Vos decidís qué regalar.</p></article>
          <article class="funcion"><h3>¿Quién te atendió?</h3><p>El cliente toca la foto de su mesero y vos ves el ranking de tu equipo. Ideal para dar bonos.</p></article>
          <article class="funcion"><h3>Resumen cada lunes</h3><p>Te llega tu nota de la semana, lo mejor, lo que hay que mejorar y la estrella del equipo.</p></article>
          <article class="funcion"><h3>Con tu marca</h3><p>La encuesta lleva tu logo y tus colores, y vos mismo cambiás las preguntas cuando querás.</p></article>
        </div>
      </div>
    </div>
  </section>

  <section class="bloque">
    <div class="envoltura">
      <h2 class="titulo-sec">Para negocios que viven de que el cliente regrese</h2>
      <div class="rubros">
        <span>Restaurantes</span><span>Cafés</span><span>Cervecerías</span><span>Barberías</span><span>Gimnasios</span><span>Clínicas</span>
      </div>
    </div>
  </section>

  <section class="bloque" id="planes" style="padding-top:0">
    <div class="envoltura">
      <h2 class="titulo-sec">Planes</h2>
      <p class="intro-sec">Precios mensuales en quetzales. Elegís el que va con el tamaño de tu negocio y podés cambiar cuando querás.</p>
      <div class="planes">
        <article class="plan">
          <h3>Básico</h3>
          <p class="plan-desc">Para empezar a medir en un local.</p>
          <p class="precio"><?= quetzales(PLANES['basico']['precio']) ?><small> /mes</small></p>
          <ul>
            <li>Encuestas por QR con preguntas listas</li>
            <li>Resumen en tiempo real y reporte mensual</li>
            <li>Alertas de calificaciones bajas por WhatsApp, correo y panel</li>
            <li>1 análisis con IA al mes</li>
          </ul>
          <a class="btn js-wa" href="#" data-msg="Hola, me interesa el plan Básico de Whispers.">Quiero el Básico</a>
        </article>
        <article class="plan recomendado">
          <span class="plan-tag">Recomendado para restaurantes</span>
          <h3>Profesional</h3>
          <p class="plan-desc">Para entender qué piensan tus clientes y crecer en Google.</p>
          <p class="precio"><?= quetzales(PLANES['profesional']['precio']) ?><small> /mes</small></p>
          <ul>
            <li>Todo lo del Básico</li>
            <li>Preguntas a tu medida, las cambiás cuando querás</li>
            <li>Análisis con IA sin límite</li>
            <li>Invitación a reseñar en Google a clientes contentos</li>
          </ul>
          <a class="btn btn-verde js-wa" href="#" data-msg="Hola, me interesa el plan Profesional de Whispers.">Quiero el Profesional</a>
        </article>
        <article class="plan">
          <h3>Premium</h3>
          <p class="plan-desc">Para sacarle todo el jugo: cupones, tu marca y tu equipo.</p>
          <p class="precio"><?= quetzales(PLANES['premium']['precio']) ?><small> /mes</small></p>
          <ul>
            <li>Todo lo del Profesional</li>
            <li>Cupones: premio por responder y disculpa por WhatsApp</li>
            <li>Tu logo y tus colores en la encuesta y los QR</li>
            <li>Ranking de tu equipo</li>
            <li>Encuesta en inglés y modo tablet</li>
            <li>Resumen cada lunes y comparación con tu rubro</li>
            <li>Varias sucursales en un solo panel</li>
          </ul>
          <a class="btn js-wa" href="#" data-msg="Hola, me interesa el plan Premium de Whispers.">Quiero el Premium</a>
        </article>
      </div>
      <div class="implementacion">
        <div>
          <strong>Implementación</strong>
          <p>Un solo pago al inicio. Configuramos tu cuenta, armamos las preguntas, te entregamos los QR de cada mesa listos para imprimir y capacitamos a tu equipo.</p>
        </div>
        <span class="monto"><?= quetzales(PRECIO_IMPLEMENTACION) ?> <small>pago único</small></span>
      </div>
    </div>
  </section>

  <section class="bloque faq" id="preguntas" style="padding-top:0">
    <div class="envoltura">
      <h2 class="titulo-sec">Preguntas frecuentes</h2>
      <div>
        <details><summary>¿El cliente tiene que descargar algo?</summary><p>No. Escanea el QR con la cámara del celular y la encuesta abre en el navegador. Toma menos de un minuto.</p></details>
        <details><summary>¿Y si nadie llena la encuesta?</summary><p>Te ayudamos con dónde poner el QR y cómo invitar al cliente a responder. Donde mejor funciona es junto a la cuenta, cuando el cliente ya está esperando.</p></details>
        <details><summary>¿Puedo cambiar las preguntas?</summary><p>En el plan Básico usás las preguntas que armamos con vos: comida, atención, tiempo de espera, limpieza y precio. Desde el Profesional las cambiás, agregás u ordenás vos mismo cuando querás.</p></details>
        <details><summary>¿Sirve si llegan turistas?</summary><p>Sí, con el plan Premium. Si el celular del cliente está en inglés, la encuesta sale en inglés sola, y también tiene un botón para cambiar de idioma.</p></details>
        <details><summary>¿La encuesta puede llevar mi logo y mis colores?</summary><p>Sí, con el plan Premium. Subís tu logo, elegís tus colores y la encuesta, los QR y hasta tu panel se ven con tu marca.</p></details>
        <details><summary>¿Quién ve las respuestas?</summary><p>Solo vos y las personas de tu equipo a las que les des acceso. Los datos de tus clientes no se comparten con nadie.</p></details>
        <details><summary>¿Sirve si tengo más de una sucursal?</summary><p>Sí. Cada sucursal y cada mesa tienen su propio QR, y en el panel podés compararlas.</p></details>
      </div>
    </div>
  </section>

  <section class="cierre">
    <div class="envoltura">
      <div class="cierre-caja">
        <div>
          <h2>¿Querés saber qué opinan tus clientes de verdad?</h2>
          <p>Escribinos y te mostramos una demo con tu propio negocio. Sin compromiso.</p>
        </div>
        <div class="cierre-ctas">
          <a class="btn btn-verde js-wa" href="#" data-msg="Hola, quiero ver una demo de Whispers para mi negocio.">Escribir por WhatsApp</a>
          <a class="btn btn-claro" href="https://www.instagram.com/whispers_gt" target="_blank" rel="noopener">Seguinos en Instagram</a>
        </div>
      </div>
    </div>
  </section>

</main>

<footer>
  <div class="envoltura">
    <span>Whispers · Guatemala · <a href="<?= e(url('login.php')) ?>">Acceso clientes</a></span>
    <a href="https://www.instagram.com/whispers_gt" target="_blank" rel="noopener">@whispers_gt</a>
  </div>
</footer>

<a class="wa-flota js-wa" href="#" data-msg="Hola, quiero información de Whispers." aria-label="Escribir por WhatsApp">
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.3-.4.8-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.3-.9.9-.9 2.2s.9 2.5 1 2.7c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3z"/></svg>
</a>

<script>
/* El número sale de Whispers → Ajustes (WhatsApp de ventas) */
var WHATSAPP = <?= json_encode(preg_replace('/[^0-9]/', '', $wa)) ?>;

(function () {
  document.querySelectorAll('.js-wa').forEach(function (a) {
    var msg = a.getAttribute('data-msg') || 'Hola, quiero información de Whispers.';
    a.href = 'https://wa.me/' + WHATSAPP + '?text=' + encodeURIComponent(msg);
    a.target = '_blank';
    a.rel = 'noopener';
  });

  /* Demo interactiva del hero */
  var textos = { 1: 'Muy malo', 2: 'Malo', 3: 'Regular', 4: 'Bueno', 5: 'Excelente' };
  var botones = document.querySelectorAll('.estrellas button');
  var pista = document.getElementById('demo-pista');
  var nota = document.getElementById('demo-nota');
  var barra = document.getElementById('demo-barra');
  var aviso = document.getElementById('demo-aviso');
  var timer;

  function pintar(v) {
    botones.forEach(function (b) {
      var n = +b.getAttribute('data-v');
      b.classList.toggle('on', n <= v);
      b.setAttribute('aria-checked', n === v ? 'true' : 'false');
    });
  }

  botones.forEach(function (b) {
    b.addEventListener('click', function () {
      var v = +b.getAttribute('data-v');
      pintar(v);
      pista.textContent = textos[v] + ' · enviado';
      clearTimeout(timer);
      timer = setTimeout(function () {
        var promedio = (4.3 * 11 + v) / 12;
        nota.innerHTML = promedio.toFixed(1) + '<small> /5</small>';
        barra.style.height = (40 + v * 9) + '%';
        aviso.classList.remove('mal', 'bien', 'entra');
        void aviso.offsetWidth;
        if (v <= 2) {
          aviso.classList.add('mal');
          aviso.innerHTML = '<strong>Alerta · Mesa 2</strong>Calificó el tiempo de espera con ' + v + '/5. El encargado ya puede acercarse a la mesa.';
          nota.style.color = '#FF8A9B';
        } else if (v >= 4) {
          aviso.classList.add('bien');
          aviso.innerHTML = '<strong>Mesa 2 · ' + v + '/5</strong>Cliente contento. Le mostramos tu link para dejar reseña en Google.';
          nota.style.color = '#5BE0AA';
        } else {
          aviso.innerHTML = '<strong>Mesa 2 · 3/5</strong>Respuesta guardada. Aparece en tu reporte para ver qué mejorar.';
          nota.style.color = '';
        }
        aviso.classList.add('entra');
      }, 450);
    });
  });
})();
</script>
</body>
</html>
