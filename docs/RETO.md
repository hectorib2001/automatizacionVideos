# La prueba técnica (resumen fiel del enunciado)

Fuente: `materiales/Prueba_tecnica_AI_Engineer_AccesoPro.pdf` (confidencial).

## Contexto

AccesoPro (Grupo Mentor) prepara a adultos para pruebas de acceso (Grado Medio, Grado Superior,
Universidad +25/+45, PCE UNEDasiss, Bachillerato libre): 6 tipos de prueba en 17 comunidades
autónomas, cada una con su temario. Quieren publicar en YouTube (Shorts 9:16 y horizontal 16:9)
contenido útil a partir de dos fuentes: el texto de sus temarios y las grabaciones de sus clases.

**El contenido tiene que ser correcto.** Un vídeo con un error de temario hace más daño que no
publicarlo: el control de calidad es tan importante como el volumen.

**Plazo:** domingo 1 de noviembre de 2026, 16:00 hora peninsular española (11:00 en Bolivia).
Entrega por correo con enlaces accesibles sin pedir permiso.

## Material recibido (en `materiales/`)

| Material | Uso |
|---|---|
| Temario AccesoPro Matemáticas GS Comunidad Valenciana (TXT) – tema **T1_S01** | Fuente de guiones |
| Guion de podcast de T1_S01 (JSON) | Base ya elaborada para los guiones |
| Temario oficial Matemáticas GS Generalitat Valenciana (PDF, DOGV 25/02/2026) | Referencia del revisor (ver `docs/TEMARIO_OFICIAL.md`) |
| Clase grabada (enlace de YouTube) | Fuente de clips · **PENDIENTE de recibir** |
| Brand book de AccesoPro | Imagen corporativa · **PENDIENTE de recibir** |

## Condiciones

- Voz y avatar: voz propia o imagen propia, o avatar de stock. **Evalúan que sepas montar la
  clonación** → usamos voz clonada (ElevenLabs IVC). Avatar: opcional, no lo hacemos.
- Cuentas propias (canal de YouTube de prueba, claves de API propias).
- Reembolso de APIs hasta 30 € con justificantes.
- Herramientas libres, pero hay que justificar las elecciones.

## Nivel 1 – obligatorio (sin él, la prueba no supera la evaluación)

1. **Dos fuentes de entrada.**
   a. Texto del tema → dividir en ideas → guiones cortos de **30–60 s** con **gancho,
      explicación y cierre con llamada a la acción** → vídeos con **voz clonada**.
   b. Clase grabada → detectar automáticamente **5–8 momentos con valor propio** (explicación
      completa, truco de examen, error típico) → clips que se entiendan sin el resto de la clase.
2. **Agente revisor.** Un segundo modelo comprueba cada guion y cada clip contra el temario
   oficial y rechaza los que tengan errores o datos inventados. Los rechazados se corrigen o se
   descartan y **el motivo queda registrado**.
3. **Montaje.** Vertical 9:16 y horizontal 16:9, subtítulos quemados e imagen de marca.
4. **Publicación automática** en el canal de prueba como **no listados**, con título,
   descripción, etiquetas y miniatura generados.
5. **Tabla de control** con estado de cada pieza (generada, revisada, rechazada, publicada),
   coste real y enlace.

## Nivel 2 – avanzado

1. Aprobación humana opcional (enlace o botón, sin tocar código, activable/desactivable).
2. Variantes A/B: dos ganchos por vídeo y lógica explicada para elegir cuál se publica.
3. Robustez: cola de trabajos, reintentos, ningún duplicado al relanzar, límite de gasto diario,
   respeto de la cuota de la API de YouTube.

## Nivel 3 – opcional

1. Bucle de aprendizaje con YouTube Analytics para proponer temas y estilo de gancho.
2. Adaptación por comunidad autónoma reutilizando lo común.

## Entregables

- Código en repositorio, listo para ejecutar.
- README para una persona no técnica: cómo lanzarlo, qué se hizo, qué quedó fuera y por qué.
- Enlaces a vídeos publicados: **≥3 desde texto y ≥3 clips de la clase**.
- Ejemplos del revisor: **≥1 guion o clip rechazado con su motivo**.
- Tabla de control con estado y coste real de cada pieza.
- Documento de arquitectura (máx. 2 páginas): 1.000 vídeos/mes, coste por vídeo, cuellos de
  botella, qué se deja a revisión humana.
- Vídeo demo de máximo 5 minutos.

## Criterios de evaluación

| Criterio | Qué buscan |
|---|---|
| Funcionamiento de principio a fin | Nivel 1 con un solo disparador, sin pasos manuales |
| Calidad pedagógica | Guiones correctos, claros y útiles para un adulto que prepara un examen |
| Agente revisor | Comprobación real contra el temario, no revisión genérica |
| Detección de clips | Criterio pedagógico, no cortes por tiempo |
| Criterio técnico | Errores, costes y escalabilidad bien razonados |
| Priorización y comunicación | Qué se hizo primero y lo bien que se explica |

**Valoran más un Nivel 1 sólido y bien explicado que todos los niveles a medias.**

## Confidencialidad

El material es de Grupo Mentor: no compartirlo ni publicarlo fuera del canal de prueba, vídeos
siempre no listados, borrar material y vídeos 15 días después de la entrega. El código es nuestro.
