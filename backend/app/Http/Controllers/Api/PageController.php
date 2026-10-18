<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPageRequest;
use App\Http\Requests\StorePageRequest;
use App\Http\Requests\UpdatePageRequest;
use App\Http\Resources\PageResource;
use App\Http\Resources\PageSummaryResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PageController extends Controller implements HasMiddleware
{
    /**
     * Authorisation is enforced by PagePolicy before any validation or
     * controller logic runs, so a forbidden request never reaches them.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Page::class, only: ['index']),
            new Middleware('can:view,page', only: ['show']),
            new Middleware('can:create,'.Page::class, only: ['store']),
            new Middleware('can:update,page', only: ['update']),
            new Middleware('can:delete,page', only: ['destroy']),
        ];
    }

    public function index(IndexPageRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = Page::query();

        if (! empty($filters['search'])) {
            // Escape LIKE wildcards so user input is matched literally.
            $term = '%'.addcslashes($filters['search'], '\\%_').'%';

            $query->where(fn ($q) => $q
                ->where('title', 'like', $term)
                ->orWhere('slug', 'like', $term));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Safe: the sort value was validated against a whitelist.
        $sort = $filters['sort'] ?? '-updated_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $pages = $query
            ->orderBy(ltrim($sort, '-'), $direction)
            ->orderBy('id') // stable ordering across pages
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return PageSummaryResource::collection($pages);
    }

    public function store(StorePageRequest $request): JsonResponse
    {
        $page = new Page($request->validated());

        // Audit columns are not mass assignable; the server sets them.
        $page->created_by = $request->user()->id;
        $page->updated_by = $request->user()->id;
        $page->save();

        return (new PageResource($page))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Page $page): PageResource
    {
        return new PageResource($page);
    }

    public function update(UpdatePageRequest $request, Page $page): PageResource
    {
        $data = $request->validated();
        $expected = Arr::pull($data, 'updated_at');

        $updated = DB::transaction(function () use ($request, $page, $data, $expected) {
            // Lock the row so two simultaneous saves cannot both pass the check.
            $current = Page::query()->lockForUpdate()->findOrFail($page->id);

            if ($expected !== null && ! Carbon::parse($expected)->equalTo($current->updated_at)) {
                abort(409, 'This page was changed by someone else after you opened it. Reload to see their changes.');
            }

            $current->fill($data);
            $current->updated_by = $request->user()->id;
            $current->save();

            return $current;
        });

        return new PageResource($updated);
    }

    public function destroy(Page $page): Response
    {
        $page->delete();

        return response()->noContent();
    }
}
