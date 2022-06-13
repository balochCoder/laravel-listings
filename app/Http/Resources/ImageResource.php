<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Storage;

class ImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'path' => Storage::url($this->path),
            
            $this->merge(Arr::except(parent::toArray($request), [
                'created_at', 'updated_at', 'resource_type', 'resource_id'
            ]))
        ];
    }
}
