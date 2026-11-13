<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Only explicitly assembled DTO arrays enter this resource, never raw models.
        return $this->resource;
    }
}
