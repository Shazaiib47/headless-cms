<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PagePublicationRequest;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Services\PagePublisher;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PagePublicationController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PagePublisher $publisher) {}

    /** Authorisation runs before validation or any controller logic. */
    public static function middleware(): array
    {
        return [new Middleware('can:publish,page')];
    }

    public function publish(PagePublicationRequest $request, Page $page): PageResource
    {
        return new PageResource(
            $this->publisher->publish($page, $request->user(), $request->validated('updated_at')),
        );
    }

    public function unpublish(PagePublicationRequest $request, Page $page): PageResource
    {
        return new PageResource(
            $this->publisher->unpublish($page, $request->user(), $request->validated('updated_at')),
        );
    }
}
