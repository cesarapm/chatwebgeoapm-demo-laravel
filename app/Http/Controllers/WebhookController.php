<?php

namespace App\Http\Controllers;

use App\Events\MessageReceived;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Recibe mensajes entrantes desde n8n / Twilio.
     * Si is_human = true en la conversación, NO responde nada (el agente humano
     * manejará desde el front).
     * Si is_human = false, simplemente guarda y deja que n8n continúe con la IA.
     */
    public function receive(Request $request)
    {
        $payload = $request->input('data', []);

        $from           = $payload['fromE164']        ?? null;
        $body           = $payload['body']            ?? '';
        $twilioSid      = $payload['raw']['data']['messageSid'] ?? null;
        $conversationId = $payload['conversation_id'] ?? null;

        if (!$from) {
            Log::warning('Webhook sin fromE164', $request->all());
            return response()->json(['status' => 'ignored'], 200);
        }

        // Obtener o crear contacto
        $contact = Contact::firstOrCreate(['phone' => $from]);

        // Obtener o crear conversación activa
        $conversation = Conversation::firstOrCreate(
            ['contact_id' => $contact->id, 'status' => 'active'],
            ['is_human' => false]
        );

        // Guardar el mensaje entrante
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'body'            => $body,
            'direction'       => 'inbound',
            'sender_type'     => 'user',
            'twilio_sid'      => $twilioSid,
        ]);

        // Emitir por WebSocket al front
        broadcast(new MessageReceived($message));

        Log::info('Mensaje guardado', [
            'contact'      => $from,
            'conversation' => $conversation->id,
            'is_human'     => $conversation->is_human,
        ]);

        return response()->json([
            'status'      => 'ok',
            'is_human'    => $conversation->is_human,
            'conversation_id' => $conversation->id,
        ], 200);
    }

    /**
     * Recibe el mensaje de respuesta que generó la IA en n8n
     * y lo guarda para mantener el hilo de la conversación.
     */
    public function storeOutbound(Request $request)
    {


    Log::info('Webhook outbound received', $request->all());
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'body'            => 'required|string',
            'twilio_sid'      => 'nullable|string',
        ]);

        $message = Message::create([
            'conversation_id' => $validated['conversation_id'],
            'body'            => $validated['body'],
            'direction'       => 'outbound',
            'sender_type'     => 'bot',
            'twilio_sid'      => $validated['twilio_sid'] ?? null,
        ]);

        broadcast(new MessageReceived($message));

        return response()->json(['status' => 'ok', 'message_id' => $message->id]);
    }
}
