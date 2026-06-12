<?php

namespace App\Services\PM;

class PmWarningService
{
    /**
     * @param  array{input_type:string,target_value:float|int|string|null,min_value:float|int|string|null,max_value:float|int|string|null,unit:string|null}  $standardSnapshot
     * @param  float|int|string|null  $numericValue
     * @return array{is_warning:bool,warning_message:?string}
     */
    public function evaluate(array $standardSnapshot, float|int|string|null $numericValue): array
    {
        $inputType = (string) ($standardSnapshot['input_type'] ?? '');
        $unit = (string) ($standardSnapshot['unit'] ?? '');

        if ($inputType === 'action' || $numericValue === null || $numericValue === '') {
            return [
                'is_warning' => false,
                'warning_message' => null,
            ];
        }

        $value = (float) $numericValue;

        if ($inputType === 'number') {
            $target = $standardSnapshot['target_value'];

            if ($target === null || $target === '') {
                return [
                    'is_warning' => false,
                    'warning_message' => null,
                ];
            }

            $targetNumber = (float) $target;

            if ($value !== $targetNumber) {
                return [
                    'is_warning' => true,
                    'warning_message' => sprintf(
                        'Nilai %.2f %s tidak sesuai target %.2f %s.',
                        $value,
                        $unit,
                        $targetNumber,
                        $unit,
                    ),
                ];
            }

            return [
                'is_warning' => false,
                'warning_message' => null,
            ];
        }

        if ($inputType === 'range') {
            $min = $standardSnapshot['min_value'];
            $max = $standardSnapshot['max_value'];

            if ($min === null || $max === null || $min === '' || $max === '') {
                return [
                    'is_warning' => false,
                    'warning_message' => null,
                ];
            }

            $minNumber = (float) $min;
            $maxNumber = (float) $max;

            if ($value < $minNumber || $value > $maxNumber) {
                return [
                    'is_warning' => true,
                    'warning_message' => sprintf(
                        'Nilai %.2f %s di luar range %.2f - %.2f %s.',
                        $value,
                        $unit,
                        $minNumber,
                        $maxNumber,
                        $unit,
                    ),
                ];
            }
        }

        return [
            'is_warning' => false,
            'warning_message' => null,
        ];
    }
}

