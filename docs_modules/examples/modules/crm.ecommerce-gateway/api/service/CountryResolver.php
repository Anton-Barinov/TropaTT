<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

/**
 * Country resolution for inbound store payloads.
 *
 * Store platforms are inconsistent about the delivery country: some send the
 * ISO 3166-1 alpha-2 code (`RU`, `DE`), others send the platform-localised name
 * (`Россия`, `Deutschland`, `Deutschland`), and several switch between the two
 * depending on the store locale. The gateway contract stores only the alpha-2
 * code, so names have to be resolved before the address is accepted — otherwise
 * the delivery country silently disappears from every order.
 *
 * The class is intentionally stateless and dependency-free (no mbstring-only
 * APIs, no intl extension, no database) so it runs on shared hosting and can be
 * unit tested without the CRM bootstrap.
 */
final class CountryResolver
{
    /** Widest accepted input before a lookup is attempted. */
    private const MAX_INPUT_LENGTH = 96;

    /**
     * alpha-2 => localised names, English first. Names are normalised when the
     * lookup map is built, so they can be written naturally here.
     *
     * @var array<string,list<string>>
     */
    private const NAMES = [
        'AF' => ['Afghanistan', 'Афганистан'],
        'AL' => ['Albania', 'Албания'],
        'DZ' => ['Algeria', 'Алжир'],
        'AD' => ['Andorra', 'Андорра'],
        'AR' => ['Argentina', 'Аргентина'],
        'AM' => ['Armenia', 'Армения'],
        'AU' => ['Australia', 'Австралия'],
        'AT' => ['Austria', 'Österreich', 'Австрия'],
        'AZ' => ['Azerbaijan', 'Азербайджан'],
        'BH' => ['Bahrain', 'Бахрейн'],
        'BD' => ['Bangladesh', 'Бангладеш'],
        'BY' => ['Belarus', 'Беларусь', 'Белоруссия'],
        'BE' => ['Belgium', 'Бельгия'],
        'BO' => ['Bolivia', 'Боливия'],
        'BA' => ['Bosnia and Herzegovina', 'Босния и Герцеговина'],
        'BR' => ['Brazil', 'Brasil', 'Бразилия'],
        'BG' => ['Bulgaria', 'Болгария'],
        'KH' => ['Cambodia', 'Камбоджа'],
        'CA' => ['Canada', 'Канада'],
        'CL' => ['Chile', 'Чили'],
        'CN' => ['China', 'Китай'],
        'CO' => ['Colombia', 'Колумбия'],
        'CR' => ['Costa Rica', 'Коста-Рика'],
        'HR' => ['Croatia', 'Хорватия'],
        'CU' => ['Cuba', 'Куба'],
        'CY' => ['Cyprus', 'Кипр'],
        'CZ' => ['Czechia', 'Czech Republic', 'Чехия'],
        'DK' => ['Denmark', 'Дания'],
        'DO' => ['Dominican Republic', 'Доминиканская Республика'],
        'EC' => ['Ecuador', 'Эквадор'],
        'EG' => ['Egypt', 'Египет'],
        'EE' => ['Estonia', 'Эстония'],
        'ET' => ['Ethiopia', 'Эфиопия'],
        'FI' => ['Finland', 'Финляндия'],
        'FR' => ['France', 'Франция'],
        'GE' => ['Georgia', 'Грузия'],
        'DE' => ['Germany', 'Deutschland', 'Германия'],
        'GH' => ['Ghana', 'Гана'],
        'GR' => ['Greece', 'Греция'],
        'HK' => ['Hong Kong', 'Гонконг'],
        'HU' => ['Hungary', 'Венгрия'],
        'IS' => ['Iceland', 'Исландия'],
        'IN' => ['India', 'Индия'],
        'ID' => ['Indonesia', 'Индонезия'],
        'IR' => ['Iran', 'Иран'],
        'IQ' => ['Iraq', 'Ирак'],
        'IE' => ['Ireland', 'Ирландия'],
        'IL' => ['Israel', 'Израиль'],
        'IT' => ['Italy', 'Italia', 'Италия'],
        'JP' => ['Japan', 'Япония'],
        'JO' => ['Jordan', 'Иордания'],
        'KZ' => ['Kazakhstan', 'Казахстан'],
        'KE' => ['Kenya', 'Кения'],
        'KP' => ['North Korea', 'КНДР', 'Северная Корея'],
        'KR' => ['South Korea', 'Republic of Korea', 'Korea', 'Южная Корея', 'Корея'],
        'KW' => ['Kuwait', 'Кувейт'],
        'KG' => ['Kyrgyzstan', 'Киргизия', 'Кыргызстан'],
        'LA' => ['Laos', 'Лаос'],
        'LV' => ['Latvia', 'Латвия'],
        'LB' => ['Lebanon', 'Ливан'],
        'LY' => ['Libya', 'Ливия'],
        'LT' => ['Lithuania', 'Литва'],
        'LU' => ['Luxembourg', 'Люксембург'],
        'MK' => ['North Macedonia', 'Macedonia', 'Македония'],
        'MY' => ['Malaysia', 'Малайзия'],
        'MT' => ['Malta', 'Мальта'],
        'MX' => ['Mexico', 'Мексика'],
        'MD' => ['Moldova', 'Молдова', 'Молдавия'],
        'MN' => ['Mongolia', 'Монголия'],
        'ME' => ['Montenegro', 'Черногория'],
        'MA' => ['Morocco', 'Марокко'],
        'MM' => ['Myanmar', 'Мьянма'],
        'NP' => ['Nepal', 'Непал'],
        'NL' => ['Netherlands', 'Holland', 'Нидерланды', 'Голландия'],
        'NZ' => ['New Zealand', 'Новая Зеландия'],
        'NG' => ['Nigeria', 'Нигерия'],
        'NO' => ['Norway', 'Норвегия'],
        'OM' => ['Oman', 'Оман'],
        'PK' => ['Pakistan', 'Пакистан'],
        'PA' => ['Panama', 'Панама'],
        'PY' => ['Paraguay', 'Парагвай'],
        'PE' => ['Peru', 'Перу'],
        'PH' => ['Philippines', 'Филиппины'],
        'PL' => ['Poland', 'Polska', 'Польша'],
        'PT' => ['Portugal', 'Португалия'],
        'QA' => ['Qatar', 'Катар'],
        'RO' => ['Romania', 'Румыния'],
        'RU' => ['Russia', 'Russian Federation', 'Россия', 'Российская Федерация'],
        'SA' => ['Saudi Arabia', 'Саудовская Аравия'],
        'RS' => ['Serbia', 'Сербия'],
        'SG' => ['Singapore', 'Сингапур'],
        'SK' => ['Slovakia', 'Словакия'],
        'SI' => ['Slovenia', 'Словения'],
        'ZA' => ['South Africa', 'ЮАР', 'Южная Африка'],
        'ES' => ['Spain', 'España', 'Испания'],
        'LK' => ['Sri Lanka', 'Шри-Ланка'],
        'SE' => ['Sweden', 'Швеция'],
        'CH' => ['Switzerland', 'Schweiz', 'Suisse', 'Швейцария'],
        'TW' => ['Taiwan', 'Тайвань'],
        'TJ' => ['Tajikistan', 'Таджикистан'],
        'TZ' => ['Tanzania', 'Танзания'],
        'TH' => ['Thailand', 'Таиланд'],
        'TN' => ['Tunisia', 'Тунис'],
        'TR' => ['Turkey', 'Türkiye', 'Турция'],
        'TM' => ['Turkmenistan', 'Туркменистан'],
        'UG' => ['Uganda', 'Уганда'],
        'UA' => ['Ukraine', 'Украина'],
        'AE' => ['United Arab Emirates', 'UAE', 'ОАЭ', 'Объединённые Арабские Эмираты'],
        'GB' => ['United Kingdom', 'UK', 'Great Britain', 'England', 'Великобритания', 'Англия'],
        'US' => ['United States', 'USA', 'United States of America', 'США', 'Америка', 'Соединённые Штаты Америки'],
        'UY' => ['Uruguay', 'Уругвай'],
        'UZ' => ['Uzbekistan', 'Узбекистан'],
        'VE' => ['Venezuela', 'Венесуэла'],
        'VN' => ['Vietnam', 'Вьетнам'],
    ];

    /**
     * Latin diacritics folded before the lookup so `Türkiye` and `Turkiye`
     * (and `España`/`Espana`, `Österreich`/`Osterreich`) share one key.
     */
    private const DIACRITICS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'æ' => 'ae',
        'ç' => 'c', 'č' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ě' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n', 'ň' => 'n',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o',
        'ř' => 'r', 'š' => 's', 'ş' => 's',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ů' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ž' => 'z',
    ];

    /** @var array<string,string>|null */
    private static ?array $lookup = null;

    /**
     * Resolves a country name or code to an upper-case ISO 3166-1 alpha-2 code.
     * Returns an empty string when the value is neither a known country nor a
     * two-letter code, so callers can keep the field out of the record instead
     * of storing a truncated name.
     */
    public static function toIsoCode(string $value): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > self::MAX_INPUT_LENGTH) {
            return '';
        }

        $lookup = self::lookup();

        // A bare two-letter token is taken as a code, exactly like before names
        // became supported: some stores use custom or legacy country codes and
        // an unknown pair must not become a regression. Tokens the dictionary
        // knows better (`UK`) resolve to the canonical code first.
        if (preg_match('/^[A-Za-z]{2}$/', $value) === 1) {
            return $lookup[self::key($value)] ?? strtoupper($value);
        }

        return $lookup[self::key($value)] ?? '';
    }

    /**
     * True when the value resolves to a country the gateway can store.
     */
    public static function isResolvable(string $value): bool
    {
        return self::toIsoCode($value) !== '';
    }

    /**
     * Normalised lookup key: lower case, `ё` folded to `е`, Latin diacritics
     * folded, and every non-letter dropped (so `Côte d'Ivoire`-style spacing and
     * hyphenation do not matter).
     */
    public static function key(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = strtr($name, self::DIACRITICS);
        $name = str_replace('ё', 'е', $name);
        $name = preg_replace('/[^a-zа-я]+/u', '', $name) ?? '';

        return $name;
    }

    /**
     * @return array<string,string>
     */
    private static function lookup(): array
    {
        if (self::$lookup !== null) {
            return self::$lookup;
        }

        $map = [];
        foreach (self::NAMES as $code => $names) {
            $map[self::key($code)] = $code;
            foreach ($names as $name) {
                $key = self::key($name);
                // First spelling wins so the canonical English name does not
                // get overwritten by a later alias.
                if ($key !== '' && !isset($map[$key])) {
                    $map[$key] = $code;
                }
            }
        }

        return self::$lookup = $map;
    }
}
