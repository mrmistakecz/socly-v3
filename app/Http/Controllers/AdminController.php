<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $search = $request->get('search', '');

        $stats = [
            'totalUsers' => User::count(),
            'totalPosts' => Post::count(),
            'totalMessages' => Message::count(),
            'newUsersToday' => User::whereDate('created_at', today())->count(),
            'newPostsToday' => Post::whereDate('created_at', today())->count(),
            'totalRevenue' => (int) DB::table('transactions')->sum('amount'),
            'pendingReports' => (int) DB::table('reports')->where('status', 'pending')->count(),
            'bannedUsers' => (int) User::where('is_banned', true)->count(),
        ];

        $usersQuery = User::withCount(['posts', 'followers', 'following'])
            ->orderByDesc('created_at');

        if ($search) {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('username', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $users = $usersQuery->limit(50)->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'email' => $u->email,
            'avatar' => $u->avatar,
            'is_verified' => $u->is_verified,
            'is_vip' => $u->is_vip,
            'is_creator' => $u->is_creator,
            'is_admin' => $u->is_admin,
            'is_banned' => (bool) $u->is_banned,
            'posts_count' => $u->posts_count,
            'followers_count' => $u->followers_count,
            'following_count' => $u->following_count,
            'created_at' => $u->created_at->format('d.m.Y H:i'),
            'email_verified' => (bool) $u->email_verified_at,
        ]);

        $posts = Post::with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'image' => $p->image,
                'caption' => $p->caption,
                'likes_count' => $p->likes_count,
                'comments_count' => $p->comments_count,
                'is_locked' => $p->is_locked,
                'user_name' => $p->user->name,
                'user_id' => $p->user->id,
                'created_at' => $p->created_at->format('d.m.Y H:i'),
            ]);

        $reports = DB::table('reports')
            ->join('users as reporter', 'reports.reporter_id', '=', 'reporter.id')
            ->select([
                'reports.*',
                'reporter.name as reporter_name',
                'reporter.username as reporter_username',
            ])
            ->orderByDesc('reports.created_at')
            ->limit(50)
            ->get()
            ->map(function ($r) {
                $target = null;
                if ($r->reportable_type === User::class) {
                    $u = User::find($r->reportable_id);
                    $target = $u ? ['type' => 'user', 'name' => $u->name, 'username' => $u->username] : null;
                } elseif ($r->reportable_type === Post::class) {
                    $p = Post::with('user')->find($r->reportable_id);
                    $target = $p ? ['type' => 'post', 'name' => $p->user->name, 'caption' => \Str::limit($p->caption, 40)] : null;
                }
                return [
                    'id' => $r->id,
                    'reason' => $r->reason,
                    'notes' => $r->notes,
                    'status' => $r->status,
                    'reporter_name' => $r->reporter_name,
                    'reporter_username' => $r->reporter_username,
                    'target' => $target,
                    'created_at' => \Carbon\Carbon::parse($r->created_at)->format('d.m.Y H:i'),
                ];
            });

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'users' => $users,
            'posts' => $posts,
            'reports' => $reports,
            'search' => $search,
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'is_verified' => ['sometimes', 'boolean'],
            'is_vip' => ['sometimes', 'boolean'],
            'is_creator' => ['sometimes', 'boolean'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['is_verified'])) $user->is_verified = $validated['is_verified'];
        if (isset($validated['is_vip'])) $user->is_vip = $validated['is_vip'];
        if (isset($validated['is_creator'])) $user->is_creator = $validated['is_creator'];
        if (isset($validated['is_admin'])) $user->is_admin = $validated['is_admin'];
        $user->save();

        return back()->with('success', "Uživatel {$user->name} byl aktualizován.");
    }

    public function deleteUser(User $user)
    {
        if ($user->is_admin) {
            return back()->with('error', 'Nelze smazat admina.');
        }

        // Delete media files from correct disk (public or S3/R2)
        $disk = config('filesystems.default');
        $deleteFile = function (string $url) use ($disk) {
            if (!$url) return;
            try {
                if (str_starts_with($url, '/storage/')) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $url));
                } elseif (str_starts_with($url, 'http')) {
                    $key = parse_url($url, PHP_URL_PATH);
                    if ($key) Storage::disk($disk)->delete(ltrim($key, '/'));
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to delete file: ' . $e->getMessage());
            }
        };

        if ($user->avatar && !str_starts_with($user->avatar, '/images/')) {
            $deleteFile($user->avatar);
        }
        if ($user->cover_image && !str_starts_with($user->cover_image, '/images/')) {
            $deleteFile($user->cover_image);
        }
        $user->posts()->each(function ($post) use ($deleteFile) {
            $deleteFile($post->image);
            $deleteFile($post->thumbnail);
        });

        $user->posts()->forceDelete();
        $user->likes()->delete();
        $user->comments()->delete();
        $user->bookmarks()->delete();
        $user->sentMessages()->delete();
        $user->receivedMessages()->delete();
        DB::table('follows')->where('follower_id', $user->id)->orWhere('following_id', $user->id)->delete();
        $user->forceDelete();

        return back()->with('success', 'Uživatel byl smazán.');
    }

    public function banUser(User $user)
    {
        if ($user->is_admin) {
            return back()->with('error', 'Nelze banovat admina.');
        }

        $user->is_banned = !$user->is_banned;
        $user->save();

        $action = $user->is_banned ? 'zabanován' : 'odbanován';
        return back()->with('success', "Uživatel {$user->name} byl {$action}.");
    }

    public function resolveReport(int $id)
    {
        DB::table('reports')->where('id', $id)->update([
            'status' => 'resolved',
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Report byl vyřešen.');
    }

    public function dismissReport(int $id)
    {
        DB::table('reports')->where('id', $id)->update([
            'status' => 'dismissed',
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Report byl zamítnut.');
    }

    public function deletePost(Post $post)
    {
        $post->likes()->delete();
        $post->comments()->delete();
        $post->bookmarks()->delete();
        $post->delete();

        return back()->with('success', 'Příspěvek byl smazán.');
    }
}
