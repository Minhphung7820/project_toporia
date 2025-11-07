<?php
namespace Framework\Data;

abstract class AbstractRepository
{
    /** Common mapping helpers for adapters (PDO/HTTP/etc.) */
    protected function mapSnakeToCamel(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $out[preg_replace_callback('/_([a-z])/', fn($m) => strtoupper($m[1]), $k)] = $v;
        }
        return $out;
    }
}
