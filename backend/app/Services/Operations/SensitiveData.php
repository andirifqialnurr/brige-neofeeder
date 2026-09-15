<?php

namespace App\Services\Operations;

use Illuminate\Contracts\Support\Arrayable;

class SensitiveData
{
    public function present(mixed $value, bool $reveal = false, string $field = ''): mixed
    {
        $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $field));
        if ($value !== null && $value !== '' && preg_match('/(^|_)(password|token|secret|credential)(_|$)/', $key)) {
            return '[disembunyikan]';
        }
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->present($item, $reveal, (string) $key);
            }

            return $result;
        }
        if ($value === null || $value === '') {
            return $value;
        }
        if (! $reveal && preg_match('/(^|_)(nik|npwp|phone|telepon|handphone|no_hp|nomor_hp|mobile)(_|$)/', $key)) {
            $text = (string) $value;

            return strlen($text) <= 4 ? '****' : str_repeat('*', strlen($text) - 4).substr($text, -4);
        }
        if (! $reveal && is_string($value) && in_array($key, ['message', 'error_desc'], true)) {
            return preg_replace_callback('/(?<!\d)\d{10,20}(?!\d)/', fn ($match) => str_repeat('*', strlen($match[0]) - 4).substr($match[0], -4), $value);
        }

        return $value;
    }
}
