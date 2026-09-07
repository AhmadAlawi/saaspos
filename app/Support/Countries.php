<?php

namespace App\Support;

/**
 * ISO 3166-1 alpha-2 country list.
 *
 * Source code carries only the 2-letter codes; the localized display
 * name for each is resolved at runtime via the `intl` extension
 * (`Locale::getDisplayRegion`). This keeps the source clean and gives
 * us a free RTL / multi-locale name table — call `all('ar')` and the
 * names come back in Arabic.
 */
class Countries
{
    /** @var array<int,string> ISO 3166-1 alpha-2 codes. */
    private const CODES = [
        'AD','AE','AF','AG','AI','AL','AM','AO','AQ','AR','AS','AT','AU','AW','AX','AZ',
        'BA','BB','BD','BE','BF','BG','BH','BI','BJ','BL','BM','BN','BO','BQ','BR','BS',
        'BT','BV','BW','BY','BZ',
        'CA','CC','CD','CF','CG','CH','CI','CK','CL','CM','CN','CO','CR','CU','CV','CW',
        'CX','CY','CZ',
        'DE','DJ','DK','DM','DO','DZ',
        'EC','EE','EG','EH','ER','ES','ET',
        'FI','FJ','FK','FM','FO','FR',
        'GA','GB','GD','GE','GF','GG','GH','GI','GL','GM','GN','GP','GQ','GR','GS','GT',
        'GU','GW','GY',
        'HK','HM','HN','HR','HT','HU',
        'ID','IE','IL','IM','IN','IO','IQ','IR','IS','IT',
        'JE','JM','JO','JP',
        'KE','KG','KH','KI','KM','KN','KP','KR','KW','KY','KZ',
        'LA','LB','LC','LI','LK','LR','LS','LT','LU','LV','LY',
        'MA','MC','MD','ME','MF','MG','MH','MK','ML','MM','MN','MO','MP','MQ','MR','MS',
        'MT','MU','MV','MW','MX','MY','MZ',
        'NA','NC','NE','NF','NG','NI','NL','NO','NP','NR','NU','NZ',
        'OM',
        'PA','PE','PF','PG','PH','PK','PL','PM','PN','PR','PS','PT','PW','PY',
        'QA',
        'RE','RO','RS','RU','RW',
        'SA','SB','SC','SD','SE','SG','SH','SI','SJ','SK','SL','SM','SN','SO','SR','SS',
        'ST','SV','SX','SY','SZ',
        'TC','TD','TF','TG','TH','TJ','TK','TL','TM','TN','TO','TR','TT','TV','TW','TZ',
        'UA','UG','UM','US','UY','UZ',
        'VA','VC','VE','VG','VI','VN','VU',
        'WF','WS',
        'YE','YT',
        'ZA','ZM','ZW',
    ];

    /** @var array<string,array<string,string>> Per-locale name cache. */
    private static array $cache = [];

    /**
     * Return [ code => localized display name ] sorted by name.
     *
     * @return array<string,string>
     */
    public static function all(?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $out = [];
        foreach (self::CODES as $code) {
            $name = \Locale::getDisplayRegion('-'.$code, $locale);
            $out[$code] = $name !== '' ? $name : $code;
        }
        // Natural case-insensitive sort by name (locale-aware enough
        // for our purposes; full collator support can come later).
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return self::$cache[$locale] = $out;
    }

    /** Resolve a single code → localized name (returns the code if unknown). */
    public static function name(string $code, ?string $locale = null): string
    {
        $code = strtoupper(trim($code));
        if ($code === '' || ! in_array($code, self::CODES, true)) {
            return $code;
        }
        return self::all($locale)[$code] ?? $code;
    }

    /** True if the code is a recognised ISO 3166-1 alpha-2. */
    public static function isValid(string $code): bool
    {
        return in_array(strtoupper(trim($code)), self::CODES, true);
    }
}
