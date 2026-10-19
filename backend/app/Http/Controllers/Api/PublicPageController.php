<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicPageResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPageController extends Controller
{
    public function __invoke(Request $request, string $slug): JsonResponse
    {
        // Drafts, unpublished pages and unknown slugs are indistinguishable.
        $page = Page::published()->where('slug', $slug)->first();
        abort_if($page === null, 404, 'Page not found.');

        $response = (new PublicPageResource($page))->response($request);

        // Caches may store the response but must revalidate every time, so an
        // unpublished page disappears immediately. The ETag makes that cheap.
        $response->headers->set('Cache-Control', 'public, no-cache');
        $response->setEtag(sha1((string) $response->getContent()));
        $response->isNotModified($request); // turns the response into a 304 on a match

        return $response;
    }
}
