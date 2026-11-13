<?php

namespace App\Services\Mobile;

use App\Http\Resources\Api\MobileResource;
use Illuminate\Http\JsonResponse;

class Reply
{
    public static function data(array $value, int $status = 200): JsonResponse
    {
        return (new MobileResource($value))->response()->setStatusCode($status)->header('Cache-Control', 'private, no-store');
    }

    public static function page($paginator, callable $serialize): JsonResponse
    {
        PublicIds::primeGraph($paginator->getCollection());

        return response()->json(['data' => $paginator->getCollection()->map($serialize)->values(), 'next_cursor' => $paginator->nextCursor()?->encode(), 'has_more' => $paginator->hasMorePages(), 'server_time' => now()->utc()->format('Y-m-d\TH:i:s\Z')])->header('Cache-Control', 'private, no-store');
    }
}
