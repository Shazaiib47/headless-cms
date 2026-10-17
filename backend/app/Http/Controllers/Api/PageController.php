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

class PageController extends Controller
{
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
        $page = Page::create($request->validated());

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
        $page->update($request->validated());

        return new PageResource($page);
    }

    public function destroy(Page $page): Response
    {
        $page->delete();

        return response()->noContent();
    }
}
