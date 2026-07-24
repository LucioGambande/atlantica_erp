---
name: resilient-architecture
description: Checklist de arquitectura resiliente y performante de la agencia — idempotencia, colas, reintentos, timeouts, caching, N+1, observabilidad. Usar al diseñar features con integraciones/colas/webhooks, al revisar arquitectura, o cuando algo "se pierde", se duplica o anda lento en producción.
---

# Arquitectura resiliente y performante

Checklist agnóstico de framework (aplica igual a Laravel, NestJS o lo que sea). No es teoría: cada punto es una pregunta que se responde señalando código.

## Resiliencia

1. **Idempotencia en todo lo que puede llegar dos veces.** Webhooks, mensajes de cola, retries de clientes: los proveedores entregan al-menos-una-vez. Clave de idempotencia (id de evento, id externo + tipo de operación) chequeada ANTES de producir efectos. Pregunta de control: *si esto se procesa dos veces, ¿se duplica algo?*
2. **Reintentos con backoff exponencial + límite + destino final.** Todo lo que habla con el exterior reintenta con backoff y jitter; lo que agota reintentos va a una dead letter queue o queda marcado para intervención — **nunca se descarta en silencio**. Pregunta: *¿dónde termina un job que falló 5 veces, y quién se entera?*
3. **Timeouts explícitos en toda llamada externa.** HTTP, DB, colas: sin timeout, un servicio lento de terceros cuelga el tuyo. Pregunta: *si esta API tarda 60 segundos, ¿qué pasa acá?*
4. **Lo lento va a cola, el request responde rápido.** Emails, syncs, llamadas a APIs, procesamiento pesado: encolar y responder. El handler de un webhook solo verifica firma, valida y encola.
5. **Fallas parciales a mitad de un flujo.** En cualquier secuencia "escribo en A, después en B": ¿qué queda si muere en el medio? Transacción si es una sola DB; outbox pattern o reconciliación si cruza sistemas. Pregunta: *¿puede quedar A hecho y B no, y cómo se detecta?*
6. **Rate limits ajenos respetados.** Toda API externa tiene límite (Shopify, WhatsApp, ERPs): throttling propio + manejo del 429 con retry-after. Pregunta: *¿qué pasa cuando el proveedor nos empieza a rechazar?*
7. **Degradación graciosa.** Si un servicio no crítico cae (recomendaciones, analytics), la funcionalidad core sigue. Identificar qué es core y qué puede faltar.

## Performance

8. **N+1 y queries en loops.** Eager loading / joins / batch. Pregunta: *¿cuántas queries dispara esta pantalla con 200 registros?*
9. **Paginación en todo listado sin tope natural.** Interno o de cara al usuario: nada de `SELECT *` de tablas que crecen. Cursor-based si el volumen es serio.
10. **Índices para las queries reales.** Las columnas de WHERE/ORDER BY de las queries calientes tienen índice; verificado con EXPLAIN sobre volumen realista, no con la tabla de dev con 20 filas.
11. **Caching con invalidación explícita.** Cachear lo caro y leído mucho — con TTL y un plan de invalidación escrito (¿qué evento lo invalida?). Un cache sin estrategia de invalidación es un bug futuro.
12. **Lo pesado, precalculado.** Reportes, agregaciones, feeds: job programado que materializa, no cálculo en el request.

## Observabilidad

13. **Logs estructurados con id de correlación.** Request id / job id / conversation id presente en cada línea del flujo — poder reconstruir la historia de UN pedido puntual en producción.
14. **Los fallos silenciosos no existen.** Catch vacío, promesa sin await, job sin monitoreo: prohibidos. Todo camino de error termina en log + métrica o en re-lanzar.
15. **Alertas sobre síntomas de negocio**, no solo infra: "0 órdenes sincronizadas en 1h" vale más que "CPU al 40%". Definir 2-3 por sistema.

## Operación

16. **Migraciones sin downtime**: expand → migrate → contract (agregar columna nullable, backfill, después restringir). Nunca un rename/drop directo sobre tabla en uso.
17. **Config por entorno validada al boot** — si falta una variable, el proceso no arranca.
18. **Reprocesamiento manual posible**: ante un bug en el sync, tiene que existir una forma documentada de re-correr un rango (comando/endpoint), no editar la DB a mano.

## Cómo usar el checklist

- **Diseñando**: recorrer 1-7 sobre cada flecha del diagrama que cruza un límite (proceso, red, sistema).
- **Revisando**: los puntos son preguntas; cada respuesta es código señalable (`archivo:línea`) o un hallazgo.
- **Dimensionar primero**: preguntar la escala real (usuarios, RPS, tamaño de datos, frecuencia de sync) antes de recomendar — la solución para 100x el volumen actual es sobre-ingeniería, no previsión.
