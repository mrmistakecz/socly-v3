<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class StoryController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $stories = Story::with('user')
            ->active()
            ->where(function ($query) use ($user) {
                $query->whereIn('user_id', function ($q) use ($user) {
                    $q->select('following_id')
                      ->from('follows')
                      ->where('follower_id', $user->id);
                })->orWhere('user_id', $user->id);
            })
            ->latest()
            ->get()
            ->groupBy('user_id')
            ->map(fn($group) => [
                'user'    => [
                    'id'       => $group->first()->user->id,
                    'name'     => $group->first()->user->name,
                    'username' => $group->first()->user->username,
                    'avatar'   => $group->first()->user->avatar,
                    'isVIP'    => $group->first()->user->is_vip,
                ],
                'stories' => $group->map(fn($s) => [
                    'id'        => $s->id,
                    'media_url' => $s->media_url,
                    'type'      => $s->type,
                    'caption'   => $s->caption,
                    'is_locked' => $s->is_locked,
                    'expires_at' => $s->expires_at->toISOString(),
                ])->values(),
            ])->values();

        return response()->json($stories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'media'     => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,webm', 'max:51200'],
            'caption'   => ['nullable', 'string', 'max:200'],
            'is_locked' => ['boolean'],
        ]);

        $file      = $request->file('media');
        $extension = strtolower($file->getClientOriginalExtension());
        $disk      = config('filesystems.default');
        $isVideo   = in_array($extension, ['mp4', 'webm']);

        if ($isVideo) {
            $filename = Str::random(40) . '.' . $extension;
            Storage::disk($disk)->put('stories/' . $filename, file_get_contents($file->getRealPath()));
        } else {
            $filename = Str::random(40) . '.jpg';
            $img = Image::read($file)->scaleDown(width: 1080);
            Storage::disk($disk)->put('stories/' . $filename, $img->toJpeg(quality: 85)->toString());
        }

        $path     = 'stories/' . $filename;
        $mediaUrl = $disk === 'public' ? '/storage/' . $path : Storage::disk($disk)->url($path);

        $story = Story::create([
            'user_id'    => Auth::id(),
            'media_url'  => $mediaUrl,
            'type'       => $isVideo ? 'video' : 'image',
            'caption'    => $request->caption ? strip_tags($request->caption) : null,
            'is_locked'  => $request->boolean('is_locked'),
            'expires_at' => now()->addHours(24),
        ]);

        return response()->json(['success' => true, 'story' => $story]);
    }

    public function view(Story $story)
    {
        $userId = Auth::id();

        if ($story->user_id === $userId) {
            return response()->json(['viewed' => true]);
        }

        \DB::table('story_views')->insertOrIgnore([
            'story_id'  => $story->id,
            'user_id'   => $userId,
            'viewed_at' => now(),
        ]);

        return response()->json(['viewed' => true]);
    }

    public function destroy(Story $story)
    {
        if ($story->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $disk = config('filesystems.default');
        if (str_starts_with($story->media_url, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $story->media_url));
        } else {
            $key = parse_url($story->media_url, PHP_URL_PATH);
            if ($key) Storage::disk($disk)->delete(ltrim($key, '/'));
        }

        $story->delete();

        return response()->json(['success' => true]);
    }
}
