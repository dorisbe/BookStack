<?php

namespace BookStack\Entities\Queries;

use BookStack\Activity\Models\View;
use BookStack\Entities\EntityProvider;
use BookStack\Entities\Models\BookChild;
use BookStack\Entities\Models\Entity;
use BookStack\Permissions\PermissionApplicator;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QueryPopular
{
    public function __construct(
        protected PermissionApplicator $permissions,
        protected EntityProvider $entityProvider,
    ) {
    }

    public function run(int $count, int $page, array $filterModels = null)
    {
        $query = $this->permissions
            ->restrictEntityRelationQuery(View::query(), 'views', 'viewable_id', 'viewable_type')
            ->select('*', 'viewable_id', 'viewable_type', DB::raw('SUM(views) as view_count'))
            ->groupBy('viewable_id', 'viewable_type')
            ->orderBy('view_count', 'desc');

        if ($filterModels) {
            $query->whereIn('viewable_type', $this->entityProvider->getMorphClasses($filterModels));
        }

        $entities = $query->with('viewable')
            ->skip($count * ($page - 1))
            ->take($count)
            ->get()
            ->pluck('viewable')
            ->filter();

        $this->loadBooksForChildren($entities);

        return $entities;
    }

    protected function loadBooksForChildren(Collection $entities): void
    {
        $bookChildren = $entities->filter(fn(Entity $entity) => $entity instanceof BookChild);
        $eloquent = (new \Illuminate\Database\Eloquent\Collection($bookChildren));
        $eloquent->load(['book' => function (BelongsTo $query) {
            $query->scopes('visible');
        }]);
    }
}
