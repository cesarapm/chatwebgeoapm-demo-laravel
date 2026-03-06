# Prompt para crear el Panel de Administración — Quasar

Usa este prompt en tu agente de IA (Copilot, Cursor, ChatGPT, etc.) dentro de tu proyecto Quasar.
Este documento cubre **solo la parte de admin y roles**. El chat ya está en `quasar-reverb-prompt.md`.

---

## PROMPT

```
Tengo un proyecto Quasar (Vue 3 Composition API + Pinia + TypeScript) con un chat de WhatsApp
ya implementado. Ahora necesito agregar el sistema de roles y el panel de administración.

El backend es Laravel 12 con Sanctum + Spatie Permission.
Hay dos roles: admin y asesor.

Al hacer login (POST /api/login) el endpoint devuelve:
{ token, user: { id, name, email, roles: [{ name: 'admin' | 'asesor' }] } }

Guarda el token en localStorage y el rol en el store de auth.
El rol se lee así: user.roles[0].name === 'admin'

---

Rutas de API relevantes para este módulo:

// ── Auth ─────────────────────────────────────────────────
GET  /api/user   Bearer token → devuelve user con roles

// ── Solo Admin ────────────────────────────────────────────
GET    /api/admin/asesores
       Lista asesores con conversaciones activas.
       Responde: [{ id, name, email, active_conversations }]

POST   /api/admin/asesores
       Crea un nuevo asesor.
       Body: { name, email, password }
       Responde: { id, name, email, role: "asesor" }

PUT    /api/admin/asesores/{id}
       Edita nombre, email o contraseña.
       Body: { name?, email?, password? }

DELETE /api/admin/asesores/{id}
       Elimina un asesor.

POST   /api/admin/assign
       Asigna una conversación a un asesor.
       Body: { conversation_id, user_id }
       Responde: { status: "assigned" }

GET    /api/conversations
       Admin → devuelve TODAS las conversaciones activas con el campo assigned_to.
       Responde: [{ id, is_human, status,
                    contact: { id, phone, name },
                    assigned_to: { id, name } | null,
                    last_message, updated_at }]

---

Crea en Quasar lo siguiente:

1. **Store `useAuthStore` (Pinia)**
   - Estado: user, token, isAdmin (computed: roles[0].name === 'admin')
   - Actions: login(email, password), logout(), fetchUser()
   - Persiste token en localStorage
   - Después del login configura axios.defaults.headers.Authorization

2. **Store `useAdminStore` (Pinia)**
   - Estado: asesores[], conversations[], loading
   - Actions:
     fetchAsesores()
     createAsesor({ name, email, password })
     updateAsesor(id, data)
     deleteAsesor(id)
     fetchAllConversations()
     assignConversation(conversation_id, user_id)

3. **Guard de rutas**
   - Si no hay token → redirigir a /login
   - Rutas con meta: { requiresAdmin: true } → si no es admin redirigir a /chat

4. **AdminPage.vue** (ruta /admin, meta: requiresAdmin: true)
   - Dos tabs: "Conversaciones" | "Asesores"

   Tab "Conversaciones":
   - QTable con columnas: Contacto (phone/name), Último mensaje,
     Asesor asignado (o "Sin asignar"), Modo (bot/humano), Acciones.
   - Botón "Asignar" por fila → abre AssignDialog.

   Tab "Asesores":
   - QTable con columnas: Nombre, Email, Conversaciones activas, Acciones.
   - Botón "Nuevo asesor" → abre AsesorDialog en modo crear.
   - Botón editar por fila → abre AsesorDialog prellenado.
   - Botón eliminar → QDialog de confirmación antes de borrar.

5. **AsesorDialog.vue**
   - QDialog con formulario: Nombre, Email, Contraseña (opcional en edición).
   - Modo "crear": llama adminStore.createAsesor().
   - Modo "editar": llama adminStore.updateAsesor().
   - Cierra y refresca la lista al guardar.

6. **AssignDialog.vue**
   - QDialog con un QSelect que lista los asesores (nombre + conversaciones activas).
   - Al confirmar llama adminStore.assignConversation(conversation_id, user_id).
   - Refresca la tabla de conversaciones.

7. **En ChatPage.vue** (ya existente):
   - Si isAdmin, muestra un QBtn "Panel Admin" en la toolbar que navega a /admin.
   - En ConversationList, cada item muestra badge con el nombre del asesor asignado.

Genera todos los archivos con código completo funcional, tipado con TypeScript.
Usa componentes de Quasar (QTable, QDialog, QBtn, QInput, QSelect, QBadge, QTabs, QTab).
```

---

## Credenciales del administrador inicial

| Campo | Valor |
|-------|-------|
| Email    | `admin@example.com` |
| Password | `123123`            |

> Cámbiala con `php artisan tinker` → `User::find(1)->update(['password' => bcrypt('nueva')])`

---

## Lógica de roles para el front

```
Rol: admin
  ✓ Ve TODAS las conversaciones (asignadas y sin asignar)
  ✓ Asigna conversaciones a asesores
  ✓ Crea, edita y elimina asesores
  ✓ Accede a /admin

Rol: asesor
  ✓ Solo ve las conversaciones asignadas a él
  ✓ Toma/cede sus conversaciones a la IA
  ✓ Envía mensajes cuando is_human = true
  ✗ No accede a /admin
```

---

## Estructura de carpetas (solo lo nuevo)

```
src/
  stores/
    auth.ts              ← login, logout, rol, isAdmin
    admin.ts             ← asesores, asignación, conversaciones admin
  pages/
    AdminPage.vue        ← tabs Conversaciones + Asesores
  components/
    AsesorDialog.vue     ← crear / editar asesor
    AssignDialog.vue     ← asignar conversación a asesor
  router/
    index.ts             ← agregar guard requiresAdmin y ruta /admin
```


---

## Estructura de carpetas sugerida

```
src/
  stores/
    auth.ts              ← login, logout, rol
    conversation.ts      ← conversaciones y mensajes
    admin.ts             ← asesores y asignación (solo admin)
  composables/
    useReverb.ts         ← WebSocket con Laravel Echo
  layouts/
    MainLayout.vue       ← QLayout con drawer y toolbar
  pages/
    LoginPage.vue
    ChatPage.vue
    AdminPage.vue
  components/
    ConversationList.vue
    ChatWindow.vue
    AsesorDialog.vue     ← crear/editar asesor
    AssignDialog.vue     ← asignar conversación
  router/
    index.ts             ← guards de roles
  boot/
    axios.ts
```
