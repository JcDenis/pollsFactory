<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\widgets\WidgetsElement;
use Dotclear\Plugin\widgets\WidgetsStack;

/**
 * @brief       pollsFactory widgets.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Widgets
{
    /**
     * Widgets registration.
     */
    public static function init(WidgetsStack $w): void
    {
        $w
            ->create(
                'pollsFactoryList',
                __('Polls: list'),
                self::pollsList(...),
                null,
                __('List of polls')
            )
            ->addTitle(__('Polls'))
            ->setting('limit', __('Polls limit:'), 10)
            ->setting('sortby', __('Order by:'), 'post_dt', 'combo', [
                __('Date')  => 'post_dt',
                __('Title') => 'post_title',
            ])
            ->setting('sort', __('Sort:'), 'desc', 'combo', [
                __('Ascending')  => 'asc',
                __('Descending') => 'desc',
            ])
            ->setting('period', __('Votes:'), '', 'combo', [
                __('All')    => '',
                __('Opened') => 'opened',
                __('Closed') => 'closed',
            ])
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $w
            ->create(
                'pollsFactoryEntry',
                __('Polls: polls of an entry'),
                self::pollsOfEntry(...),
                null,
                __('Polls related to current entry')
            )
            ->addTitle(__('Related polls'))
            ->setting('showdesc', __('Show description'), 0, 'check')
            ->setting('usebars', __('Use bars for results'), 1, 'check')
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $w
            ->create(
                'pollsFactorySelected',
                __('Polls: selected polls'),
                self::pollsSelected(...),
                null,
                __('Selected polls with their form')
            )
            ->addTitle(__('Selected polls'))
            ->setting('limit', __('Polls limit:'), 1)
            ->setting('showdesc', __('Show description'), 0, 'check')
            ->setting('usebars', __('Use bars for results'), 1, 'check')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
    }

    /**
     * Polls list widget.
     */
    public static function pollsList(WidgetsElement $w): string
    {
        if (!self::isDisplayed($w)) {
            return '';
        }

        $params = [
            'no_content' => true,
            'order'      => (self::str($w, 'sortby') === 'post_title' ? 'post_title' : 'post_dt') . ' ' . (self::str($w, 'sort') === 'asc' ? 'asc' : 'desc'),
            'limit'      => max(1, abs((int) self::str($w, 'limit'))),
        ];
        if (self::str($w, 'period') === 'opened') {
            $params['sql'] = 'AND post_open_tb = 1 ';
        } elseif (self::str($w, 'period') === 'closed') {
            $params['sql'] = 'AND post_open_tb = 0 ';
        }

        $rs  = Polls::getPolls($params);
        $res = '';
        while ($rs->fetch()) {
            $res .= '<li><a href="' . Render::url($rs) . '">' . Html::escapeHTML($rs->strField('post_title')) . '</a></li>';
        }
        if ($res === '') {
            return '';
        }

        return self::render($w, 'pollsfactory-list', '<ul>' . $res . '</ul>');
    }

    /**
     * Polls of an entry widget.
     */
    public static function pollsOfEntry(WidgetsElement $w): string
    {
        if (!self::isDisplayed($w, false) || !App::url()->isType('post')) {
            return '';
        }

        $post = App::frontend()->context()->__get('posts');
        if (!$post instanceof MetaRecord || $post->isEmpty()) {
            return '';
        }

        $res = '';
        foreach (Polls::getPollsOfPost($post->intField('post_id')) as $poll_id) {
            $res .= Render::poll($poll_id, true, (bool) $w->get('showdesc'), (bool) $w->get('usebars'));
        }
        if ($res === '') {
            return '';
        }

        return self::render($w, 'pollsfactory-entry', $res);
    }

    /**
     * Selected polls widget.
     */
    public static function pollsSelected(WidgetsElement $w): string
    {
        if (!self::isDisplayed($w)) {
            return '';
        }

        $rs = Polls::getPolls([
            'no_content'    => true,
            'post_selected' => 1,
            'order'         => 'post_dt DESC',
            'limit'         => max(1, abs((int) self::str($w, 'limit'))),
        ]);

        $res = '';
        while ($rs->fetch()) {
            $res .= Render::poll($rs->intField('post_id'), true, (bool) $w->get('showdesc'), (bool) $w->get('usebars'));
        }
        if ($res === '') {
            return '';
        }

        return self::render($w, 'pollsfactory-selected', $res);
    }

    /**
     * Check if widget is displayed.
     */
    private static function isDisplayed(WidgetsElement $w, bool $home = true): bool
    {
        return My::isActive() && !$w->get('offline') && (!$home || $w->checkHomeOnly(App::url()->getType()));
    }

    /**
     * Get a widget setting as string.
     */
    private static function str(WidgetsElement $w, string $key): string
    {
        $value = $w->get($key);

        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    /**
     * Render widget.
     */
    private static function render(WidgetsElement $w, string $class, string $content): string
    {
        return $w->renderDiv(
            (bool) $w->get('content_only'),
            'pollsfactory ' . $class . ' ' . self::str($w, 'class'),
            '',
            (self::str($w, 'title') !== '' ? $w->renderTitle(Html::escapeHTML(self::str($w, 'title'))) : '') . $content
        );
    }
}
