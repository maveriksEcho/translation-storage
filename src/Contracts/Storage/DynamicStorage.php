<?php

namespace Pervozdanniy\TranslationStorage\Contracts\Storage;

interface DynamicStorage
{
    /**
     * @param string $index
     * @param array $fields Associative array of key => value pairs
     * @return bool
     */
    public function set(string $index, array $fields): bool;

    /**
     * @param string $id
     * @param string $lang
     * @param string|null $index
     * @return array|null
     */
    public function find(string $index, string $id, string $lang): ?array;

    /**
     * @param array|null $langs
     * @param string|null $index
     * @return iterable
     */
    public function fetch(array $langs = null, string $index = null): iterable;

    /**
     * @param array|null $langs
     * @param string|null $index
     */
    public function clear(array $langs = null, string $index = null): void;
}
