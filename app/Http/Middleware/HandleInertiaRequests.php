<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? (function () use ($request) {
                    $user = $request->user()->loadCount(['followers', 'following', 'posts']);
                    return [
                        'id'                 => $user->id,
                        'name'               => $user->name,
                        'username'           => $user->username,
                        'email'              => $user->email,
                        'avatar'             => $user->avatar,
                        'bio'                => $user->bio,
                        'is_verified'        => $user->is_verified,
                        'is_vip'             => $user->is_vip,
                        'is_creator'         => $user->is_creator,
                        'subscription_price' => $user->subscription_price,
                        'is_admin'           => $user->is_admin,
                        'cover_image'           => $user->cover_image ?: '/images/default-cover.svg',
                        'balance'               => (float) $user->balance,
                        'onboarding_completed'  => (bool) $user->onboarding_completed,
                        'followers_count'       => $user->followers_count,
                        'following_count'    => $user->following_count,
                        'posts_count'        => $user->posts_count,
                    ];
                })() : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
