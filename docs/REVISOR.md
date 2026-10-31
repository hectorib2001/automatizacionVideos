# Agente revisor – especificación

Es el criterio más importante de la evaluación: "comprobación real contra el temario, no una
revisión genérica". Un vídeo con un error es peor que no publicarlo: **ante la duda, rechaza**.

## Entradas

- La pieza: guion JSON (texto) o transcripción + metadatos del clip.
- `docs/TEMARIO_OFICIAL.md` completo (es corto: va entero en el prompt, sin RAG).
- La estructura del tema AccesoPro correspondiente (T1_S01: operaciones con números reales;
  ordenación y representación; propiedades).
- Modelo: rol `reviewer` de `LlmGateway` (distinto del generador).

## Comprobaciones (cada una produce `pass | fail` + detalle)

1. **matematica** – Corrección de cada afirmación. El LLM extrae las afirmaciones verificables a
   `claims[]` y `MathVerifier` las recalcula. Cualquier discrepancia → `fail` automático,
   aunque el LLM diga que está bien.
2. **temario** – La pieza trata contenidos de los saberes básicos oficiales del bloque
   correspondiente y no se sale del nivel de la prueba. Debe citar el saber concreto.
3. **datos_inventados** – Rechaza estadísticas, porcentajes, fechas, nombres de leyes,
   formatos de examen o promesas que no estén en las fuentes. Ejemplo real: la `frase_final` del
   podcast dice que tres reglas "resuelven el noventa por ciento de los errores" → dato sin fuente.
4. **pedagogia** – Claro para un adulto que prepara un examen; el gancho no engaña; el ejemplo
   es correcto y la explicación no contradice al ejemplo.
5. **autonomia** (solo clips) – Se entiende sin el resto de la clase; empieza y acaba en frase
   completa; no depende de "lo de antes" o de una pizarra que no se ve.
6. **formato** (solo guiones) – Tiene gancho, explicación y CTA; 75–150 palabras.

## Salida (JSON estructurado)

```json
{
  "verdict": "aprobado | rechazado",
  "checks": {
    "matematica": {"status": "pass|fail", "detail": "..."},
    "temario": {"status": "pass|fail", "detail": "...", "saber_oficial": "Bloque 1 · Números reales: operaciones..."},
    "datos_inventados": {"status": "pass|fail", "detail": "..."},
    "pedagogia": {"status": "pass|fail", "detail": "..."},
    "autonomia": {"status": "pass|fail|n/a", "detail": "..."},
    "formato": {"status": "pass|fail|n/a", "detail": "..."}
  },
  "claims": [{"expresion": "1/2+1/3", "resultado": "5/6", "texto_original": "un medio más un tercio son cinco sextos"}],
  "reasons": ["motivos concretos y accionables para corregir"],
  "fix_suggestion": "qué cambiar exactamente"
}
```
`verdict = aprobado` solo si todas las comprobaciones aplicables son `pass` **y** `MathVerifier`
no encontró discrepancias.

## MathVerifier

- Gramática admitida en `expresion`: enteros, decimales con punto, fracciones `a/b`, `+ - * /`,
  potencias `^` con exponente entero (negativo incluido), paréntesis, `sqrt(n)`.
- Aritmética exacta con `Brick\Math\BigRational`; `sqrt` y decimales periódicos se comparan en
  coma flotante con tolerancia 1e-9 (o 1e-2 si el texto dice "aproximadamente").
- Si una expresión no se puede parsear → se marca `unverifiable` (no bloquea, pero se registra).
- Tests mínimos: `2+3*4=14`, `1/2+1/3=5/6`, `2^-3=1/8`, `(3/4)/(2/5)=15/8`, `2/3+3/4-1/3=13/12`,
  `2^-3=-8` debe fallar, `1/2+1/3=2/5` debe fallar.

## Bucle

1. Revisión (intento 1). Si `rechazado` → `ReviseScript` con `reasons` y `fix_suggestion`.
2. Nueva revisión (intento 2, 3). Tras 2 correcciones fallidas → `descartada` con el último motivo.
3. Clips: no se pueden "corregir" (es lo que dijo el profesor) → se descartan o se recortan
   para excluir el fragmento erróneo, y se registra.
4. Cada pasada crea una fila en `reviews`. Exportar al menos un rechazo como ejemplo entregable.

## Casos de prueba obligatorios

- Podcast T1_S01 completo: las cuentas son correctas (verificado), pero la frase del "90 %" debe
  rechazarse por `datos_inventados`.
- Guion con error inyectado ("dos elevado a menos tres es menos ocho") → `matematica: fail`.
- Guion correcto sobre propiedades (conmutativa no aplica a resta/división) → `aprobado`.
