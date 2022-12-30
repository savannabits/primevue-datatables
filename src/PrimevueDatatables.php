<?php

namespace Savannabits\PrimevueDatatables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use ReflectionClass;
use Throwable;
class PrimevueDatatables
{
    /**
     * @var Builder|\Illuminate\Database\Query\Builder
     */
    private \Illuminate\Database\Query\Builder|Builder $query;
    private ?int $currentPage;
    private $sort;
    private $sortDirection;
    private array $_searchableColumns;
    /**
     * @var int
     */
    private $perPage;
    private array $filters;
    private array $_dtParams;

    public function __construct()
    {
        $this->_dtParams = json_decode(request()->get('dt_params', "[]"), true);
        $this->_searchableColumns
        = json_decode(request()->get('searchable_columns',"[]"), true);
    }

    public function dtParams(array $params): static
    {
        $this->_dtParams = $params;
        return $this;
    }

    public function searchableColumns(array $searchable_columns): static
    {
        $this->_searchableColumns = $searchable_columns;
        return $this;
    }

    public function query(Builder $query): static {
        $this->query = $query;
        return $this;
    }
    public static function of(Builder $query): static
    {
        // I'm pretty sure passing $query as an argument doesn't actually do anything.
        $instance = new self($query);
        return $instance->query($query);
    }

    public function make(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $this->currentPage = collect($this->_dtParams)->get("page", 0) + 1;
        $this->perPage = collect($this->_dtParams)->get("rows", 10);

        $filters = collect($this->_dtParams)->get("filters", []);
        $this->sort = collect($this->_dtParams)->get('sortField');
        $this->sortDirection = collect($this->_dtParams)->get('sortOrder') == 1 ? 'asc' : 'desc';
        $global = collect(collect($filters)->get("global") ?? null);
        $localFilters = collect($filters)->except("global");

        $columnNames = $this->_searchableColumns;
        $query = $this->query
            ->where(function (Builder $q) use ($columnNames, $global) {
                // Global Search
                if (count($columnNames) && $global && collect($global)->get("value")) {
                    $firstColumn = collect($columnNames)->get(0);
                    $otherColumns = collect($columnNames)->except(0);
                    $firstFilter = new Filter($firstColumn, collect($global)->get("value"), collect($global)->get("matchMode"));
                    $this->applyFilter($firstFilter, $q);
                    foreach ($otherColumns as $column) {
                        $colFilter = new Filter($column, collect($global)->get("value"), collect($global)->get("matchMode"));
                        $this->applyFilter($colFilter, $q, true);
                    }
                }
            })->where(function (Builder $q) use ($localFilters) {
                // Local filters
                foreach ($localFilters as $field => $filter) {
                    if(isset($filter['constraints'])){
                        foreach($filter['constraints'] as $const){
                            if ($const["value"]) {
                                $instance = new Filter($field, $const["value"], $const["matchMode"]);
                                $this->applyFilter($instance, $q, $filter['operator'] == 'or' ? true : false);
                            }
                        }
                    } else {
                        if (collect($filter)->get("value") !== null) {
                            $instance = new Filter($field, collect($filter)->get("value"), collect($filter)->get("matchMode"));
                            $this->applyFilter($instance, $q);
                        }
                    }
                }
            });
        $with = collect([]);
        foreach ($columnNames as $columnName) {
            $exploded = explode(".", $columnName);
            if (sizeof($exploded) == 2) {
                $with->push($exploded[0]);
            } elseif (sizeof($exploded) == 3) {
                $with->push($exploded[0] . "." . $exploded[1]);
            }
        }
        $query->with($with->toArray());
        $this->applySort($query);
        return $query->paginate($this->perPage, page: $this->currentPage);
    }

    private function applyFilter(Filter $filter, Builder &$q, $or = false)
    {
        // Apply Search to a depth of 3
        $filter->buildWhere($q, $or);
    }

    private function applySort(Builder &$q)
    {
        if ($this->sort != null) {
            $key = explode(".", $this->sort);
            if (sizeof($key) === 1) {
                $q->orderBy($this->sort, $this->sortDirection ?? 'asc');
            } elseif (sizeof($key) === 2) {
                $relationship = $this->getRelatedFromMethodName($key[0], get_class($q->getModel()));
                if ($relationship) {
                    $parentTable = $relationship->getParent()->getTable();
                    $relatedTable = $relationship->getRelated()->getTable();
                    if ($relationship instanceof HasOne) {
                        $parentKey = explode(".", $relationship->getQualifiedParentKeyName())[1];
                        $relatedKey = $relationship->getForeignKeyName();
                    } else {
                        $parentKey = $relationship->getForeignKeyName();
                        $relatedKey = $relationship->getOwnerKeyName();
                    }

                    $q->orderBy(
                        get_class($relationship->getRelated())::query()->select($key[1])->whereColumn("$parentTable.$parentKey", "$relatedTable.$relatedKey"),
                        $this->sortDirection ?? 'asc'
                    );
                    /*$q->join($fTable, "$ownerTable.$fKey", '=', "$fTable.$ownerKey")
                        ->orderBy($fTable.".".$key[1],$this->sortDirection ?? 'asc');*/
                    /*$q->orderBy($fKey,$this->sortDirection ?? 'asc');*/
                }
            }
        }
    }

    private function getRelatedFromMethodName(string $method_name, string $class)
    {
        try {
            $method = (new ReflectionClass($class))->getMethod($method_name);
            return $method->invoke(new $class);
        } catch (Throwable $exception) {
            return null;
        }
    }
}
