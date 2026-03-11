<?php
/**
 * Symbols - Marker shape definitions for MapServer
 */

declare(strict_types=1);

class Symbols
{
    /**
     * Get all available shape names
     */
    public static function shapes(): array
    {
        return [
            'general' => [
                'plus' => 'Plus sign',
                'cross' => 'X cross',
                'asterisk' => 'Asterisk'
            ],
            'closed' => [
                'circle' => 'Circle (filled)',
                'star' => 'Star (filled)',
                'square' => 'Square (filled)',
                'triangle' => 'Triangle (filled)',
                'hexagon' => 'Hexagon (filled)',
                'inversetriangle' => 'Inverted triangle (filled)'
            ],
            'open' => [
                'opencircle' => 'Circle (outline)',
                'openstar' => 'Star (outline)',
                'opensquare' => 'Square (outline)',
                'opentriangle' => 'Triangle (outline)',
                'openhexagon' => 'Hexagon (outline)',
                'inverseopentriangle' => 'Inverted triangle (outline)'
            ]
        ];
    }

    /**
     * Get symbol definitions for mapfile
     */
    public static function definitions(): array
    {
        return [
            // General (line-based) symbols
            'plus' => [
                'type' => 'VECTOR',
                'filled' => false,
                'points' => "0.5 0\n      0.5 1\n      -99 -99\n      0 0.5\n      1 0.5"
            ],
            'cross' => [
                'type' => 'VECTOR',
                'filled' => false,
                'points' => "0 0\n      1 1\n      -99 -99\n      0 1\n      1 0"
            ],
            'asterisk' => [
                'type' => 'VECTOR',
                'filled' => false,
                'points' => "0 0\n      1 1\n      -99 -99\n      0 1\n      1 0\n      -99 -99\n      0.5 0\n      0.5 1\n      -99 -99\n      0 0.5\n      1 0.5"
            ],

            // Closed (filled) symbols
            'circle' => [
                'type' => 'ELLIPSE',
                'filled' => true,
                'points' => '1 1'
            ],
            'star' => [
                'type' => 'VECTOR',
                'filled' => true,
                'points' => "0 0.375\n      0.35 0.365\n      0.5 0\n      0.65 0.375\n      1 0.375\n      0.75 0.625\n      0.875 1\n      0.5 0.75\n      0.125 1\n      0.25 0.625\n      0 0.375"
            ],
            'square' => [
                'type' => 'VECTOR',
                'filled' => true,
                'points' => "0 1\n      0 0\n      1 0\n      1 1\n      0 1"
            ],
            'triangle' => [
                'type' => 'VECTOR',
                'filled' => true,
                'points' => "0 1\n      0.5 0\n      1 1\n      0 1"
            ],
            'hexagon' => [
                'type' => 'VECTOR',
                'filled' => true,
                'points' => "0.23 0\n      0 0.5\n      0.23 1\n      0.77 1\n      1 0.5\n      0.77 0\n      0.23 0"
            ],
            'inversetriangle' => [
                'type' => 'VECTOR',
                'filled' => true,
                'points' => "0 0\n      1 0\n      0.5 1\n      0 0"
            ],

            // Open (outline) symbols - same shapes but not filled
            'opencircle' => [
                'type' => 'ELLIPSE',
                'filled' => false,
                'points' => '1 1'
            ],
            'openstar' => [
                'type' => 'VECTOR',
                'filled' => false,
                'points' => "0 0.375\n      0.35 0.365\n      0.5 0\n      0.65 0.375\n      1 0.375\n      0.75 0.625\n      0.875 1\n      0.5 0.75\n      0.125 1\n      0.25 0.625\n      0 0.375"
            ],
            'opensquare' => [
                'type' => 'VECTOR',
                'filled' => false,
                'points' => "0 1\n      0 0\n      1 0\n      1 1\n      0 1"
            ],
            'opentriangle' => [
                'type' => 'VECTOR',
                'filled' => false,
                'points' => "0 1\n      0.5 0\n      1 1\n      0 1"
            ],
            'openhexagon' => [
                'type' => 'VECTOR',
                'filled' => false,
                'points' => "0.23 0\n      0 0.5\n      0.23 1\n      0.77 1\n      1 0.5\n      0.77 0\n      0.23 0"
            ],
            'inverseopentriangle' => [
                'type' => 'VECTOR',
                'filled' => false,
                'points' => "0 0\n      1 0\n      0.5 1\n      0 0"
            ]
        ];
    }

    /**
     * Get available marker sizes
     */
    public static function sizes(): array
    {
        return [6, 8, 10, 12, 14, 16];
    }

    /**
     * Check if a shape name is valid
     */
    public static function isValid(string $shape): bool
    {
        return isset(self::definitions()[$shape]);
    }
}
