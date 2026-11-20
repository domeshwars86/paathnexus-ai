<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class QuestionCsvImport
{
    private const MAX_ROWS = 100;

    private const BASE_HEADERS = ['prompt', 'type', 'difficulty', 'topic'];

    /** @return array{valid: bool, row_count: int, valid_count: int, rows: array, errors: array} */
    public function preview(UploadedFile $file): array
    {
        $stream = @fopen($file->getRealPath(), 'rb');
        if (! is_resource($stream)) {
            return $this->failedPreview('The CSV file could not be read.');
        }

        try {
            $header = fgetcsv($stream, null, ',', '"', '');
            if ($header === false || $header === [null]) {
                return $this->failedPreview('The CSV file is empty.');
            }

            $headers = array_map(static fn ($value): string => trim((string) $value), $header);
            if (isset($headers[0])) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
            }
            $headerErrors = $this->validateHeaders($headers);
            if ($headerErrors !== []) {
                return $this->failedPreview($headerErrors);
            }

            $optionNumbers = [];
            foreach ($headers as $name) {
                if (preg_match('/^option_([1-8])$/', $name, $matches) === 1) {
                    $optionNumbers[] = (int) $matches[1];
                }
            }
            sort($optionNumbers);

            $rows = [];
            $recordNumber = 1;
            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $recordNumber++;
                if ($values === [null] || collect($values)->every(fn ($value): bool => trim((string) $value) === '')) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    return $this->failedPreview('A CSV import can contain at most 100 non-empty question rows.');
                }

                $row = [];
                foreach ($headers as $index => $name) {
                    $row[$name] = trim((string) ($values[$index] ?? ''));
                }
                $errors = [];
                if (count($values) > count($headers) && collect(array_slice($values, count($headers)))->contains(fn ($value): bool => trim((string) $value) !== '')) {
                    $errors['row'][] = 'This row has more values than the CSV header.';
                }

                $attributes = [
                    'prompt' => $row['prompt'] ?? '',
                    'type' => $row['type'] ?? '',
                    'difficulty' => $row['difficulty'] ?? '',
                    'topic' => ($row['topic'] ?? '') === '' ? null : $row['topic'],
                ];
                $validator = Validator::make($attributes, [
                    'prompt' => ['required', 'string', 'max:5000'],
                    'type' => ['required', Rule::in(['single_choice', 'multiple_select'])],
                    'difficulty' => ['required', 'integer', 'between:1,5'],
                    'topic' => ['nullable', 'string', 'max:120'],
                ]);
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors[$field] = [...($errors[$field] ?? []), ...$messages];
                }

                $options = [];
                foreach ($optionNumbers as $number) {
                    $text = $row["option_{$number}"] ?? '';
                    $correctValue = strtolower($row["option_{$number}_correct"] ?? '');
                    if ($text === '' && $correctValue === '') {
                        continue;
                    }
                    if ($text === '') {
                        $errors["option_{$number}"][] = 'An answer marker requires option text.';
                        continue;
                    }
                    if (mb_strlen($text) > 1000) {
                        $errors["option_{$number}"][] = 'Option text may not exceed 1000 characters.';
                    }
                    $isCorrect = $this->booleanValue($correctValue);
                    if ($isCorrect === null) {
                        $errors["option_{$number}_correct"][] = 'Use true/false, yes/no, or 1/0.';
                        continue;
                    }
                    $options[] = ['text' => $text, 'is_correct' => $isCorrect];
                }
                if (count($options) < 2) {
                    $errors['options'][] = 'Add at least two complete options.';
                }
                $correctCount = collect($options)->where('is_correct', true)->count();
                if ($correctCount < 1) {
                    $errors['options'][] = 'Mark at least one correct option.';
                }
                if (($attributes['type'] ?? '') === 'single_choice' && $correctCount !== 1) {
                    $errors['options'][] = 'Single-choice questions need exactly one correct option.';
                }

                $question = [
                    'prompt' => $attributes['prompt'],
                    'topic' => $attributes['topic'],
                    'difficulty' => filter_var($attributes['difficulty'], FILTER_VALIDATE_INT) !== false ? (int) $attributes['difficulty'] : $attributes['difficulty'],
                    'type' => $attributes['type'],
                    'options' => $options,
                ];
                $rows[] = [
                    'row_number' => $recordNumber,
                    'valid' => $errors === [],
                    'question' => $question,
                    'errors' => $errors,
                ];
            }

            if ($rows === []) {
                return $this->failedPreview('Add at least one non-empty question row to the CSV.');
            }
            $validCount = collect($rows)->where('valid', true)->count();

            return [
                'valid' => $validCount === count($rows),
                'row_count' => count($rows),
                'valid_count' => $validCount,
                'rows' => $rows,
                'errors' => [],
            ];
        } finally {
            fclose($stream);
        }
    }

    private function validateHeaders(array $headers): array
    {
        $errors = [];
        if (count($headers) !== count(array_unique($headers))) {
            $errors[] = 'The CSV header contains duplicate column names.';
        }
        foreach (self::BASE_HEADERS as $required) {
            if (! in_array($required, $headers, true)) {
                $errors[] = "The CSV is missing the required {$required} column.";
            }
        }
        foreach ($headers as $name) {
            if (! in_array($name, self::BASE_HEADERS, true) && preg_match('/^option_[1-8](?:_correct)?$/', $name) !== 1) {
                $errors[] = "The column {$name} is not supported.";
            }
        }
        $optionColumns = [];
        foreach ($headers as $name) {
            if (preg_match('/^option_([1-8])(?:_correct)?$/', $name, $matches) === 1) {
                $optionColumns[(int) $matches[1]][str_ends_with($name, '_correct') ? 'correct' : 'text'] = true;
            }
        }
        if (count($optionColumns) < 2) {
            $errors[] = 'Include at least option_1, option_1_correct, option_2, and option_2_correct columns.';
        }
        foreach ($optionColumns as $number => $columns) {
            if (! isset($columns['correct'], $columns['text'])) {
                $errors[] = "Both option_{$number} and option_{$number}_correct columns are required.";
            }
        }
        if ($optionColumns !== []) {
            $numbers = array_keys($optionColumns);
            sort($numbers);
            if ($numbers !== range(1, max($numbers))) {
                $errors[] = 'Option columns must be numbered consecutively starting at 1.';
            }
        }

        return array_values(array_unique($errors));
    }

    private function booleanValue(string $value): ?bool
    {
        return match ($value) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => null,
        };
    }

    private function failedPreview(string|array $errors): array
    {
        return [
            'valid' => false,
            'row_count' => 0,
            'valid_count' => 0,
            'rows' => [],
            'errors' => is_array($errors) ? array_values($errors) : [$errors],
        ];
    }
}
