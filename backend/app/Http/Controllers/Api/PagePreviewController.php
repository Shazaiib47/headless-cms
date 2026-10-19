<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicPageResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Shows the saved content of any page, in the public format, to signed-in users. */
class PagePreviewController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:view,page')];
    }

    public function __invoke(Request $request, Page $page): JsonResponse
    {
        $data = (new PublicPageResource($page))->resolve($request);
        // Lets the preview screen label drafts. This is not part of the public shape.
        $data['status'] = $page->status->value;

        return response()
            ->json(['data' => $data])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
