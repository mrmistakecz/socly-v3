<?php

namespace App\Http\Controllers;

use App\Events\NewMessage;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'exists:users,id'],
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'Zpráva je povinná.',
            'body.max' => 'Zpráva může mít maximálně 2000 znaků.',
        ]);

        if ((int) $validated['receiver_id'] === Auth::id()) {
            return back()->with('error', 'Nemůžete poslat zprávu sami sobě.');
        }

        // Sanitize message body (strip HTML tags)
        $body = strip_tags($validated['body']);

        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $validated['receiver_id'],
            'body' => $body,
        ]);

        broadcast(new NewMessage($message))->toOthers();

        if ($request->wantsJson()) return response()->json(['success' => true, 'message' => $message]);
        return back()->with('success', 'Zpráva odeslána.');
    }

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'exists:users,id'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,pdf,doc,docx', 'max:10240'], // 10MB max
            'body' => ['nullable', 'string', 'max:2000'],
        ], [
            'file.required' => 'Soubor je povinný.',
            'file.max' => 'Soubor může mít maximálně 10MB.',
            'file.mimes' => 'Nepovolený typ souboru.',
            'body.max' => 'Zpráva může mít maximálně 2000 znaků.',
        ]);

        if ((int) $validated['receiver_id'] === Auth::id()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Nemůžete poslat zprávu sami sobě.'], 400);
            }
            return back()->with('error', 'Nemůžete poslat zprávu sami sobě.');
        }

        $file = $request->file('file');
        
        // Validate file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'ogg', 'pdf', 'doc', 'docx'];
        $extension = strtolower($file->getClientOriginalExtension());
        // Browser MediaRecorder sometimes sends empty extension — default to webm
        if (empty($extension)) $extension = 'webm';
        
        if (!in_array($extension, $allowedExtensions)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Neplatná přípona souboru.'], 400);
            }
            return back()->withErrors(['file' => 'Neplatná přípona souboru.']);
        }
        
        // Generate secure random filename
        $filename = \Illuminate\Support\Str::random(40) . '.' . $extension;
        
        // Store file on configured disk (public or S3/R2)
        $disk = config('filesystems.default');
        Storage::disk($disk)->put('messages/' . $filename, file_get_contents($file->getRealPath()));
        $path = 'messages/' . $filename;
        $url = $disk === 'public'
            ? asset('storage/' . $path)
            : Storage::disk($disk)->url($path);

        // Sanitize message body if present
        $body = isset($validated['body']) ? strip_tags($validated['body']) : '';

        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $validated['receiver_id'],
            'body' => $body,
            'media' => $url,
        ]);

        broadcast(new NewMessage($message))->toOthers();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }
        return back()->with('success', 'Soubor odeslán.');
    }

    public function show(User $user)
    {
        $me = Auth::user();

        $messages = Message::where(function ($q) use ($me, $user) {
            $q->where('sender_id', $me->id)->where('receiver_id', $user->id);
        })->orWhere(function ($q) use ($me, $user) {
            $q->where('sender_id', $user->id)->where('receiver_id', $me->id);
        })->with(['reactions.user'])
          ->orderBy('created_at', 'asc')
          ->limit(100)
          ->get()
          ->map(function ($m) use ($me) {
              $mediaType = null;
              if ($m->media) {
                  if (str_contains($m->media, '.jpg') || str_contains($m->media, '.jpeg') || str_contains($m->media, '.png') || str_contains($m->media, '.gif')) {
                      $mediaType = 'image';
                  } elseif (str_contains($m->media, '.mp4') || str_contains($m->media, '.webm')) {
                      $mediaType = 'video';
                  } else {
                      $mediaType = 'file';
                  }
              }
              
              // Group reactions by emoji
              $reactions = [];
              foreach ($m->reactions as $reaction) {
                  $emoji = $reaction->emoji;
                  if (!isset($reactions[$emoji])) {
                      $reactions[$emoji] = [
                          'emoji' => $emoji,
                          'count' => 0,
                          'users' => [],
                          'hasReacted' => false,
                      ];
                  }
                  $reactions[$emoji]['count']++;
                  $reactions[$emoji]['users'][] = [
                      'id' => $reaction->user->id,
                      'name' => $reaction->user->name,
                  ];
                  if ($reaction->user_id === $me->id) {
                      $reactions[$emoji]['hasReacted'] = true;
                  }
              }
              
              return [
                  'id' => $m->id,
                  'body' => $m->body,
                  'media' => $m->media,
                  'mediaType' => $mediaType,
                  'isOwn' => $m->sender_id === $me->id,
                  'time' => $m->created_at->locale('cs')->format('H:i'),
                  'date' => $m->created_at->locale('cs')->isoFormat('D. MMM'),
                  'edited' => $m->edited_at !== null,
                  'status' => $m->sender_id === $me->id ? ($m->is_read ? 'read' : 'delivered') : null,
                  'reactions' => array_values($reactions),
              ];
          });

        // Mark as read
        Message::where('sender_id', $user->id)
            ->where('receiver_id', $me->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'messages' => $messages,
            'partner' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'verified' => $user->is_verified,
            ],
        ]);
    }

    public function markRead(User $user)
    {
        Message::where('sender_id', $user->id)
            ->where('receiver_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back();
    }

    public function update(Request $request, Message $message)
    {
        // Only allow editing own messages
        if ($message->sender_id !== Auth::id()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            return back()->with('error', 'Nemůžete upravit cizí zprávu.');
        }

        // Only allow editing messages sent in last 15 minutes
        if ($message->created_at->diffInMinutes(now()) > 15) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Too late to edit'], 403);
            }
            return back()->with('error', 'Zprávu lze upravit pouze do 15 minut po odeslání.');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        // Sanitize message body
        $body = strip_tags($validated['body']);

        $message->update([
            'body' => $body,
            'edited_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }
        return back()->with('success', 'Zpráva upravena.');
    }

    public function destroy(Message $message)
    {
        // Only allow deleting own messages
        if ($message->sender_id !== Auth::id()) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            return back()->with('error', 'Nemůžete smazat cizí zprávu.');
        }

        // Only allow deleting messages sent in last 1 hour
        if ($message->created_at->diffInHours(now()) > 1) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Too late to delete'], 403);
            }
            return back()->with('error', 'Zprávu lze smazat pouze do 1 hodiny po odeslání.');
        }

        $message->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Zpráva smazána.');
    }

    public function addReaction(Request $request, Message $message)
    {
        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:10'],
        ]);

        // Check if user is part of the conversation
        $user = Auth::user();
        if ($message->sender_id !== $user->id && $message->receiver_id !== $user->id) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            return back()->with('error', 'Nemůžete reagovat na tuto zprávu.');
        }

        // Use firstOrCreate to prevent race conditions
        $reaction = MessageReaction::firstOrCreate(
            [
                'message_id' => $message->id,
                'user_id' => $user->id,
            ],
            [
                'emoji' => $validated['emoji'],
            ]
        );

        // If reaction already existed, update the emoji
        if (!$reaction->wasRecentlyCreated && $reaction->emoji !== $validated['emoji']) {
            $reaction->update(['emoji' => $validated['emoji']]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'reaction' => $reaction]);
        }
        return back();
    }

    public function removeReaction(Request $request, Message $message)
    {
        $user = Auth::user();
        
        $deleted = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'deleted' => $deleted]);
        }
        return back();
    }
}
