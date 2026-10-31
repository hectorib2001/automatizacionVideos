# AccesoPro · Motor de contenido en vídeo

Este repositorio es la prueba técnica de AI Engineer para AccesoPro (Grupo Mentor): un
sistema que genera, revisa y publica automáticamente vídeos cortos educativos para adultos
que se preparan para una prueba de acceso (Grado Superior), a partir de dos fuentes: el texto
de un tema del temario y una clase grabada.

**Este documento se va actualizando a medida que avanza el proyecto** (entrega: domingo 1 de
noviembre de 2026). La sección "Estado actual" refleja lo que funciona hoy, no un plan.

## Estado actual

✅ **Infraestructura lista.** Proyecto Laravel, base de datos, voz clonada (ElevenLabs),
conexión con Google Drive y con el modelo de IA (Claude), todo probado y funcionando.

✅ **Las 7 ideas del primer tema ("Números reales") ya tienen guion y revisión real.** El
sistema las propuso solo, escribió un guion de 30-60s para cada una y un segundo modelo de IA
(distinto del que escribe) las revisó frase por frase contra el temario oficial:

- **6 guiones aprobados a la primera** — matemáticas correctas, sin datos inventados, claros
  para un adulto que estudia para un examen.
- **1 guion rechazado y corregido automáticamente.** El revisor detectó que el gancho
  afirmaba «es el error que más puntos te cuesta» sin ninguna fuente que lo respalde, y que
  prometía «40 segundos» cuando el guion duraba 43. El propio sistema reescribió solo esas
  dos cosas (sin tocar el resto del guion) y, en la segunda revisión, **quedó aprobado**.
  El motivo del primer rechazo queda registrado como ejemplo (ver `docs/PLAN.md`).
- **Resultado: las 7 ideas del tema tienen guion aprobado.** Coste real total
  (ideas + 7 guiones + la corrección): **≈0,78 €**.

✅ **Las 7 ideas son 7 vídeos reales, publicados, de principio a fin.** Con un solo comando
(`accesopro:run T1_S01 --limit=7`), el sistema: narra cada guion con tu voz clonada, quema
subtítulos sincronizados palabra por palabra, monta el vídeo con el logo y los colores de
AccesoPro (vertical 9:16 y horizontal 16:9), genera título/descripción/etiquetas y una
miniatura, y lo sube a Google Drive. Los 14 vídeos (7 ideas × 2 formatos) están publicados
— enlaces en [`docs/PLAN.md`](docs/PLAN.md#vídeos-publicados-t1_s01-fuente-texto).
Coste total: **≈1,57 €** (voz + revisión con IA; montar y publicar no cuestan nada más).

✅ **Nivel 1 completo: también con la clase grabada.** El sistema descargó la clase
(58 min), la transcribió con marcas de tiempo por palabra, y un modelo de IA detectó los
momentos con valor propio — no cortes por tiempo. Primer intento: solo 1 de 7 candidatos pasó
la revisión, porque esta clase es muy interactiva (la profesora señala la pizarra, responde
al chat) y la mayoría de momentos no se entienden solos. En vez de aflojar el criterio, se
afinaron las instrucciones y se pidieron más candidatos: segundo intento, **3 de 10
aprobados y publicados**, cubriendo el mínimo pedido. Los otros 7 fueron rechazados con
motivos reales — entre ellos, un error de la propia clase: la profesora sugiere que puedes
reconocer un número irracional mirando los decimales en la pantalla de la calculadora, lo
cual es matemáticamente falso. Enlaces en
[`docs/PLAN.md`](docs/PLAN.md#clips-publicados-clase-t1s02).

**Coste total del Nivel 1 completo (texto + clase): ≈2,73 €.** Muy por debajo del límite
diario de 5 € y del tope de reembolso de 30 €.

✅ **Panel de control con login.** Una tabla (`/panel`, acceso con usuario/contraseña)
muestra cada pieza con su título, tipo, estado, **origen** (de qué tema o clase viene),
coste real, enlaces a los vídeos publicados y el motivo exacto de cada rechazo, con
filtros por estado y tipo. Se intentó primero con Filament, pero quedó bloqueado por una
incompatibilidad de este PHP con la extensión `intl` (ver `docs/DECISIONES.md` D25); se
reconstruyó con Laravel puro, sin perder nada de funcionalidad para lo que pide el
enunciado.

🔜 **Pendiente** (Nivel 2, ver `docs/PLAN.md`):
- Exportar la tabla de control a CSV.
- Publicar en YouTube como no listados (ahora mismo se publica en Drive, por diseño).
- Aprobación humana opcional, variantes A/B, despliegue en Laravel Cloud.

## Cómo lanzarlo

Esta parte está pensada para que cualquiera, técnico o no, pueda ponerlo en marcha.

1. **Requisitos**: Windows, PHP 8.4, Composer, FFmpeg. La guía paso a paso para instalarlos
   está en [`docs/SETUP_WINDOWS.md`](docs/SETUP_WINDOWS.md).
2. **Claves necesarias** (cada una se consigue gratis o con un plan de pago pequeño, ver
   `docs/SETUP_WINDOWS.md` sección 5): una clave de Anthropic (Claude), una de ElevenLabs
   (voz) y acceso a Google Drive. Se guardan en `accesopro/.env`.
3. **Poner en marcha el sistema completo para un tema**, desde una terminal dentro de la
   carpeta `accesopro/`:
   ```powershell
   php artisan migrate
   php artisan accesopro:check          # comprueba que todo está conectado
   php artisan accesopro:run T1_S01      # procesa 1 idea de ese tema (por defecto)
   ```
   `accesopro:run T1_S01 --limit=5` procesaría 5 ideas en vez de 1 (cada idea cuesta dinero
   real en llamadas a la IA, por eso el valor por defecto es bajo).
4. **Ver el resultado**: el propio comando muestra una tabla con el estado de cada pieza
   (generada, revisada, aprobada...), su coste y el motivo si fue rechazada.

## Qué se ha hecho y por qué (resumen para no técnicos)

- **Dos cerebros de IA distintos, no uno.** Un modelo escribe el guion y **otro modelo
  completamente distinto lo revisa**, como un segundo profesor corrigiendo el trabajo del
  primero. Si hay un error matemático, un segundo sistema (no la IA) vuelve a hacer la cuenta
  a mano para confirmarlo: la IA puede equivocarse diciendo que algo está bien, pero el
  cálculo exacto siempre tiene la última palabra.
- **Prioridad: que no se publique nada incorrecto.** El enunciado de la prueba es claro en
  esto: un vídeo con un error de temario hace más daño que no publicar nada. Por eso, ante la
  duda, el sistema rechaza y registra el motivo en vez de arriesgarse.
- **Voz propia clonada**, no un locutor genérico ni un avatar: se usó una grabación real de
  voz para que el sistema narre los vídeos con ella.
- **Nada del material confidencial de AccesoPro se usa con planes gratuitos de IA** (esos
  planes usan lo que les envías para entrenar sus modelos). Todo el material de AccesoPro se
  procesa con planes de pago.

## Qué quedó fuera (por ahora) y por qué

- **La imagen de marca definitiva**: el brand book y el logo oficiales llegaron durante el
  desarrollo; falta construir las plantillas visuales a partir de ellos (Fase 2).
- **Publicación en YouTube**: se empieza subiendo a Google Drive para poder revisar la
  calidad sin arriesgar el canal; YouTube se activa al final sin tener que tocar el resto del
  sistema (ver `docs/DECISIONES.md`, decisión D5).
- **Aprobación humana y variantes A/B** (Nivel 2 del enunciado): están planificadas pero se
  priorizó primero tener un Nivel 1 sólido, como pide el propio enunciado de la prueba.

## Confidencialidad

El material de origen (temario de AccesoPro, guion de podcast, clase grabada, brand book)
pertenece a Grupo Mentor y vive solo en la carpeta `materiales/`, que nunca se sube a este
repositorio (está en `.gitignore`). El código es de libre consulta; el contenido de origen no.

## Documentación técnica

Para quien quiera entender las decisiones de arquitectura, el modelo de datos o el estado
detallado tarea por tarea:

- [`docs/RETO.md`](docs/RETO.md) — qué pide la prueba técnica.
- [`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md) — stack, modelo de datos, pipelines.
- [`docs/PLAN.md`](docs/PLAN.md) — fases, tareas y estado actualizado.
- [`docs/DECISIONES.md`](docs/DECISIONES.md) — qué se decidió y por qué.
- [`docs/REVISOR.md`](docs/REVISOR.md) — cómo funciona el agente revisor en detalle.
