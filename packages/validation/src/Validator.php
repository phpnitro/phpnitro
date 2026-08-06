<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Validation;

/**
 * The piece missing for a "Form" to mean anything in a framework with no
 * client-side widget state (see docs/architecture.md): every field's
 * value only ever reaches PHP on a "submit:" round-trip (TextField's own
 * docblock), so validation has always had to happen here, by hand, one
 * `if ($username === '') { ... }` at a time (see NativeLoginScreen's
 * public/index.php handler). This is that hand-written boilerplate
 * factored into a rule engine instead — Laravel's `Validator::make()`
 * rule-string syntax on purpose (a PHP developer coming from anywhere
 * else in the ecosystem already knows it), not a new DSL to learn.
 *
 * Deliberately NOT trying to be Laravel's full validator: a fixed, small
 * rule set covering what a mobile form actually needs (see RULES below),
 * pure PHP, no service container, no custom-rule registration mechanism.
 * Extend the match() in check() directly if a project needs a rule this
 * doesn't cover — the same "if you know PHP you know PhpNitro" bet the
 * rest of this framework makes, not a plugin system for validation rules.
 */
final class Validator
{
    /** @var array<string, string> field => first failing rule's message */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     */
    private function __construct(
        private readonly array $data,
        private readonly array $rules,
    ) {
        $this->run();
    }

    /**
     * @param array<string, mixed> $data Typically $_GET straight from a "submit:" round-trip.
     * @param array<string, string|list<string>> $rules Field name => 'required|email' or ['required', 'email'].
     */
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string, string> field => first failing rule's message, empty if passes() */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return ?string The first failing rule's message for this one field, null if it passed (or wasn't checked at all). */
    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Trimmed string value for every field that HAD rules — the same
     * "collect what actually needs collecting" convenience array_filter()
     * on $_GET usually ends up hand-rolled for, scoped to just the fields
     * this call actually validated.
     *
     * @return array<string, string>
     */
    public function validated(): array
    {
        $out = [];
        foreach (array_keys($this->rules) as $field) {
            $out[$field] = trim((string) ($this->data[$field] ?? ''));
        }

        return $out;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $value = trim((string) ($this->data[$field] ?? ''));
            foreach (is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet) as $rule) {
                $message = $this->check($field, $value, $rule);
                if ($message !== null) {
                    // First failing rule wins — same default Laravel ships
                    // (bail-per-field, not "collect every violation"),
                    // simplest to show one caption under a field anyway.
                    $this->errors[$field] = $message;
                    break;
                }
            }
        }
    }

    private function check(string $field, string $value, string $rule): ?string
    {
        [$name, $param] = str_contains($rule, ':') ? explode(':', $rule, 2) : [$rule, null];

        // Every rule except 'required' passes on an empty value — pair
        // with 'required' explicitly if emptiness itself should fail,
        // same convention Laravel's rule chain uses.
        if ($name !== 'required' && $value === '') {
            return null;
        }

        return match ($name) {
            'required' => $value === '' ? "{$field} est requis." : null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? "{$field} doit être une adresse email valide." : null,
            'numeric' => !is_numeric($value) ? "{$field} doit être un nombre." : null,
            'min' => $param !== null && mb_strlen($value) < (int) $param ? "{$field} doit contenir au moins {$param} caractères." : null,
            'max' => $param !== null && mb_strlen($value) > (int) $param ? "{$field} doit contenir au plus {$param} caractères." : null,
            'same' => $param !== null && $value !== trim((string) ($this->data[$param] ?? '')) ? "{$field} ne correspond pas à {$param}." : null,
            'in' => $param !== null && !in_array($value, explode(',', $param), true) ? "{$field} n'est pas une valeur valide." : null,
            default => null,
        };
    }
}
