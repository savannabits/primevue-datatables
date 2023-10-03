<?php

namespace Savannabits\PrimevueDatatables;

use Illuminate\Database\Eloquent\Builder;

class Filter
{
    const STARTS_WITH = 'startsWith';
    const CONTAINS = 'contains';
    const NOT_CONTAINS = 'notContains';
    const ENDS_WITH = 'endsWith';
    const EQUALS = 'equals';
    const NOT_EQUALS = 'notEquals';
    const IN = 'in';
    const LESS_THAN = 'lt';
    const LESS_THAN_OR_EQUAL_TO = 'lte';
    const GREATER_THAN = 'gt';
    const GREATER_THAN_OR_EQUAL_TO = 'gte';
    const BETWEEN = 'between';
    const DATE_IS = 'dateIs';
    const DATE_IS_NOT = 'dateIsNot';
    const DATE_BEFORE = 'dateBefore';
    const DATE_AFTER = 'dateAfter';

    private $likeOperator = 'LIKE';

    public function __construct(public string $field, public $value = null, public ?string $matchMode = self::CONTAINS)
    {
        $this->likeOperator = \DB::connection()->getPDO()->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    public function buildWhere(Builder &$q, ?bool $or = false)
    {
        $searchParts = explode(".", $this->field);
        if (count($searchParts) <= 4) {
            switch (count($searchParts)) {
                case 1:
                    $this->applyWhere($q, $searchParts[0], $or);
                    break;
                case 2:
                    if ($or) {
                        $q->orWhereHas($searchParts[0], function ($q1) use ($searchParts) {
                            $this->applyWhere($q1, $searchParts[1]);
                        });
                    } else {
                        $q->whereHas($searchParts[0], function ($q1) use ($searchParts) {
                            $this->applyWhere($q1, $searchParts[1]);
                        });
                    }

                    break;
                case  3:
                    if ($or) {
                        $q->orWhereHas($searchParts[0], function ($q1) use ($searchParts) {
                            $q1->whereHas($searchParts[1], function ($q2) use ($searchParts) {
                                $this->applyWhere($q2, $searchParts[2]);
                            });
                        });
                    } else {
                        $q->whereHas($searchParts[0], function ($q1) use ($searchParts) {
                            $q1->whereHas($searchParts[1], function ($q2) use ($searchParts) {
                                $this->applyWhere($q2, $searchParts[2]);
                            });
                        });
                    }
                    break;
                case  4:
                    if ($or) {
                        $q->orWhereHas($searchParts[0], function ($q1) use ($searchParts) {
                            $q1->whereHas($searchParts[1], function ($q2) use ($searchParts) {
                                $q2->whereHas($searchParts[2], function ($q3) use ($searchParts) {
                                    $this->applyWhere($q3, $searchParts[3]);
                                });
                            });
                        });
                    } else {
                        $q->whereHas($searchParts[0], function ($q1) use ($searchParts) {
                            $q1->whereHas($searchParts[1], function ($q2) use ($searchParts) {
                                $q2->whereHas($searchParts[2], function ($q3) use ($searchParts) {
                                    $this->applyWhere($q3, $searchParts[3]);
                                });
                            });
                        });
                    }
                    break;
                default:
                    break;
            }
        }
    }
    private function applyWhere(Builder &$q, string $field, ?bool $or = false)
    {
        $jsonField = $this->isJsonFieldPath($field);

        switch ($this->matchMode) {
            case self::STARTS_WITH:
                if ($or) {
                    if(!$jsonField) {
                        $q->orWhere($field, $this->likeOperator, $this->value . "%");
                    }
                    else {
                        $q->orWhereRaw('LOWER('.$jsonField[0].'->>"$.'.$jsonField[1].'") '.$this->likeOperator.' ?', mb_strtolower($this->value . "%"));
                    }
                } else {
                    if(!$jsonField) {
                        $q->where($field, $this->likeOperator, $this->value . "%");
                    }
                    else {
                        $q->whereRaw('LOWER('.$jsonField[0].'->>"$.'.$jsonField[1].'") '.$this->likeOperator.' ?', mb_strtolower($this->value . "%"));
                    }
                }
                break;
            case self::NOT_CONTAINS:
                if ($or) {
                    if(!$jsonField) {
                        $q->orWhere($field, "NOT " . $this->likeOperator, "%" . $this->value . "%");
                    }
                    else {
                        $q->orWhereRaw('LOWER('.$jsonField[0].'->>"$.'.$jsonField[1].'") NOT '.$this->likeOperator.' ?', mb_strtolower("%" . $this->value . "%"));
                    }
                } else {
                    if(!$jsonField) {
                        $q->where($field, "NOT " . $this->likeOperator, "%" . $this->value . "%");
                    }
                    else {
                        $q->whereRaw('LOWER('.$jsonField[0].'->>"$.'.$jsonField[1].'") NOT '.$this->likeOperator.' ?', mb_strtolower("%" . $this->value . "%"));
                    }
                }
                break;
            case self::ENDS_WITH:
                if ($or) {
                    if(!$jsonField) {
                        $q->orWhere($field, $this->likeOperator, "%" . $this->value);
                    }
                    else {
                        $q->orWhereRaw('LOWER('.$jsonField[0].'->>"$.'.$jsonField[1].'") '.$this->likeOperator.' ?', mb_strtolower("%" . $this->value . ""));
                    }
                } else {
                    if(!$jsonField) {
                        $q->where($field, $this->likeOperator, "%" . $this->value);
                    }
                    else {
                        $q->whereRaw('LOWER('.$jsonField[0].'->>"$.'.$jsonField[1].'") '.$this->likeOperator.' ?', mb_strtolower("%" . $this->value . ""));
                    }
                }
                break;
            case self::EQUALS:
                if ($or) {
                    if(!$jsonField) {
                        $q->orWhere($field, "=", $this->value);
                    }
                    else {
                        $q->orWhereRaw('LOWER('.$jsonField[0].'->>"$.'.$jsonField[1].'") = ?', mb_strtolower($this->value));
                    }
                } else {
                    if(!$jsonField) {
                        $q->where($field, "=", $this->value);
                    }
                    else {
                        $q->whereRaw('LOWER('.$jsonField[0].'->>"$.'.$jsonField[1].'") = ?', mb_strtolower($this->value));
                    }
                }
                break;
            case self::NOT_EQUALS:
                if ($or) {
                    if(!$jsonField) {
                        $q->orWhere($field, "!=", $this->value);
                    }
                    else {
                        $q->orWhereRaw('LOWER('.$jsonField[0].'->>"$.'.$jsonField[1].'") != ?', mb_strtolower($this->value));
                    }
                } else {
                    if(!$jsonField) {
                        $q->where($field, "!=", $this->value);
                    }
                    else {
                        $q->whereRaw('LOWER('.$jsonField[0].'->>"$.'.$jsonField[1].'") != ?', mb_strtolower($this->value));
                    }
                }
                break;
            case self::IN:
                if ($or) {
                    $q->orWhereIn($field, $this->value);
                } else {
                    $q->whereIn($field, $this->value);
                }
                break;
            case self::LESS_THAN:
                if ($or) {
                    $q->orWhere($field, "<", $this->value);
                } else {
                    $q->where($field, "<", $this->value);
                }
                break;
            case self::LESS_THAN_OR_EQUAL_TO:
                if ($or) {
                    $q->orWhere($field, "<=", $this->value);
                } else {
                    $q->where($field, "<=", $this->value);
                }
                break;
            case self::GREATER_THAN:
                if ($or) {
                    $q->orWhere($field, ">", $this->value);
                } else {
                    $q->where($field, ">", $this->value);
                }
                break;
            case self::GREATER_THAN_OR_EQUAL_TO:
                if ($or) {
                    $q->orWhere($field, ">=", $this->value);
                } else {
                    $q->where($field, ">=", $this->value);
                }
                break;
            case self::BETWEEN:
                //TODO: implement
                break;

            case self::DATE_IS:
                if ($or) {
                    $q->orWhereDate($field, "=", $this->value);
                } else {
                    $q->whereDate($field, "=", $this->value);
                }
                break;

            case self::DATE_IS_NOT:
                if ($or) {
                    $q->orWhereDate($field, "!=", $this->value);
                } else {
                    $q->whereDate($field, "!=", $this->value);
                }
                break;

            case self::DATE_BEFORE:
                if ($or) {
                    $q->orWhereDate($field, "<=", $this->value);
                } else {
                    $q->whereDate($field, "<=", $this->value);
                }
                break;
            case self::DATE_AFTER:
                if ($or) {
                    $q->orWhereDate($field, ">", $this->value);
                } else {
                    $q->whereDate($field, ">", $this->value);
                }
                break;

            case self::CONTAINS:
            default:

                if ($or) {
                    if(!$jsonField) {
                        $q->orWhere($field, $this->likeOperator, "%" . $this->value . "%");
                    }
                    else {
                        $q->orWhereRaw('LOWER('.$jsonField[0].'->>"$.'.$jsonField[1].'") '.$this->likeOperator.' ?', mb_strtolower("%" . $this->value . "%"));
                    }
                } else {
                    if(!$jsonField) {
                        $q->where($field, $this->likeOperator, "%" . $this->value . "%");
                    }
                    else {
                        $q->whereRaw('LOWER('.$jsonField[0].'->>"$.'.$jsonField[1].'") '.$this->likeOperator.' ?', mb_strtolower("%" . $this->value . "%"));
                    }
                }
                break;
        }
    }

    /**
     * Check if a field string represents a JSON field path.
     *
     * @param string $field The field string to check.
     * @return array|false Returns an array of path segments if the field is a JSON field path,
     *                    or false otherwise.
     */
    private function isJsonFieldPath(string $field): false|array
    {
        if(str_contains($field, "->")) {
            return explode("->", $field);
        }
        return false;
    }
}
