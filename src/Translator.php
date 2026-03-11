<?php
/**
 * Translator class for SimpleMappr
 *
 * Handles internationalization using YAML locale files.
 * Inspired by Bionomia's approach to i18n.
 */

declare(strict_types=1);

namespace SimpleMappr;

use Symfony\Component\Yaml\Yaml;

class Translator
{
    private static ?Translator $instance = null;
    private string $locale;
    private string $defaultLocale = 'en';
    private array $translations = [];
    private array $loadedLocales = [];
    private string $localesPath;

    /**
     * Supported locales
     */
    public const SUPPORTED_LOCALES = [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'locale' => 'en_US'
        ],
        'fr' => [
            'name' => 'French',
            'native' => 'Français',
            'locale' => 'fr_FR'
        ]
    ];

    /**
     * Private constructor for singleton
     */
    private function __construct(string $locale = 'en')
    {
        $this->localesPath = ROOT . '/config/locales';
        $this->setLocale($locale);
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(string $locale = 'en'): self
    {
        if (self::$instance === null) {
            self::$instance = new self($locale);
        }
        return self::$instance;
    }

    /**
     * Set the current locale
     */
    public function setLocale(string $locale): self
    {
        // Validate locale
        if (!isset(self::SUPPORTED_LOCALES[$locale])) {
            $locale = $this->defaultLocale;
        }

        $this->locale = $locale;
        $this->loadLocale($locale);

        // Also load default locale as fallback
        if ($locale !== $this->defaultLocale) {
            $this->loadLocale($this->defaultLocale);
        }

        return $this;
    }

    /**
     * Get the current locale
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Get all supported locales
     */
    public function getSupportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    /**
     * Load a locale file
     */
    private function loadLocale(string $locale): void
    {
        if (isset($this->loadedLocales[$locale])) {
            return;
        }

        $filePath = $this->localesPath . '/' . $locale . '.yml';

        if (!file_exists($filePath)) {
            // Try .yaml extension
            $filePath = $this->localesPath . '/' . $locale . '.yaml';
        }

        if (file_exists($filePath)) {
            $content = Yaml::parseFile($filePath);
            if (isset($content[$locale]) && is_array($content[$locale])) {
                $this->translations[$locale] = $content[$locale];
            } else {
                $this->translations[$locale] = $content;
            }
            $this->loadedLocales[$locale] = true;
        } else {
            $this->translations[$locale] = [];
            $this->loadedLocales[$locale] = true;
        }
    }

    /**
     * Translate a key
     *
     * @param string $key Dot-notation key (e.g., 'editor.title')
     * @param array $params Placeholder replacements (e.g., ['name' => 'John'])
     * @return string Translated string or key if not found
     */
    public function t(string $key, array $params = []): string
    {
        $translation = $this->get($key, $this->locale);

        // Fallback to default locale
        if ($translation === null && $this->locale !== $this->defaultLocale) {
            $translation = $this->get($key, $this->defaultLocale);
        }

        // If still not found, return the key
        if ($translation === null) {
            return $key;
        }

        // Handle pluralization if 'count' param is provided
        if (isset($params['count']) && is_array($translation)) {
            $count = (int)$params['count'];
            if ($count === 1 && isset($translation['one'])) {
                $translation = $translation['one'];
            } elseif (isset($translation['other'])) {
                $translation = $translation['other'];
            } else {
                $translation = array_values($translation)[0] ?? $key;
            }
        }

        // Replace placeholders
        if (!empty($params) && is_string($translation)) {
            foreach ($params as $placeholder => $value) {
                $translation = str_replace(
                    ['%{' . $placeholder . '}', ':' . $placeholder, '%' . $placeholder . '%'],
                    $value,
                    $translation
                );
            }
        }

        return is_string($translation) ? $translation : $key;
    }

    /**
     * Get a translation by dot-notation key
     *
     * @return mixed
     */
    private function get(string $key, string $locale)
    {
        if (!isset($this->translations[$locale])) {
            return null;
        }

        $keys = explode('.', $key);
        $value = $this->translations[$locale];

        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Check if a translation exists
     */
    public function has(string $key): bool
    {
        return $this->get($key, $this->locale) !== null ||
               $this->get($key, $this->defaultLocale) !== null;
    }

    /**
     * Get all translations for the current locale (useful for JS)
     */
    public function all(): array
    {
        return $this->translations[$this->locale] ?? [];
    }

    /**
     * Get translations for a specific section (useful for JS)
     */
    public function section(string $section): array
    {
        return $this->translations[$this->locale][$section] ?? [];
    }

    /**
     * Detect locale from request (Accept-Language header or cookie)
     */
    public static function detectLocale(): string
    {
        // Check cookie first
        if (isset($_COOKIE['locale']) && isset(self::SUPPORTED_LOCALES[$_COOKIE['locale']])) {
            return $_COOKIE['locale'];
        }

        // Check query parameter
        if (isset($_GET['lang']) && isset(self::SUPPORTED_LOCALES[$_GET['lang']])) {
            return $_GET['lang'];
        }

        // Parse Accept-Language header
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($langs as $lang) {
                $lang = trim(explode(';', $lang)[0]);
                $lang = strtolower(substr($lang, 0, 2));
                if (isset(self::SUPPORTED_LOCALES[$lang])) {
                    return $lang;
                }
            }
        }

        return 'en';
    }

    /**
     * Set locale cookie
     */
    public function setLocaleCookie(string $locale, int $days = 365): void
    {
        if (isset(self::SUPPORTED_LOCALES[$locale])) {
            setcookie('locale', $locale, [
                'expires' => time() + ($days * 24 * 60 * 60),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}

/**
 * Helper function for translations (shorthand)
 */
function __t(string $key, array $params = []): string
{
    return Translator::getInstance()->t($key, $params);
}
