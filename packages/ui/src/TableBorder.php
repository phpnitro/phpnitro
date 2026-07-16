<?php

namespace Engine;

/**
 * Flutter's TableBorder describes per-edge border painting on a table;
 * here it's a set of Tailwind divide-x/divide-y presets applied to the
 * <table> element (divide-* draws borders between children natively,
 * no arbitrary per-cell selectors needed).
 */
final class TableBorder
{
    public const ALL = 'border border-collapse divide-y divide-x divide-gray-300 dark:divide-gray-700 dark:border-gray-700';
    public const HORIZONTAL = 'divide-y divide-gray-300 dark:divide-gray-700';
    public const VERTICAL = 'divide-x divide-gray-300 dark:divide-gray-700';
    public const NONE = '';
}
