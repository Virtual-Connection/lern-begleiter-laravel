<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SourceType;
use App\Models\Source;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<ValidationRule|Enum|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(SourceType::class)],
            'path' => ['required', 'string', 'max:1024'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var string $rawPath */
            $rawPath = $this->input('path');
            $this->validateSourcePath($validator, $rawPath);
        });
    }

    /**
     * Canonical absolute path after successful validation (realpath).
     */
    public function canonicalPath(): string
    {
        $resolved = realpath((string) $this->input('path'));

        if ($resolved === false) {
            throw new \RuntimeException('Path could not be resolved after validation.');
        }

        return $resolved;
    }

    private function validateSourcePath(Validator $validator, string $rawPath): void
    {
        $trimmed = trim($rawPath);

        if ($trimmed === '') {
            $validator->errors()->add('path', 'Der Pfad darf nicht leer sein.');

            return;
        }

        if ($this->containsTraversalSegment($trimmed)) {
            $validator->errors()->add('path', 'Pfad-Traversal (`..`) ist nicht erlaubt.');

            return;
        }

        if (! $this->isAbsolutePath($trimmed)) {
            $validator->errors()->add('path', 'Der Pfad muss absolut sein.');

            return;
        }

        $resolved = realpath($trimmed);

        if ($resolved === false || ! is_dir($resolved)) {
            $validator->errors()->add('path', 'Der Pfad existiert nicht oder ist kein Ordner.');

            return;
        }

        if (! is_readable($resolved)) {
            $validator->errors()->add('path', 'Der Pfad ist nicht lesbar.');

            return;
        }

        $duplicate = Source::query()
            ->where('path', $resolved)
            ->exists();

        if ($duplicate) {
            $validator->errors()->add('path', 'Dieser Pfad ist bereits als Quelle angelegt.');
        }
    }

    private function containsTraversalSegment(string $path): bool
    {
        return (bool) preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $path);
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        // Windows: C:\... or C:/...
        return (bool) preg_match('#^[A-Za-z]:[/\\\\]#', $path);
    }
}
