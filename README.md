# Whispers 2.0 — Plataforma de encuestas por QR

Plataforma completa y autogestionable: tu página de ventas, el panel de Whispers para manejar clientes
y cobros, y el panel de cada negocio con encuestas, alertas, análisis con IA y reporte mensual.

## Instalación (10 minutos)
1. Subí la carpeta completa a tu hosting (cPanel, Hostinger, XAMPP o Laragon). Necesitás PHP 8.0+ y MySQL/MariaDB.
2. Abrí tu dominio en el navegador. Te lleva solo a **install.php**.
3. Llená los datos de MySQL, tu usuario de Whispers y tu WhatsApp de ventas, y tocá **Instalar**.
   Si dejás marcado “Restaurante Demo”, te crea un cliente con 70 encuestas para hacer demos.
4. Listo. No hay que editar ningún archivo: todo se maneja desde el panel.

> Si el instalador avisa que no pudo crear `config.local.php`, te muestra el contenido para crearlo a mano.
> Para reinstalar, borrá `config.local.php`.

## Quién ve qué
| Usuario | Qué puede hacer |
|---|---|
| **Whispers** (vos) | Crear clientes, cambiar planes, registrar pagos, pausar cuentas, abrir el panel de cualquier cliente, configurar IA y WhatsApp |
| **Administrador** del negocio | Todo su panel: sucursales, mesas, QR, preguntas, usuarios, logo, colores, alertas |
| **Gerente** | Ver resultados y dar seguimiento. Si se le asigna una sucursal, solo ve esa |

## Qué incluye cada plan (se aplica solo)
| | Básico Q450 | Profesional Q850 | Premium Q1,500 |
|---|---|---|---|
| Encuesta por QR, resumen, reporte mensual | ✓ | ✓ | ✓ |
| Alertas de nota baja por WhatsApp, correo y panel | ✓ | ✓ | ✓ |
| Análisis con IA | 1 al mes | Sin límite | Sin límite |
| Preguntas | Las define Whispers | El dueño las edita | El dueño las edita |
| Invitación a reseñar en Google | | ✓ | ✓ |
| Cupones (premio y disculpa) | | | ✓ |
| Logo y colores | | | ✓ |
| Personal y ranking | | | ✓ |
| Encuesta en inglés, modo tablet, pregunta especial | | | ✓ |
| Resumen semanal, día y turno, comparación con el rubro | | | ✓ |
| Varias sucursales | | | ✓ |

En el plan Básico, vos (usuario de Whispers) sí podés editar las preguntas del cliente desde **Abrir panel → Preguntas**.
Precios y matriz: `PLANES` y `FUNCIONES_PLAN` al inicio de `config.php`.

### Dar funciones de prueba o extra a un cliente
En **Clientes → Editar → Funciones del cliente** podés darle cualquier función que su plan no incluye:
**Dar prueba** (7, 15 o 30 días), **Activar siempre** o **Quitar**. También hay un botón **🎁 Prueba Premium completa**.
Mientras dura la prueba, el cliente ve un aviso con los días que le quedan y un botón para pedirte quedarse con la función.
Al vencer, la función se apaga sola y sus datos no se borran. En la lista de Clientes ves quién tiene pruebas activas.

## Activar el análisis con IA (OpenAI o Claude)
1. Creá tu llave en **platform.openai.com → API keys** (o en console.anthropic.com si preferís Claude).
2. En Whispers → **Ajustes de Whispers → Análisis con IA**, elegí qué IA usar, pegá la llave y guardá.
3. Tocá **Probar conexión con la IA**.
Modelo por defecto de OpenAI: `gpt-4o-mini` (económico). Si OpenAI lo retira, poné otro modelo de chat de tu cuenta.
Tu cuenta de OpenAI necesita saldo cargado (Billing) para que la llave funcione.

## Activar alertas por WhatsApp
1. En Meta for Developers, en tu app de WhatsApp, creá una plantilla (categoría *Utilidad*) llamada
   `alerta_calificacion`, idioma español, con este texto o parecido:
   ```
   Alerta de Whispers en {{1}}: {{2}} calificó con {{3}}. Comentario: "{{4}}". Revisalo en tu panel.
   ```
2. Cuando Meta la apruebe, copiá el **token permanente** y el **Phone number ID** en Ajustes de Whispers.
3. Cada negocio pone sus números (hasta 5) y correos (hasta 5) en **Ajustes del negocio** y toca **Enviar alerta de prueba**. La alerta le llega a todos.

Las alertas por correo usan `mail()` del hosting. Poné un remitente de tu dominio en Ajustes de Whispers.

## Estructura
```
index.php          Página de ventas de Whispers
encuesta.php       Encuesta que abre el QR
install.php        Instalador
login.php          Acceso para todos
super/             Panel de Whispers (clientes, cobros, ajustes globales)
admin/             Panel de cada negocio
includes/          Núcleo, métricas, IA, notificaciones y esquema SQL
assets/            Estilos, JS y librerías (Chart.js y QR van incluidas, sin depender de CDN)
uploads/logos/     Logos de los negocios (necesita permiso de escritura)
```

## Seguridad incluida
Contraseñas con `password_hash`, consultas preparadas, CSRF en todos los formularios, aislamiento total
entre clientes, freno de intentos de login, campo trampa anti-bots, QR con tokens aleatorios,
`.htaccess` que bloquea `includes/`, `config.local.php` y la ejecución de código en `uploads/`.

## Logo y colores de cada negocio
En **Ajustes del negocio → Logo y colores de tu marca** el administrador sube su logo y elige un color principal
(encabezado y botones) y uno de acento (estrellas y detalles), o una combinación lista. Hay vista previa en vivo.
Los colores se aplican a la encuesta y a los QR descargables; con la casilla “Usar estos colores también en mi panel”
se aplican también a su panel. El texto se pone blanco u oscuro solo, según el color, para que siempre se lea.

## Actualizar desde la versión anterior
Reemplazá los archivos (sin borrar `config.local.php` ni `uploads/`). La base de datos se actualiza sola la primera vez que alguien entra.

## Funciones para atraer y retener clientes (v5)
| Función | Dónde | Qué hace |
|---|---|---|
| **Premio por responder** | Cupones | Al terminar la encuesta el cliente ve un código (ej. GRACIAS-7KQ2M). El dueño edita el premio, condiciones, vigencia y el inicio del código |
| **Cupón de disculpa** | Cupones + detalle de encuesta | Con la API de WhatsApp conectada: se envía desde el servidor con la plantilla `cupon_disculpa` (con un botón o automático, sin abrir WhatsApp Web). Sin API: botón que abre WhatsApp con mensaje editable. Código único por encuesta |
| **Canje de códigos** | Cupones | El personal valida el código en la caja: avisa si ya se usó o venció |
| **¿Quién te atendió?** | Personal | El cliente toca la foto del mesero; ranking del equipo con 🏆 |
| **Día y turno** | Resumen | Mapa de lunes a domingo × mañana/tarde/noche y el punto más débil |
| **Comparación con el rubro** | Resumen | Su nota vs. el promedio anónimo de negocios del mismo tipo (con 3+ negocios con datos) |
| **Pregunta especial** | Preguntas | Pregunta temporal con fecha de fin, sale de primera |
| **Encuesta en inglés** | Automático | Detecta el idioma del celular; botón EN/ES; traducción de preguntas con IA |
| **Modo tablet** | Mesas y QR → Modo tablet | Link para tablet en mostrador: se reinicia sola después de cada cliente |
| **Resumen semanal** | Ajustes del negocio | Cada lunes por WhatsApp y correo. Requiere la tarea programada `cron/resumen_semanal.php` (ver Ajustes de Whispers) |


### Plantilla de WhatsApp para el cupón de disculpa
Creala en Meta como **Marketing**, idioma español, nombre `cupon_disculpa`, con 5 variables:
```
Hola {{1}}, te escribimos de {{2}}. Leímos tu comentario y lamentamos que tu visita no haya sido como esperabas. Queremos compensarte con: {{3}}.

Tu código es {{4}}, válido hasta el {{5}}.

¡Esperamos verte pronto!
```
{{1}} nombre del cliente · {{2}} negocio · {{3}} premio · {{4}} código · {{5}} vencimiento.
