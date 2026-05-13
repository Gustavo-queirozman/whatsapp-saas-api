<?php

namespace App\Domain\Chatbot\Requests;

use App\Domain\Chatbot\Models\BotFlowOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBotFlowRequest extends FormRequest
{
    private const WEEK_DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('current_company_id');

        $rules = [
            'sector_id' => [
                'required',
                'integer',
                Rule::exists('sectors', 'id')->where('company_id', $companyId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'welcome_message' => ['nullable', 'string'],
            'menu_message' => ['nullable', 'string'],
            'invalid_option_message' => ['nullable', 'string'],
            'out_of_hours_message' => ['nullable', 'string'],
            'office_hours_enabled' => ['sometimes', 'boolean'],
            'office_hours_timezone' => ['nullable', 'string', 'max:100'],
            'office_hours' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'options' => ['nullable', 'array'],
            'options.*.label' => ['required', 'string', 'max:100'],
            'options.*.number' => ['nullable', 'string', 'max:20'],
            'options.*.keywords' => ['nullable', 'array'],
            'options.*.keywords.*' => ['required', 'string', 'max:100'],
            'options.*.action' => [
                'required',
                Rule::in([
                    BotFlowOption::ACTION_REPLY,
                    BotFlowOption::ACTION_TRANSFER_SECTOR,
                    BotFlowOption::ACTION_OPEN_QUEUE,
                ]),
            ],
            'options.*.response_message' => ['nullable', 'string'],
            'options.*.target_sector_id' => [
                'nullable',
                'integer',
                Rule::exists('sectors', 'id')->where('company_id', $companyId),
            ],
            'options.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'options.*.is_active' => ['sometimes', 'boolean'],
            'options.*.settings' => ['nullable', 'array'],
        ];

        foreach (self::WEEK_DAYS as $day) {
            $rules["office_hours.{$day}"] = ['nullable', 'array'];
            $rules["office_hours.{$day}.enabled"] = ['sometimes', 'boolean'];
            $rules["office_hours.{$day}.start"] = ['nullable', 'date_format:H:i'];
            $rules["office_hours.{$day}.end"] = ['nullable', 'date_format:H:i'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateOfficeHours($validator);
            $this->validateOptions($validator);
        });
    }

    private function validateOfficeHours(Validator $validator): void
    {
        if (! $this->boolean('office_hours_enabled')) {
            return;
        }

        $officeHours = $this->input('office_hours', []);

        foreach (self::WEEK_DAYS as $day) {
            $schedule = data_get($officeHours, $day);

            if (! is_array($schedule) || ! ((bool) data_get($schedule, 'enabled', false))) {
                continue;
            }

            $start = data_get($schedule, 'start');
            $end = data_get($schedule, 'end');

            if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
                $validator->errors()->add(
                    "office_hours.{$day}",
                    'Informe horario inicial e final para dias ativos.'
                );

                continue;
            }

            if ($start >= $end) {
                $validator->errors()->add(
                    "office_hours.{$day}.end",
                    'O horario final deve ser maior que o horario inicial.'
                );
            }
        }
    }

    private function validateOptions(Validator $validator): void
    {
        $options = $this->input('options', []);

        if (! is_array($options)) {
            return;
        }

        $numbers = [];
        $keywords = [];

        foreach ($options as $index => $option) {
            if (! is_array($option)) {
                continue;
            }

            $number = $this->normalizeText(data_get($option, 'number'));
            $keywordValues = collect(data_get($option, 'keywords', []))
                ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => $this->normalizeText($value))
                ->filter()
                ->values();

            if ($number === null && $keywordValues->isEmpty()) {
                $validator->errors()->add(
                    "options.{$index}",
                    'Cada opcao precisa de um numero ou pelo menos uma palavra-chave.'
                );
            }

            if ($number !== null) {
                if (isset($numbers[$number])) {
                    $validator->errors()->add(
                        "options.{$index}.number",
                        'O numero da opcao ja foi utilizado em outra opcao.'
                    );
                }

                $numbers[$number] = true;
            }

            foreach ($keywordValues as $keyword) {
                if (isset($keywords[$keyword])) {
                    $validator->errors()->add(
                        "options.{$index}.keywords",
                        'A palavra-chave ja foi utilizada em outra opcao.'
                    );
                }

                $keywords[$keyword] = true;
            }

            $action = (string) data_get($option, 'action');
            $targetSectorId = data_get($option, 'target_sector_id');

            if ($action === BotFlowOption::ACTION_TRANSFER_SECTOR && $targetSectorId === null) {
                $validator->errors()->add(
                    "options.{$index}.target_sector_id",
                    'Informe o setor de destino para opcoes de transferencia.'
                );
            }
        }
    }

    private function normalizeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = Str::lower(trim($value));

        return $normalizedValue === '' ? null : $normalizedValue;
    }
}
