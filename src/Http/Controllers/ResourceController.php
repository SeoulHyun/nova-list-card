<?php

namespace NovaListCard\Http\Controllers;

use Laravel\Nova\Nova;
use Illuminate\Routing\Controller;
use Laravel\Nova\Http\Requests\NovaRequest;

class ResourceController extends Controller
{
    private function title($card, $resouce)
    {
        if ($card->title) return call_user_func($card->title, $resouce);

        return $resouce->title();
    }

    private function subtitle($card, $resource)
    {
        if ($card->subtitle) return call_user_func($card->subtitle, $resource);

        return $resource->subtitle();
    }

    public function index($key, $aggregate = 'count', $relationship = null, $column = null)
    {
        $novaRequest = resolve(NovaRequest::class);

        $card = collect(Nova::$cards)
            ->filter(function ($card) use ($key) {
                return $card->uriKey() == $key;
            })->first();

        $resource = $card->resource;

        $query = $resource::newModel();

        if ($relationship) {
            $query = $this->applyAggregate($query, $aggregate, $relationship, $column);
        }

        $query = $this->applyOrdering($query);

        if (request()->has('limit')) {
            $query->take(request('limit'));
        }

        if ($card->query) {
            $query = call_user_func($card->query, $novaRequest, $query);
        } else {
            $query = $resource::indexQuery($novaRequest, $query);
        }

        return $query->get()
            ->mapInto($resource)
            ->filter(function ($resource) use ($novaRequest) {
                return $resource->authorizedToView($novaRequest);
            })->map(function ($resource) use ($novaRequest, $aggregate, $relationship, $card) {
                return [
                    'resource' => $resource->resource->toArray(),
                    'resourceName' => $resource::uriKey(),
                    'resourceTitle' => $resource::label(),
                    'title' => $this->title($card, $resource),
                    'subTitle' => $this->subtitle($card, $resource),
                    'resourceId' => $resource->getKey(),
                    'url' => url(Nova::path().'/resources/'.$resource::uriKey().'/'.$resource->getKey()),
                    'avatar' => $resource->resolveAvatarUrl($novaRequest),
                    'aggregate' => data_get($resource, "{$relationship}_{$aggregate}"),
                ];
            });
    }

    public function applyAggregate($query, $aggregate, $relationship, $column = null)
    {
        if ('count' == $aggregate) {
            return $query->withCount($relationship);
        }

        if ('sum' == $aggregate) {
            return $query->withSum($relationship, $column);
        }
    }

    public function applyOrdering($query)
    {
        $direction = (request()->input('direction')) ? request('direction') : 'asc';

        return $query->orderBy(request('order_by'), $direction);
    }
}
