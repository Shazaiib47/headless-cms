<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexMediaRequest;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaStore;
use App\Services\MediaUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly MediaStore $store,
        private readonly MediaUsage $usage,
    ) {}

    /** Authorisation runs before validation or any controller logic. */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Media::class, only: ['index']),
            new Middleware('can:view,media', only: ['show']),
            new Middleware('can:create,'.Media::class, only: ['store']),
            // A prefix keeps this counter separate from other routes' limits.
            new Middleware('throttle:30,1,media-uploads', only: ['store']),
            new Middleware('can:update,media', only: ['update']),
            new Middleware('can:delete,media', only: ['destroy']),
        ];
    }

    public function index(IndexMediaRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = Media::query();

        if (! empty($filters['search'])) {
            // Escape LIKE wildcards so user input is matched literally.
            $term = '%'.addcslashes($filters['search'], '\\%_').'%';

            $query->where(fn ($q) => $q
                ->where('original_name', 'like', $term)
                ->orWhere('alt_text', 'like', $term));
        }

        $media = $query
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 24)
            ->withQueryString();

        return MediaResource::collection($media);
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $media = $this->store->store(
            $request->file('file'),
            $request->user(),
            $request->validated('alt_text'),
        );

        return (new MediaResource($media))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Media $media): MediaResource
    {
        return new MediaResource($media);
    }

    public function update(UpdateMediaRequest $request, Media $media): MediaResource
    {
        $media->fill($request->validated())->save();

        return new MediaResource($media);
    }

    public function destroy(Media $media): Response
    {
        $pages = $this->usage->pagesUsing($media);

        if ($pages !== []) {
            throw ValidationException::withMessages(['media' => [$this->inUseMessage($pages)]]);
        }

        $paths = array_values(array_filter([$media->path, $media->thumbnail_path]));
        $disk = Storage::disk($media->disk);

        $media->delete();
        $disk->delete($paths);

        return response()->noContent();
    }

    /** @param  list<array{id: int, title: string}>  $pages */
    private function inUseMessage(array $pages): string
    {
        $titles = implode(', ', array_map(
            fn (array $page) => '"'.$page['title'].'"',
            array_slice($pages, 0, 5),
        ));
        $more = count($pages) > 5 ? ' and others' : '';
        $count = count($pages);

        return sprintf(
            'This image is used by %d %s (%s%s). Remove it from %s before deleting it.',
            $count,
            $count === 1 ? 'page' : 'pages',
            $titles,
            $more,
            $count === 1 ? 'that page' : 'those pages',
        );
    }
}
