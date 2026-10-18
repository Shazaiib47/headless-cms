<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller implements HasMiddleware
{
    /** Authorisation runs before validation or any controller logic. */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.User::class, only: ['index']),
            new Middleware('can:view,user', only: ['show']),
            new Middleware('can:create,'.User::class, only: ['store']),
            new Middleware('can:update,user', only: ['update']),
            new Middleware('can:delete,user', only: ['destroy']),
        ];
    }

    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = User::query();

        if (! empty($filters['search'])) {
            // Escape LIKE wildcards so user input is matched literally.
            $term = '%'.addcslashes($filters['search'], '\\%_').'%';

            $query->where(fn ($q) => $q
                ->where('name', 'like', $term)
                ->orWhere('email', 'like', $term));
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        // Safe: the sort value was validated against a whitelist.
        $sort = $filters['sort'] ?? 'name';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $users = $query
            ->orderBy(ltrim($sort, '-'), $direction)
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = new User(Arr::only($data, ['name', 'email', 'password']));
        // role is not mass assignable, so it is set explicitly.
        $user->role = Role::from($data['role']);
        $user->save();

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();
        $isSelf = $user->is($request->user());

        if (isset($data['role']) && Role::from($data['role']) !== $user->role) {
            // Prevents an admin from locking everyone out of administration.
            if ($isSelf) {
                throw ValidationException::withMessages([
                    'role' => 'You cannot change your own role.',
                ]);
            }

            $user->role = Role::from($data['role']);
        }

        $user->fill(Arr::only($data, ['name', 'email', 'password']));
        $user->save();

        // Resetting someone's password signs them out everywhere.
        if (isset($data['password']) && ! $isSelf) {
            $this->endSessionsFor($user);
        }

        return new UserResource($user);
    }

    public function destroy(Request $request, User $user): Response
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        $user->delete();
        $this->endSessionsFor($user);

        return response()->noContent();
    }

    /** Only applies when sessions are stored in the database. */
    private function endSessionsFor(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }
    }
}
