/* =========================================================
   Whispers — interacciones (público + panel)
   ========================================================= */
(function () {
  'use strict';

  /* ---------- Encuesta: texto bajo las estrellas ---------- */
  var textos = { 1: 'Muy malo', 2: 'Malo', 3: 'Regular', 4: 'Bueno', 5: 'Excelente', falta: 'Falta calificar esta', enviando: 'Enviando…' };
  try { var dt = document.body.getAttribute('data-txt'); if (dt) textos = JSON.parse(dt); } catch (e) {}
  document.querySelectorAll('.estrellas').forEach(function (grupo) {
    var salida = grupo.parentElement.querySelector('.estrellas-txt');
    function pintar() {
      var sel = grupo.querySelector('input:checked');
      if (salida) salida.textContent = sel ? textos[sel.value] : '';
    }
    grupo.addEventListener('change', pintar);
    pintar();
  });

  /* ---------- Encuesta: progreso, comentario y sugerencias ---------- */
  var formEnc = document.querySelector('.encuesta-form');
  if (formEnc) {
    var barra = document.getElementById('progreso');
    var grupos = Array.prototype.slice.call(formEnc.querySelectorAll('fieldset.pregunta:not(.personal-pick)'));
    var actualizar = function () {
      var listas = 0;
      grupos.forEach(function (g) {
        var ok = !!g.querySelector('input[type=radio]:checked');
        g.classList.toggle('lista', ok);
        if (ok) listas++;
      });
      if (barra && grupos.length) barra.style.width = Math.round(listas / grupos.length * 100) + '%';
    };
    formEnc.addEventListener('change', actualizar);
    actualizar();

    var area = document.getElementById('comentario');
    var cont = document.getElementById('contador');
    var contar = function () { if (cont && area) cont.textContent = area.value.length + '/' + area.maxLength; };
    if (area) { area.addEventListener('input', contar); contar(); }

    formEnc.querySelectorAll('.sug').forEach(function (b) {
      b.addEventListener('click', function () {
        var t = b.textContent.trim();
        if (b.classList.contains('usada') || !area) return;
        var v = area.value.trim();
        area.value = v ? v.replace(/[.\s]*$/, '') + '. ' + t : t;
        b.classList.add('usada');
        contar();
        area.focus();
      });
    });
  }

  /* Validación amable antes de enviar la encuesta */
  var form = document.querySelector('.encuesta-form');
  if (form) {
    form.addEventListener('submit', function (ev) {
      var faltante = null;
      form.querySelectorAll('.estrellas').forEach(function (g) {
        if (!faltante && !g.querySelector('input:checked')) faltante = g.closest('.pregunta');
      });
      if (faltante) {
        ev.preventDefault();
        faltante.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var txt = faltante.querySelector('.estrellas-txt');
        if (txt) { txt.textContent = textos.falta; txt.style.color = 'var(--rojo)'; }
        return;
      }
      var btn = form.querySelector('button[type=submit]');
      if (btn) { btn.disabled = true; btn.textContent = textos.enviando || 'Enviando…'; }
    });
  }

  /* ---------- Panel: menú en celular ---------- */
  var menuBtn = document.querySelector('.menu-btn');
  var lateral = document.getElementById('lateral');
  if (menuBtn && lateral) {
    menuBtn.addEventListener('click', function () {
      var abierto = lateral.classList.toggle('abierto');
      menuBtn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
    document.addEventListener('click', function (ev) {
      if (lateral.classList.contains('abierto') && !lateral.contains(ev.target) && !menuBtn.contains(ev.target)) {
        lateral.classList.remove('abierto');
        menuBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---------- Confirmaciones ---------- */
  document.querySelectorAll('form[data-confirmar]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.getAttribute('data-confirmar'))) ev.preventDefault();
    });
  });

  /* ---------- Filas clicables ---------- */
  document.querySelectorAll('.fila-link').forEach(function (tr) {
    tr.addEventListener('click', function (ev) {
      if (ev.target.closest('a, button, input, select')) return;
      window.location = tr.getAttribute('data-href');
    });
  });

  /* ---------- Copiar link ---------- */
  document.querySelectorAll('[data-copiar]').forEach(function (b) {
    b.addEventListener('click', function () {
      var txt = b.getAttribute('data-copiar');
      var listo = function () { var o = b.textContent; b.textContent = 'Copiado'; setTimeout(function () { b.textContent = o; }, 1500); };
      if (navigator.clipboard) {
        navigator.clipboard.writeText(txt).then(listo);
      } else {
        var i = b.previousElementSibling; i.select(); document.execCommand('copy'); listo();
      }
    });
  });

  function escapar(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

  /* ---------- Generar contraseña ---------- */
  document.querySelectorAll('[data-generar]').forEach(function (b) {
    b.addEventListener('click', function () {
      var abc = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789', out = '';
      var arr = new Uint32Array(10); (window.crypto || window.msCrypto).getRandomValues(arr);
      for (var i = 0; i < arr.length; i++) out += abc[arr[i] % abc.length];
      document.getElementById(b.getAttribute('data-generar')).value = out;
    });
  });

  /* ---------- Formularios que tardan (IA) ---------- */
  document.querySelectorAll('form[data-cargando]').forEach(function (f) {
    f.addEventListener('submit', function () {
      f.classList.add('cargando');
      var btn = f.querySelector('button[type=submit]');
      if (btn) btn.textContent = f.getAttribute('data-cargando');
    });
  });

  /* ---------- Colores de marca: vista previa en vivo ---------- */
  function lum(hex) {
    var c = [1, 3, 5].map(function (i) {
      var v = parseInt(hex.substr(i, 2), 16) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }
  function contr(a, b) { var x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
  function txtSobre(f) { return contr(f, '#FFFFFF') >= contr(f, '#141A3D') ? '#FFFFFF' : '#141A3D'; }
  var valido = function (c) { return /^#[0-9a-fA-F]{6}$/.test(c); };

  var vista = document.getElementById('vista');
  if (vista) {
    var cp = document.getElementById('cp'), cpT = document.getElementById('cp_txt');
    var ca = document.getElementById('ca'), caT = document.getElementById('ca_txt');
    var pintarVista = function () {
      var p = cpT.value.toUpperCase(), a = caT.value.toUpperCase();
      if (!valido(p) || !valido(a)) return;
      var tp = txtSobre(p);
      vista.style.setProperty('--marca', p);
      vista.style.setProperty('--marca-txt', tp);
      vista.style.setProperty('--boton', p);
      vista.style.setProperty('--boton-txt', tp);
      vista.style.setProperty('--acento', a);
      vista.style.setProperty('--acento-txt', txtSobre(a));
      vista.style.setProperty('--estrella', a);
    };
    var enlazar = function (picker, texto) {
      picker.addEventListener('input', function () { texto.value = picker.value.toUpperCase(); pintarVista(); });
      texto.addEventListener('input', function () {
        var v = texto.value.trim(); if (v && v[0] !== '#') v = '#' + v;
        if (valido(v)) { picker.value = v; pintarVista(); }
      });
    };
    enlazar(cp, cpT); enlazar(ca, caT);
    document.querySelectorAll('.paleta').forEach(function (b) {
      b.addEventListener('click', function () {
        cp.value = cpT.value = b.getAttribute('data-p');
        ca.value = caT.value = b.getAttribute('data-a');
        pintarVista();
      });
    });
    pintarVista();
  }

  /* ---------- Varios números / correos de alerta ---------- */
  document.querySelectorAll('.multi').forEach(function (bloque) {
    var lista = bloque.querySelector('.multi-lista');
    var mas = bloque.querySelector('.multi-mas');
    var max = parseInt(bloque.getAttribute('data-max'), 10) || 5;
    var revisar = function () {
      var filas = lista.querySelectorAll('.multi-fila');
      mas.hidden = filas.length >= max;
      filas.forEach(function (f) { f.querySelector('.multi-quitar').hidden = filas.length === 1; });
    };
    mas.addEventListener('click', function () {
      var nueva = lista.querySelector('.multi-fila').cloneNode(true);
      nueva.querySelector('input').value = '';
      lista.appendChild(nueva);
      nueva.querySelector('input').focus();
      revisar();
    });
    lista.addEventListener('click', function (ev) {
      var b = ev.target.closest('.multi-quitar');
      if (!b) return;
      var filas = lista.querySelectorAll('.multi-fila');
      if (filas.length > 1) b.closest('.multi-fila').remove(); else b.closest('.multi-fila').querySelector('input').value = '';
      revisar();
    });
    revisar();
  });

  /* ---------- Toast ---------- */
  function toast(html) {
    var t = document.getElementById('toast');
    if (!t) return;
    t.innerHTML = html;
    t.hidden = false;
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { t.hidden = true; }, 9000);
  }

  /* ---------- Alertas en tiempo real (cada 30 s) ---------- */
  var urlAlertas = document.body.getAttribute('data-alertas-url');
  if (urlAlertas && window.fetch) {
    var ultimaVista = null;
    var revisar = function () {
      fetch(urlAlertas, { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d) return;
          var badge = document.getElementById('badge-alertas');
          if (badge) { badge.textContent = d.pendientes; badge.hidden = d.pendientes === 0; }
          var id = d.ultima ? d.ultima.id : 0;
          if (ultimaVista !== null && id > ultimaVista && d.ultima) {
            toast('Calificación baja en <strong>' + escapar(d.ultima.mesa) + '</strong> (' +
              d.ultima.promedio.toFixed(1) + '). <a href="' + d.ultima.url + '">Ver encuesta</a>');
            document.title = '(!) ' + document.title.replace(/^\(!\) /, '');
          }
          ultimaVista = id;
        })
        .catch(function () {});
    };
    revisar();
    setInterval(revisar, 30000);
  }

  /* ---------- Gráfica del resumen ---------- */
  window.whGrafica = function () {
    var el = document.getElementById('grafica-dias');
    var d = window.WH_GRAFICA;
    if (!el || !d || !window.Chart) return;
    var fuente = getComputedStyle(document.documentElement).fontFamily;
    Chart.defaults.font.family = fuente;
    Chart.defaults.color = '#666C8C';
    new Chart(el, {
      data: {
        labels: d.labels,
        datasets: [
          { type: 'bar', label: 'Encuestas', data: d.conteos, backgroundColor: '#D9DDF7', hoverBackgroundColor: '#9D8CFF', borderRadius: 6, yAxisID: 'y', order: 2 },
          { type: 'line', label: 'Promedio', data: d.promedios, borderColor: '#4361EE', backgroundColor: '#4361EE', tension: .25, spanGaps: true, pointRadius: 3, borderWidth: 2.5, yAxisID: 'y2', order: 1 }
        ]
      },
      options: {
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#EEF0F6' }, title: { display: true, text: 'Encuestas' } },
          y2: { position: 'right', min: 1, max: 5, grid: { display: false }, title: { display: true, text: 'Promedio' } }
        }
      }
    });
  };

  /* ---------- QR de mesas ---------- */
  window.whQRs = function () {
    if (!window.QRCode) return;
    document.querySelectorAll('[data-qr]').forEach(function (el) {
      new QRCode(el, { text: el.getAttribute('data-qr'), width: 340, height: 340, colorDark: '#141A3D', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
    });

    function descargar(id) {
      var caja = document.getElementById('print-' + id);
      var qrEl = caja.querySelector('[data-qr]');
      var qrCanvas = qrEl.querySelector('canvas');
      var cs = getComputedStyle(caja);
      var col = function (v, d) { return (cs.getPropertyValue(v) || '').trim() || d; };
      var fondo = col('--marca', '#141A3D'), texto = col('--marca-txt', '#FFFFFF');
      var suave = col('--marca-suave', '#C9CDEA'), acento = col('--acento-sobre-marca', '#9D8CFF');
      var logo = caja.querySelector('.qr-logo');
      var hayLogo = logo && logo.complete && logo.naturalWidth > 0;

      var W = 1080, H = 1350, c = document.createElement('canvas');
      c.width = W; c.height = H;
      var x = c.getContext('2d');
      var fuente = '"Outfit", system-ui, sans-serif';
      var redondo = function (rx, ry, rw, rh, r) {
        x.beginPath();
        if (x.roundRect) { x.roundRect(rx, ry, rw, rh, r); } else { x.rect(rx, ry, rw, rh); }
      };
      x.fillStyle = fondo; x.fillRect(0, 0, W, H);
      x.textAlign = 'center';

      var y0 = 150, lado = 640;
      if (hayLogo) {
        var L = 120, lx = (W - L) / 2, ly = 60;
        x.save(); redondo(lx, ly, L, L, 28); x.fillStyle = '#ffffff'; x.fill(); x.clip();
        var r = Math.max(L / logo.naturalWidth, L / logo.naturalHeight);
        var iw = logo.naturalWidth * r, ih = logo.naturalHeight * r;
        x.drawImage(logo, lx + (L - iw) / 2, ly + (L - ih) / 2, iw, ih);
        x.restore();
        y0 = 240; lado = 600;
      }
      x.fillStyle = suave; x.font = '500 46px ' + fuente;
      x.fillText(caja.querySelector('.qr-negocio').textContent, W / 2, y0);

      var qx = (W - lado) / 2, qy = y0 + 70;
      x.fillStyle = '#ffffff'; redondo(qx - 40, qy - 40, lado + 80, lado + 80, 36); x.fill();
      x.imageSmoothingEnabled = false;
      x.drawImage(qrCanvas, qx, qy, lado, lado);

      x.fillStyle = texto; x.font = '700 104px ' + fuente;
      x.fillText(caja.querySelector('.qr-mesa').textContent, W / 2, qy + lado + 170);
      x.fillStyle = acento; x.font = '500 48px ' + fuente;
      x.fillText(caja.querySelector('.qr-cta').textContent, W / 2, qy + lado + 255);
      x.fillStyle = suave; x.font = '400 30px ' + fuente;
      x.fillText('Con Whispers', W / 2, 1300);

      var a = document.createElement('a');
      a.download = qrEl.getAttribute('data-nombre') + '.png';
      a.href = c.toDataURL('image/png');
      document.body.appendChild(a); a.click(); a.remove();
    }
    document.querySelectorAll('[data-descargar-qr]').forEach(function (b) {
      b.addEventListener('click', function () { descargar(b.getAttribute('data-descargar-qr')); });
    });
    var todos = document.querySelector('[data-descargar-todos]');
    if (todos) {
      todos.addEventListener('click', function () {
        var ids = Array.prototype.map.call(document.querySelectorAll('.qr-card:not(.inactivo) [data-descargar-qr]'), function (b) { return b.getAttribute('data-descargar-qr'); });
        ids.forEach(function (id, i) { setTimeout(function () { descargar(id); }, i * 450); });
      });
    }
  };

  /* Casillas que muestran u ocultan un campo */
  document.querySelectorAll('[data-muestra]').forEach(function (c) {
    var obj = document.getElementById(c.getAttribute('data-muestra'));
    var f = function () { if (obj) obj.hidden = !c.checked; };
    c.addEventListener('change', f); f();
  });

})();
