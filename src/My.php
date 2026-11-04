<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use ArrayObject;
use Dotclear\App;
use Dotclear\Module\MyPlugin;

/**
 * @brief       pollsFactory My helper.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class My extends MyPlugin
{
    /**
     * Polls post type.
     *
     * @var     string  POST_TYPE
     */
    public const POST_TYPE = 'pollsfactory';

    /**
     * Polls options database table name.
     *
     * This table is shared with other plugins (ex: postWidgetText).
     *
     * @var     string  TABLE_NAME
     */
    public const TABLE_NAME = 'post_option';

    /**
     * Option type of a poll query.
     *
     * post_id is the poll, option_meta is the query type.
     *
     * @var     string  TYPE_QUERY
     */
    public const TYPE_QUERY = 'pollsquery';

    /**
     * Option type of a query selection.
     *
     * post_id is the poll, option_meta is the query id.
     *
     * @var     string  TYPE_SELECTION
     */
    public const TYPE_SELECTION = 'pollsselection';

    /**
     * Option type of a response.
     *
     * post_id is the poll, option_meta is the query id,
     * option_title is the voter, option_content is the response.
     *
     * @var     string  TYPE_RESPONSE
     */
    public const TYPE_RESPONSE = 'pollsresponse';

    /**
     * Option type of a link between an entry and a poll.
     *
     * post_id is the entry, option_meta is the poll id.
     *
     * @var     string  TYPE_POST
     */
    public const TYPE_POST = 'pollspost';

    /**
     * Voters cookie name.
     *
     * @var     string  COOKIE_NAME
     */
    public const COOKIE_NAME = 'pollsFactoryVotes';

    /**
     * Voter identification by cookie.
     *
     * @var     int     IDENT_COOKIE
     */
    public const IDENT_COOKIE = 0;

    /**
     * Voter identification by cookie and IP.
     *
     * @var     int     IDENT_BOTH
     */
    public const IDENT_BOTH = 1;

    /**
     * Voter identification by IP.
     *
     * @var     int     IDENT_IP
     */
    public const IDENT_IP = 2;

    /**
     * Queries types with choices.
     *
     * @var     array<int, string>  CHOICE_TYPES
     */
    public const CHOICE_TYPES = ['checkbox', 'radio', 'combo'];

    /**
     * Queries types with text responses.
     *
     * @var     array<int, string>  TEXT_TYPES
     */
    public const TEXT_TYPES = ['field', 'textarea'];

    public static function checkCustomContext(int $context): ?bool
    {
        return match ($context) {
            // Add usage perm to backend
            self::MANAGE, self::MENU => App::task()->checkContext('BACKEND')
                && App::blog()->isDefined()
                && App::auth()->check(App::auth()->makePermissions([
                    App::auth()::PERMISSION_USAGE,
                    App::auth()::PERMISSION_CONTENT_ADMIN,
                ]), App::blog()->id()),

            default => null,
        };
    }

    /**
     * Check if plugin is active on current blog.
     */
    public static function isActive(): bool
    {
        return App::blog()->isDefined() && (bool) self::settings()->getBool('active', false);
    }

    /**
     * Get voters identification mode.
     */
    public static function userIdent(): int
    {
        $ident = (int) self::settings()->getInt('user_ident', false);

        return in_array($ident, [self::IDENT_COOKIE, self::IDENT_BOTH, self::IDENT_IP], true) ? $ident : self::IDENT_COOKIE;
    }

    /**
     * Get URL types where polls of entries are displayed.
     *
     * @return  array<int, string>
     */
    public static function tplTypes(): array
    {
        $value = self::settings()->get('public_tpltypes');

        return is_array($value) ? array_values(array_filter($value, is_string(...))) : ['post'];
    }

    /**
     * Get place of polls in entries content.
     *
     * @return  string  before, after or empty string
     */
    public static function publicPlace(): string
    {
        $place = self::settings()->getStr('public_place', false);

        return in_array($place, ['before', 'after'], true) ? $place : '';
    }

    /**
     * Check if a query type uses choices.
     */
    public static function isChoiceType(string $type): bool
    {
        return in_array($type, self::CHOICE_TYPES, true);
    }

    /**
     * Get queries types combo.
     *
     * @return  array<string, string>
     */
    public static function queryTypesCombo(): array
    {
        return [
            __('Multiple choices list') => 'checkbox',
            __('Single choice list')    => 'radio',
            __('Options box')           => 'combo',
            __('Text field')            => 'field',
            __('Text area')             => 'textarea',
        ];
    }

    /**
     * Get query type name.
     */
    public static function queryTypeName(string $type): string
    {
        $name = array_search($type, self::queryTypesCombo(), true);

        return is_string($name) ? $name : __('Unknown');
    }

    /**
     * Get voters identification combo.
     *
     * @return  array<string, string>
     */
    public static function userIdentCombo(): array
    {
        return [
            __('Cookie')          => (string) self::IDENT_COOKIE,
            __('Cookie and IP')   => (string) self::IDENT_BOTH,
            __('IP address only') => (string) self::IDENT_IP,
        ];
    }

    /**
     * Get public URL types combo.
     *
     * @return  array<string, string>
     */
    public static function urlTypesCombo(): array
    {
        /**
         * @var ArrayObject<string, mixed>
         */
        $types = new ArrayObject([
            __('Home page')     => 'default',
            __('Entries pages') => 'post',
            __('Static pages')  => 'pages',
            __('Tags pages')    => 'tag',
            __('Archives pages') => 'archive',
            __('Category pages') => 'category',
            __('Entries feed')  => 'feed',
        ]);

        # --BEHAVIOR-- pollsFactoryPublicUrlTypes -- ArrayObject
        App::behavior()->callBehavior('pollsFactoryPublicUrlTypes', $types);

        $res = [];
        foreach ($types as $k => $v) {
            if (is_string($v)) {
                $res[(string) $k] = $v;
            }
        }

        return $res;
    }
}
