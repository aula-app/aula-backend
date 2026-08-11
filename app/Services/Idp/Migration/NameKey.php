<?php

declare(strict_types=1);

namespace App\Services\Idp\Migration;

/**
 * Reduces a person's or room's name to a key two systems can be compared on.
 *
 * Names are the only thing a school's aula rows and its directory have in
 * common, and they never agree exactly: casing differs, umlauts are written
 * both ways, and spacing is inconsistent. Everything here is a fold that makes
 * two spellings of the same name equal, and nothing more — no fuzzy distance,
 * because a near-match that is wrong hands somebody another person's account.
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
        // "Mueller Schmidt" are the same person written two ways.
        $key = (string) preg_replace('/[^a-z0-9]+/u', ' ', $key);
        $key = trim((string) preg_replace('/\s+/', ' ', $key));

        return $key === '' ? null : $key;
    }

    /**
     * Every spelling of a name worth comparing.
     *
     * A directory may give both a full first name and the one the person is
     * actually called by ("Wilma Johanna Sophie" goes by "Johanna"), and aula
     * holds both a real name and a display name. Any of them matching is a
     * match.
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
