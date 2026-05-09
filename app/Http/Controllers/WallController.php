<?php

namespace App\Http\Controllers;

use App\Events\NewNotification;
use App\Notifications\SoclyNotification;
use App\Events\PostInteraction;
use App\Models\Post;
use App\Models\User;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\ModerationService;

class WallController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $sort = $request->get('sort', 'latest');

        $postsQuery = Post::with(['user', 'comments.user'])
            ->withCount(['likes', 'comments']);

        if ($sort === 'trending') {
            $postsQuery->orderByDesc('likes_count');
        } else {
            $postsQuery->latest();
        }

        $posts = $postsQuery->limit(20)
            ->get()
            ->map(function ($post) use ($user) {
                return [
                    'id' => $post->id,
                    'creator' => [
                        'id' => $post->user->id,
                        'name' => $post->user->name,
                        'username' => $post->user->username,
                        'avatar' => $post->user->avatar,
                        'verified' => $post->user->is_verified,
                    ],
                    'image' => $post->image,
                    'likes' => $post->likes_count,
                    'comments' => $post->comments_count,
                    'isLocked' => $post->is_locked,
                    'price' => $post->price,
                    'isVideo' => $post->is_video,
                    'caption' => $post->caption,
                    'timeAgo' => $post->created_at->locale('cs')->diffForHumans(),
                    'isLiked' => $user ? $user->hasLiked($post) : false,
                    'isBookmarked' => $user ? $user->hasBookmarked($post) : false,
                    'recentComments' => $post->comments->sortByDesc('created_at')->take(5)->map(fn ($c) => [
                        'id' => $c->id,
                        'body' => $c->body,
                        'user' => [
                            'name' => $c->user->name,
                            'avatar' => $c->user->avatar,
                        ],
                        'timeAgo' => $c->created_at->locale('cs')->diffForHumans(),
                    ])->values(),
                ];
            });

        $creators = User::where('id', '!=', $user?->id)
            ->withCount('followers')
            ->orderByDesc('followers_count')
            ->limit(8)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'avatar' => $c->avatar,
                'hasStory' => true,
                'isVIP' => $c->is_vip,
                'isLive' => false,
            ]);

        $topCreators = User::where('id', '!=', $user?->id)
            ->withCount('followers')
            ->orderByDesc('followers_count')
            ->limit(10)
            ->get()
            ->map(fn ($c, $i) => [
                'id' => $c->id,
                'name' => $c->name,
                'username' => $c->username,
                'avatar' => $c->avatar,
                'followers' => $this->formatNumber($c->followers_count),
                'verified' => $c->is_verified,
                'badge' => $i + 1,
            ]);

        // Conversations: single query with subqueries for last_message + unread_count
        $conversations = collect();
        if ($user) {
            $uid = $user->id;

            $conversations = DB::table('users')
                ->joinSub(
                    DB::table('messages')
                        ->selectRaw("CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as partner_id", [$uid])
                        ->selectRaw("MAX(id) as last_message_id")
                        ->selectRaw("SUM(CASE WHEN receiver_id = ? AND is_read = false THEN 1 ELSE 0 END) as unread_count", [$uid])
                        ->where(function ($q) use ($uid) {
                            $q->where('sender_id', $uid)->orWhere('receiver_id', $uid);
                        })
                        ->groupByRaw("CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END", [$uid]),
                    'conv', fn ($join) => $join->on('users.id', '=', 'conv.partner_id')
                )
                ->leftJoin('messages as lm', 'lm.id', '=', 'conv.last_message_id')
                ->select([
                    'users.id', 'users.name', 'users.username', 'users.avatar',
                    'users.is_verified as verified', 'users.is_vip as isVIP',
                    'lm.body as last_body', 'lm.created_at as last_at',
                    'conv.unread_count',
                ])
                ->orderByDesc('lm.created_at')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'username' => $c->username,
                    'avatar' => $c->avatar,
                    'verified' => (bool) $c->verified,
                    'isVIP' => (bool) $c->isVIP,
                    'isOnline' => false,
                    'lastMessage' => $c->last_body ?? '',
                    'time' => $c->last_at ? \Carbon\Carbon::parse($c->last_at)->locale('cs')->diffForHumans(short: true) : '',
                    'unread' => (int) $c->unread_count,
                    'hasMedia' => false,
                ]);
        }

        $trendingPosts = Post::with('user')
            ->orderByDesc('posts.likes_count')
            ->limit(9)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'image' => $p->image,
                'isLocked' => $p->is_locked,
                'isVideo' => $p->is_video,
                'likes' => $this->formatNumber($p->likes_count),
                'caption' => $p->caption,
                'creator' => ['id' => $p->user->id],
            ]);

        return Inertia::render('Wall', [
            'posts' => $posts,
            'stories' => $creators,
            'topCreators' => $topCreators,
            'trendingPosts' => $trendingPosts,
            'conversations' => $conversations,
        ]);
    }

    public function postsApi(Request $request)
    {
        $user   = Auth::user();
        $sort   = $request->get('sort', 'latest');
        $lastId = (int) $request->get('last_id', 0);
        $limit  = 20;

        $postsQuery = Post::with(['user', 'comments.user'])
            ->withCount(['likes', 'comments']);

        if ($sort === 'trending') {
            $postsQuery->orderByDesc('likes_count')->orderByDesc('id');
            if ($lastId) $postsQuery->where('id', '<', $lastId);
        } else {
            $postsQuery->latest();
            if ($lastId) $postsQuery->where('id', '<', $lastId);
        }

        $posts = $postsQuery->limit($limit + 1)->get();
        $hasMore = $posts->count() > $limit;
        $posts = $posts->take($limit);

        $mapped = $posts->map(function ($post) use ($user) {
            return [
                'id'       => $post->id,
                'creator'  => [
                    'id'       => $post->user->id,
                    'name'     => $post->user->name,
                    'username' => $post->user->username,
                    'avatar'   => $post->user->avatar,
                    'verified' => $post->user->is_verified,
                ],
                'image'          => $post->image,
                'likes'          => $post->likes_count,
                'comments'       => $post->comments_count,
                'isLocked'       => $post->is_locked,
                'price'          => $post->price,
                'isVideo'        => $post->is_video,
                'caption'        => $post->caption,
                'timeAgo'        => $post->created_at->locale('cs')->diffForHumans(),
                'isLiked'        => $user ? $user->hasLiked($post) : false,
                'isBookmarked'   => $user ? $user->hasBookmarked($post) : false,
                'recentComments' => $post->comments->sortByDesc('created_at')->take(5)->map(fn ($c) => [
                    'id'      => $c->id,
                    'body'    => $c->body,
                    'user'    => ['name' => $c->user->name, 'avatar' => $c->user->avatar],
                    'timeAgo' => $c->created_at->locale('cs')->diffForHumans(),
                ])->values(),
            ];
        });

        return response()->json([
            'posts'    => $mapped,
            'has_more' => $hasMore,
            'last_id'  => $posts->last()?->id,
        ]);
    }

    public function like(Request $request, Post $post)
    {
        $user = Auth::user();
        
        // Use firstOrCreate to prevent race conditions
        $like = $user->likes()->where('post_id', $post->id)->first();

        if ($like) {
            // Unlike
            $like->delete();
            $post->decrement('likes_count');
            $newCount = $post->fresh()->likes_count;
            broadcast(new PostInteraction($post->id, 'likes', $newCount))->toOthers();
            if ($request->wantsJson()) return response()->json(['success' => true, 'action' => 'unliked', 'count' => $newCount]);
            return back();
        }

        // Like - use firstOrCreate to prevent duplicates
        $user->likes()->firstOrCreate(['post_id' => $post->id]);
        $post->increment('likes_count');
        $newCount = $post->fresh()->likes_count;
        broadcast(new PostInteraction($post->id, 'likes', $newCount))->toOthers();

        if ($post->user_id !== $user->id) {
            broadcast(new NewNotification(
                userId: $post->user_id,
                type: 'like',
                message: $user->name . ' dal/a like vašemu příspěvku',
                avatar: $user->avatar,
                postId: $post->id,
            ));
            $post->user->notify(new SoclyNotification('like', $user->name . ' dal/a like vašemu příspěvku', $user->avatar, $post->id));
        }

        if ($request->wantsJson()) return response()->json(['success' => true, 'action' => 'liked', 'count' => $newCount]);
        return back();
    }

    public function bookmark(Request $request, Post $post)
    {
        $user = Auth::user();
        $existing = $user->bookmarks()->where('post_id', $post->id)->first();

        if ($existing) {
            $existing->delete();
            if ($request->wantsJson()) return response()->json(['success' => true, 'action' => 'unbookmarked']);
            return back();
        }

        // Use firstOrCreate to prevent race conditions
        $user->bookmarks()->firstOrCreate(['post_id' => $post->id]);
        
        if ($request->wantsJson()) return response()->json(['success' => true, 'action' => 'bookmarked']);
        return back();
    }

    public function comment(Request $request, Post $post)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $user = Auth::user();

        // Sanitize comment body
        $body = strip_tags($validated['body']);

        // AI moderation check
        $moderation = app(ModerationService::class);
        if (!$moderation->isSafe($body)) {
            $categories = implode(', ', $moderation->flaggedCategories($body));
            if ($request->wantsJson()) {
                return response()->json(['error' => "Komentář porušuje pravidla komunity ($categories)."], 422);
            }
            return back()->withErrors(['body' => "Komentář porušuje pravidla komunity ($categories)."]);
        }

        $post->comments()->create([
            'user_id' => $user->id,
            'body' => $body,
        ]);

        $post->increment('comments_count');
        $newCount = $post->fresh()->comments_count;
        broadcast(new PostInteraction($post->id, 'comments', $newCount))->toOthers();

        if ($post->user_id !== $user->id) {
            broadcast(new NewNotification(
                userId: $post->user_id,
                type: 'comment',
                message: $user->name . ' komentoval/a váš příspěvek',
                avatar: $user->avatar,
                postId: $post->id,
            ));
            $post->user->notify(new SoclyNotification('comment', $user->name . ' komentoval/a váš příspěvek', $user->avatar, $post->id));
        }

        if ($request->wantsJson()) return response()->json(['success' => true]);
        return back();
    }

    public function discover(Request $request)
    {
        $category = $request->get('category', 'all');

        $query = Post::with('user');

        switch ($category) {
            case 'trending':
                $query->orderByDesc('likes_count');
                break;
            case 'popular':
                $query->where('likes_count', '>=', 1)->orderByDesc('comments_count');
                break;
            case 'new':
                $query->orderByDesc('created_at');
                break;
            case 'vip':
                $query->whereHas('user', fn ($q) => $q->where('is_vip', true));
                break;
            default:
                $query->orderByDesc('likes_count');
        }

        $posts = $query->limit(18)->get()->map(fn ($p) => [
            'id' => $p->id,
            'image' => $p->image,
            'isLocked' => $p->is_locked,
            'isVideo' => $p->is_video,
            'likes' => $this->formatNumber($p->likes_count),
            'caption' => $p->caption,
            'creator' => ['id' => $p->user->id],
        ]);

        return response()->json(['posts' => $posts]);
    }

    public function bookmarks()
    {
        $user = Auth::user();

        $posts = $user->bookmarks()
            ->with('post.user')
            ->latest()
            ->get()
            ->map(function ($bm) {
                $p = $bm->post;
                if (!$p) return null;
                return [
                    'id' => $p->id,
                    'image' => $p->image,
                    'isLocked' => $p->is_locked,
                    'isVideo' => $p->is_video,
                    'likes' => $p->likes_count,
                ];
            })->filter()->values();

        return response()->json(['posts' => $posts]);
    }

    private function formatNumber(int $num): string
    {
        if ($num >= 1000000) return round($num / 1000000, 1) . 'M';
        if ($num >= 1000) return round($num / 1000, 1) . 'K';
        return (string) $num;
    }
}
