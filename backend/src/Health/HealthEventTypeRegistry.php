<?php

declare(strict_types=1);

namespace App\Health;

/**
 * Реєстр типів подій здоров'я. Тип визначає, які структуровані поля дозволені
 * в payload і як їх валідувати. Нові типи (цукор, температура) — це лише
 * запис тут, без нових таблиць чи міграцій.
 */
final class HealthEventTypeRegistry
{
    /** Типи з болем/симптомом можуть мати тяжкість 1–5. */
    private const WITH_SEVERITY = ['headache', 'migraine', 'symptom'];

    public const TYPES = ['pressure', 'weight', 'headache', 'migraine', 'medication', 'symptom', 'note', 'custom'];

    public function isKnown(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    /**
     * Валідує і нормалізує payload для типу.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{errors: array<string, string>, payload: array<string, mixed>}
     */
    public function validate(string $type, array $payload): array
    {
        $errors = [];
        $clean = [];

        if ($type === 'pressure') {
            $systolic = (int) ($payload['systolic'] ?? 0);
            $diastolic = (int) ($payload['diastolic'] ?? 0);
            $pulse = (int) ($payload['pulse'] ?? 0);

            if ($systolic < 60 || $systolic > 260) {
                $errors['systolic'] = 'Систолічний тиск має бути в межах 60–260';
            }
            if ($diastolic < 40 || $diastolic > 160) {
                $errors['diastolic'] = 'Діастолічний тиск має бути в межах 40–160';
            }
            if ($errors === [] && $systolic <= $diastolic) {
                $errors['systolic'] = 'Систолічний тиск має бути більшим за діастолічний';
            }
            if ($pulse < 30 || $pulse > 220) {
                $errors['pulse'] = 'Пульс має бути в межах 30–220';
            }

            $clean = ['systolic' => $systolic, 'diastolic' => $diastolic, 'pulse' => $pulse];
        }

        if ($type === 'weight') {
            $kg = (float) ($payload['kg'] ?? 0);
            if ($kg < 20 || $kg > 400) {
                $errors['kg'] = 'Вага має бути в межах 20–400 кг';
            }
            $clean = ['kg' => round($kg, 1)];
        }

        if (in_array($type, self::WITH_SEVERITY, true) && isset($payload['severity']) && $payload['severity'] !== '') {
            $severity = (int) $payload['severity'];
            if ($severity < 1 || $severity > 5) {
                $errors['severity'] = 'Тяжкість — від 1 до 5';
            } else {
                $clean['severity'] = $severity;
            }
        }

        if ($type === 'custom') {
            $title = trim((string) ($payload['title'] ?? ''));
            if ($title === '') {
                $errors['title'] = 'Для власної події вкажіть назву';
            } else {
                $clean['title'] = mb_substr($title, 0, 100);
            }
        }

        return ['errors' => $errors, 'payload' => $clean];
    }
}
