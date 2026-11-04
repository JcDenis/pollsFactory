<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Favorites;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Html;

/**
 * @brief       pollsFactory backend behaviors.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class BackendBehaviors
{
    /**
     * Dashboard favorite.
     */
    public static function adminDashboardFavoritesV2(Favorites $favs): void
    {
        $favs->register(My::id(), [
            'title'       => My::name(),
            'url'         => My::manageUrl([], '&'),
            'small-icon'  => My::icons(),
            'large-icon'  => My::icons(),
            'permissions' => App::auth()->makePermissions([
                App::auth()::PERMISSION_USAGE,
                App::auth()::PERMISSION_CONTENT_ADMIN,
            ]),
            'dashboard_cb' => function (ArrayObject $icon): void {
                $count = Polls::getPolls([], true)->intField(0);
                if ($count > 0) {
                    $icon['title'] = sprintf(__('One poll', '%d polls', $count), $count);
                }
            },
        ]);
    }

    /**
     * Polls lists columns.
     *
     * @param   ArrayObject<string, mixed>  $cols   The columns
     */
    public static function adminColumnsListsV2(ArrayObject $cols): void
    {
        $cols[My::id()] = [
            My::name(),
            [
                'votes'   => [true, __('Votes')],
                'voters'  => [true, __('Voters')],
                'entries' => [true, __('Entries')],
                'date'    => [true, __('Date')],
                'author'  => [true, __('Author')],
            ],
        ];
    }

    /**
     * Polls lists sort options.
     *
     * @param   ArrayObject<string, mixed>  $sorts  The sort options
     */
    public static function adminFiltersListsV2(ArrayObject $sorts): void
    {
        $sorts[My::id()] = [
            My::name(),
            [
                __('Date')     => 'post_dt',
                __('Title')    => 'post_title',
                __('Author')   => 'user_id',
                __('Status')   => 'post_status',
                __('Selected') => 'post_selected',
            ],
            'post_dt',
            'desc',
            [__('polls per page'), 30],
        ];
    }

    /**
     * Clean polls options on entry or poll deletion.
     */
    public static function adminBeforePostDelete(int $post_id): void
    {
        $rs = App::blog()->getPosts(['post_id' => $post_id, 'post_type' => '', 'no_content' => true]);
        if (!$rs->isEmpty() && $rs->strField('post_type') === My::POST_TYPE) {
            Polls::delPollOptions($post_id);
        }

        Polls::unlinkPoll($post_id);
    }

    /**
     * Polls box on entry edition page.
     *
     * @param   ArrayObject<string, mixed>                  $main_items     The main items
     * @param   ArrayObject<string, array<string, mixed>>   $sidebar_items  The sidebar items
     * @param   null|MetaRecord                             $post           The entry
     */
    public static function adminPostFormItems(ArrayObject $main_items, ArrayObject $sidebar_items, ?MetaRecord $post = null): void
    {
        $linked = $post instanceof MetaRecord ? Polls::getPollsOfPost($post->intField('post_id')) : [];
        $items  = [];
        $combo  = ['-' => ''];

        $rs = Polls::getPolls(['no_content' => true, 'order' => 'post_dt DESC']);
        while ($rs->fetch()) {
            $poll_id = $rs->intField('post_id');
            $title   = Html::escapeHTML($rs->strField('post_title'));
            if (in_array($poll_id, $linked, true)) {
                $items[] = (new Para())->items([
                    (new Checkbox([My::id() . '_polls[]', My::id() . '_poll_' . $poll_id], true))
                        ->value($poll_id)
                        ->label(new Label($title, Label::IL_FT)),
                    (new Link())
                        ->href(App::postTypes()->getPostAdminURL(My::POST_TYPE, $poll_id))
                        ->title(__('Edit poll'))
                        ->text(' &rarr;'),
                ])->render();
            } else {
                $combo[$title] = (string) $poll_id;
            }
        }

        if (count($combo) > 1) {
            $items[] = (new Para())->items([
                (new Select(My::id() . '_add'))
                    ->items($combo)
                    ->label(new Label(__('Add a poll:'), Label::OL_TF)),
            ])->render();
        }

        if ($items === []) {
            $items[] = (new Para())->items([
                (new Link())
                    ->href(My::manageUrl(['part' => 'poll']))
                    ->text(__('Create a poll')),
            ])->render();
        }

        $items[] = (new Hidden(My::id() . '_box', '1'))->render();

        $sidebar_items['pollsfactory-box'] = [
            'title' => My::name(),
            'items' => $items,
        ];
    }

    /**
     * Save polls of an entry.
     */
    public static function adminAfterPostSave(Cursor $cur, int $post_id): void
    {
        if (!isset($_POST[My::id() . '_box'])) {
            // polls box was not displayed
            return;
        }

        $keep = is_array($_POST[My::id() . '_polls'] ?? null) ? array_map(intval(...), array_filter($_POST[My::id() . '_polls'], is_numeric(...))) : [];
        foreach (Polls::getPollsOfPost($post_id) as $poll_id) {
            if (!in_array($poll_id, $keep, true)) {
                Polls::unlinkPoll($post_id, $poll_id);
            }
        }

        $add = is_numeric($_POST[My::id() . '_add'] ?? null) ? (int) $_POST[My::id() . '_add'] : 0;
        if ($add > 0 && !Polls::getPoll($add, true)->isEmpty()) {
            Polls::linkPoll($post_id, $add);
        }
    }
}
