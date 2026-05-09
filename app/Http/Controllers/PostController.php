<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use App\Services\ModerationService;

class PostController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'caption'   => ['nullable', 'string', 'max:2000'],
            'image'     => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm', 'max:102400'],
            'is_locked' => ['boolean'],
            'price'     => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [
            'caption.max'   => 'Popis může mít maximálně 2000 znaků.',
            'image.required' => 'Soubor je povinný.',
            'image.mimes'   => 'Povolené formáty: jpg, png, webp, gif, mp4, webm.',
            'image.max'     => 'Soubor může mít maximálně 100 MB.',
            'price.max'     => 'Cena může být maximálně 100 000.',
        ]);

        $file = $request->file('image');
        $extension = strtolower($file->getClientOriginalExtension());
        $disk = config('filesystems.default');

        $videoExtensions = ['mp4', 'webm'];
        $isVideo = in_array($extension, $videoExtensions);

        if ($isVideo) {
            $filename = Str::random(40) . '.' . $extension;
            Storage::disk($disk)->put('posts/' . $filename, file_get_contents($file->getRealPath()));
            $path = 'posts/' . $filename;
        } else {
            $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($extension, $allowedImageExtensions)) {
                return back()->withErrors(['image' => 'Neplatná přípona souboru.']);
            }
            // Re-encode image: strips EXIF, normalizes format, resizes to max 1920px
            $filename = Str::random(40) . '.jpg';
            $img = Image::read($file)->scaleDown(width: 1920);
            Storage::disk($disk)->put('posts/' . $filename, $img->toJpeg(quality: 80)->toString());
            $path = 'posts/' . $filename;

            // Generate 400px thumbnail for grid view (F4.3)
            $thumbName = 'thumb_' . $filename;
            $thumb = Image::read($file)->scaleDown(width: 400);
            Storage::disk($disk)->put('posts/' . $thumbName, $thumb->toJpeg(quality: 75)->toString());
        }

        $imageUrl = $disk === 'public'
            ? '/storage/' . $path
            : Storage::disk($disk)->url($path);

        $thumbUrl = null;
        if (!$isVideo && isset($thumbName)) {
            $thumbPath = 'posts/' . $thumbName;
            $thumbUrl = $disk === 'public'
                ? '/storage/' . $thumbPath
                : Storage::disk($disk)->url($thumbPath);
        }

        // Sanitize caption (strip HTML tags)
        $caption = $validated['caption'] ? strip_tags($validated['caption']) : null;

        // AI moderation check
        if ($caption) {
            $moderation = app(ModerationService::class);
            if (!$moderation->isSafe($caption)) {
                $categories = implode(', ', $moderation->flaggedCategories($caption));
                if ($request->wantsJson()) {
                    return response()->json(['error' => "Obsah porušuje pravidla komunity ($categories)."], 422);
                }
                return back()->withErrors(['caption' => "Obsah porušuje pravidla komunity ($categories)."]);
            }
        }

        $post = Post::create([
            'user_id'   => Auth::id(),
            'caption'   => $caption,
            'image'     => $imageUrl,
            'thumbnail' => $thumbUrl,
            'is_video'  => $isVideo,
            'is_locked' => $validated['is_locked'] ?? false,
            'price'     => $validated['price'] ?? null,
        ]);

        if ($request->wantsJson()) {
            $user = Auth::user();
            return response()->json([
                'success' => true,
                'post' => [
                    'id' => $post->id,
                    'creator' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                        'avatar' => $user->avatar,
                        'verified' => $user->is_verified,
                    ],
                    'image' => $post->image,
                    'likes' => 0,
                    'comments' => 0,
                    'isLocked' => $post->is_locked,
                    'price' => $post->price,
                    'isVideo' => $post->is_video,
                    'caption' => $post->caption,
                    'timeAgo' => 'právě teď',
                    'isLiked' => false,
                    'isBookmarked' => false,
                    'recentComments' => [],
                ]
            ]);
        }

        return redirect('/?tab=home')->with('success', 'Příspěvek byl vytvořen!');
    }

    public function destroy(Request $request, Post $post)
    {
        // Check authorization first
        if ($post->user_id !== Auth::id() && !Auth::user()->is_admin) {
            abort(403, 'Nemáte oprávnění smazat tento příspěvek.');
        }

        // Delete file from storage
        if ($post->image) {
            try {
                $disk = config('filesystems.default');
                if (str_starts_with($post->image, '/storage/')) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $post->image));
                } else {
                    $key = parse_url($post->image, PHP_URL_PATH);
                    if ($key) Storage::disk($disk)->delete(ltrim($key, '/'));
                }
            } catch (\Exception $e) {
                \Log::error('Failed to delete post image: ' . $e->getMessage());
            }
        }

        $post->delete();

        if ($request->wantsJson()) return response()->json(['success' => true]);
        return back()->with('success', 'Příspěvek byl smazán.');
    }
}
