<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Lista todos los asesores (rol asesor).
     * Solo admin.
     */
    public function index()
    {
        $asesores = User::role('asesor')
            ->withCount(['assignedConversations as active_conversations' => function ($q) {
                $q->where('status', 'active');
            }])
            ->get()
            ->map(fn($u) => [
                'id'                   => $u->id,
                'name'                 => $u->name,
                'email'                => $u->email,
                'active_conversations' => $u->active_conversations,
            ]);

        return response()->json($asesores);
    }

    /**
     * Crea un nuevo asesor.
     * Solo admin.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole('asesor');

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => 'asesor',
        ], 201);
    }

    /**
     * Actualiza nombre / email / contraseña de un asesor.
     * Solo admin.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json(['status' => 'updated']);
    }

    /**
     * Elimina un asesor.
     * Solo admin.
     */
    public function destroy(User $user)
    {
        // No permitir que el admin se elimine a sí mismo
        if ($user->hasRole('admin')) {
            return response()->json(['error' => 'No puedes eliminar al administrador.'], 403);
        }

        $user->delete();

        return response()->json(['status' => 'deleted']);
    }

    /**
     * Asigna una conversación a un asesor.
     * Solo admin.
     */
    public function assignConversation(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'user_id'         => 'required|exists:users,id',
        ]);

        $asesor = User::findOrFail($validated['user_id']);

        if (!$asesor->hasRole('asesor')) {
            return response()->json(['error' => 'El usuario no es un asesor.'], 422);
        }

        \App\Models\Conversation::find($validated['conversation_id'])
            ->update(['assigned_to' => $validated['user_id']]);

        return response()->json(['status' => 'assigned']);
    }

    /**
     * Quita la asignación de una conversación.
     * Solo admin.
     */
    public function unassignConversation(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        \App\Models\Conversation::find($validated['conversation_id'])
            ->update(['assigned_to' => null]);

        return response()->json(['status' => 'unassigned']);
    }
}
