<?php

declare(strict_types=1);

namespace App\Services\Idp\Migration;

/**
 * Reduces a user or room name to a key two systems can be compared on.
 *
 * Names are all a school's aula rows and its directory share, and they never
 * agree exactly: casing differs, umlauts are written both ways, spacing is
 * inconsistent. Every step here is a fold that makes two spellings of one name
 * equal, and nothing more. No fuzzy distance: a near-match that is wrong hands
 * one account to another person.
 */
final class NameKey
{
    private const array FOLD = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ý' => 'y',
    ];

    public static function of(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $key = mb_strtolower(trim($name));
        $key = strtr($key, self::FOLD);
        // Punctuation carries no meaning here: "Müller-Schmidt" and
        // "Mueller Schmidt" are one name written two ways.
        $key = (string) preg_replace('/[^a-z0-9]+/u', ' ', $key);
        $key = trim((string) preg_replace('/\s+/', ' ', $key));

        return $key === '' ? null : $key;
    }

    /**
     * Every spelling of a name worth comparing.
     *
     * A directory can carry both a full first name and the one in use ("Wilma
     * Johanna Sophie" going by "Johanna"), and aula holds `realname` next to
     * `displayname`. Any one key matching counts as a match.
     *
     * @param  list<string|null>  $names
     * @return list<string>
     */
    public static function keys(array $names): array
    {
        $keys = [];

        foreach ($names as $name) {
            $key = self::of($name);

            if ($key !== null && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
