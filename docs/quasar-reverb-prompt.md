# Prompt para crear el frontend Quasar — Chatbot WhatsApp

Usa este prompt tal cual en tu agente de IA (Copilot, Cursor, ChatGPT, etc.) dentro de tu proyecto Quasar.

---

## PROMPT

```
Soy el desarrollador de un sistema de chat de WhatsApp.
El backend es Laravel 12 con:
- Laravel Reverb (WebSocket) corriendo en el puerto 8080
- Laravel Sanctum para autenticación (token Bearer)
- Las siguientes rutas de API:

  POST   /api/login
         Autenticación. No requiere token.
         Body: { email, password }
         Responde: { token, user: { id, name, email } }
         → Guarda el token en localStorage y úsalo como: Authorization: Bearer {token}

  POST   /api/logout
         Cierra sesión e invalida el token actual. Requiere Bearer token.

  GET    /api/conversations
         Devuelve lista de conversaciones activas:
         [{ id, is_human, status, contact: { id, phone, name }, last_message, updated_at }]

  GET    /api/conversations/{id}/messages
         Devuelve mensajes de la conversación:
         [{ id, conversation_id, body, direction, sender_type, twilio_sid, created_at }]

  PATCH  /api/conversations/{id}/toggle-human
         Activa o desactiva el modo humano.
         Responde: { conversation_id, is_human }

  POST   /api/conversations/{id}/send
         Envía un mensaje como agente humano (solo si is_human = true).
         Body: { body: "texto" }
         Responde: { status: "sent", message_id }

- Evento WebSocket (canal público):
  Canal: conversation.{id}
  Evento: MessageReceived
  Payload: { id, conversation_id, body, direction, sender_type, created_at }

---

Crea en Quasar (Vue 3 Composition API + Pinia + TypeScript) lo siguiente:

1. **Store `useConversationStore` (Pinia)**
   - Estado: conversations[], activeConversationId, messages[], loading
   - Actions: fetchConversations(), fetchMessages(id), toggleHuman(id), sendMessage(id, body)
   - Todas las llamadas a la API usan axios con el token Sanctum desde localStorage/cookie.

2. **Composable `useReverb`**
   - Recibe el conversation_id
   - Se conecta al WebSocket de Reverb usando la librería `laravel-echo` + `pusher-js`
   - Config de Echo:
     broadcaster: 'reverb'
     key: VITE_REVERB_APP_KEY (variable de entorno)
     wsHost: VITE_REVERB_HOST
     wsPort: VITE_REVERB_PORT (8080)
     forceTLS: false
     enabledTransports: ['ws']
   - Se suscribe al canal `conversation.{id}` y escucha el evento `MessageReceived`
   - Al recibir un mensaje, lo agrega al store (conversationStore.addMessage)
   - Limpia la suscripción en onUnmounted

3. **Layout `ChatLayout.vue`**
   - Usa QLayout de Quasar con un QDrawer izquierdo y un área principal.

4. **Componente `ConversationList.vue`**
   - Lista las conversaciones del store en un QList de Quasar.
   - Cada item muestra: nombre/teléfono del contacto, último mensaje, badge "Humano" si is_human = true.
   - Al hacer click activa la conversación (setActiveConversation(id)).

5. **Componente `ChatWindow.vue`**
   - Muestra los mensajes de la conversación activa en un scroll infinito.
   - Mensajes del usuario (direction: inbound) alineados a la izquierda.
   - Mensajes outbound del bot/agente alineados a la derecha.
   - Badge que muestra el sender_type (bot / human_agent).
   - Botón toggle "Tomar conversación / Ceder a IA" que llama toggleHuman().
   - Input de texto + botón enviar (solo activo cuando is_human = true).
   - Llama al composable useReverb(activeConversationId) para escuchar en tiempo real.

6. **Variables de entorno (.env)**
   VITE_API_URL=http://127.0.0.1:8000
   VITE_REVERB_APP_KEY=lmjexvaohw4amhwmsnlz
   VITE_REVERB_HOST=localhost
   VITE_REVERB_PORT=8080
   VITE_REVERB_SCHEME=http

7. **Instalación de dependencias necesarias:**
   npm install laravel-echo pusher-js axios pinia

Genera todos los archivos con código completo funcional, tipado con TypeScript.
```

---

## Variables de entorno que debes llenar (`.env` en Quasar)

Estas las encuentras en el `.env` de tu proyecto Laravel:

| Variable Quasar          | Valor real (de tu Laravel `.env`)                          |
|--------------------------|------------------------------------------------------------|
| `VITE_API_URL`           | `http://127.0.0.1:8000` — URL donde corre `php artisan serve` |
| `VITE_REVERB_APP_KEY`    | `lmjexvaohw4amhwmsnlz` — identifica tu app en Reverb      |
| `VITE_REVERB_HOST`       | `localhost` — donde corre el servidor WebSocket            |
| `VITE_REVERB_PORT`       | `8080` — puerto de Reverb (`php artisan reverb:start`)     |
| `VITE_REVERB_SCHEME`     | `http` — usa `https` solo en producción con SSL            |

---

## Flujo is_human explicado para el front

```
is_human = false (por defecto)
  → El input de texto está DESHABILITADO
  → Muestra texto: "La IA está respondiendo"
  → Botón: "Tomar conversación"

is_human = true  (después de hacer PATCH /toggle-human)
  → El input de texto está HABILITADO
  → Muestra badge: "MODO HUMANO"
  → Botón: "Ceder a IA"
  → Los mensajes enviados van por POST /send y llegan a WhatsApp real
```

---

## Estructura de carpetas sugerida para Quasar

```
src/
  stores/
    conversation.ts       ← Pinia store
  composables/
    useReverb.ts          ← WebSocket con Laravel Echo
  layouts/
    ChatLayout.vue
  components/
    ConversationList.vue
    ChatWindow.vue
  pages/
    ChatPage.vue          ← Ensambla layout + componentes
  boot/
    axios.ts              ← Configura axios con baseURL y token
```
