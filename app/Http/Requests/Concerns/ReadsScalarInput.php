<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\ValidationException;

trait ReadsScalarInput
{
    protected function scalarInput(string $key, mixed $default = null): mixed
    {
        return $this->scalarValue($this->input($key, $default), $key);
    }

    protected function scalarValue(mixed $value, string $key): mixed
    {
        if ($value !== null && ! is_scalar($value)) {
            throw ValidationException::withMessages([
                $key => 'This field must contain a single text or numeric value.',
            ])->errorBag($this->errorBag)->redirectTo($this->getRedirectUrl());
        }

        return $value;
    }
}
