<?php

namespace App\Database;

use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent builder for DB_AppHub legacy/PascalCase tables.
 *
 * Application code may keep using Laravel-style names such as item_code
 * and uom_code while SQL Server receives the real column names ItemCode/UOM.
 */
class AppHubEloquentBuilder extends Builder
{
    private function mapColumn(mixed $column): mixed
    {
        if (! is_string($column) || $column === '*') {
            return $column;
        }

        if (str_contains($column, '.') || str_contains($column, ' ')) {
            return $column;
        }

        if (method_exists($this->model, 'appHubColumn')) {
            return $this->model->appHubColumn($column);
        }

        return $column;
    }

    private function mapColumns(array $columns): array
    {
        return array_map(fn ($column) => $this->mapColumn($column), $columns);
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_array($column)) {
            $mapped = [];
            foreach ($column as $key => $item) {
                $mapped[$this->mapColumn($key)] = $item;
            }
            return parent::where($mapped, $operator, $value, $boolean);
        }

        return parent::where($this->mapColumn($column), $operator, $value, $boolean);
    }

    public function orWhere($column, $operator = null, $value = null)
    {
        if (is_array($column)) {
            $mapped = [];
            foreach ($column as $key => $item) {
                $mapped[$this->mapColumn($key)] = $item;
            }
            return parent::orWhere($mapped, $operator, $value);
        }

        return parent::orWhere($this->mapColumn($column), $operator, $value);
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        return parent::whereIn($this->mapColumn($column), $values, $boolean, $not);
    }

    public function orderBy($column, $direction = 'asc')
    {
        return parent::orderBy($this->mapColumn($column), $direction);
    }

    public function get($columns = ['*'])
    {
        return parent::get($this->mapColumns(is_array($columns) ? $columns : func_get_args()));
    }

    public function pluck($column, $key = null)
    {
        return parent::pluck($this->mapColumn($column), $key === null ? null : $this->mapColumn($key));
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if (method_exists($this->model, 'appHubWritePayload')) {
            $values = array_values(array_filter(array_map(
                fn (array $row) => $this->model->appHubWritePayload($row),
                $values
            ), fn (array $row) => $row !== []));
        }

        $uniqueBy = is_array($uniqueBy)
            ? $this->mapColumns($uniqueBy)
            : $this->mapColumn($uniqueBy);

        if (is_array($update)) {
            $update = $this->mapColumns($update);
        }

        return parent::upsert($values, $uniqueBy, $update);
    }
}
