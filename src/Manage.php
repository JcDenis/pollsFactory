<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Process\TraitProcess;
use Exception;

/**
 * @brief       pollsFactory backend polls list.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Manage
{
    use TraitProcess;

    /**
     * Polls list filters.
     */
    private static ?FilterPolls $filter = null;

    /**
     * Polls list.
     */
    private static ?ListingPolls $list = null;

    /**
     * Polls list actions.
     */
    private static ?ActionsPolls $actions = null;

    /**
     * An action has been performed.
     */
    private static bool $action_done = false;

    /**
     * Get current part.
     */
    private static function part(): string
    {
        $part = $_REQUEST['part'] ?? 'polls';

        return in_array($part, ['polls', 'poll', 'results'], true) ? $part : 'polls';
    }

    public static function init(): bool
    {
        self::status(My::checkContext(My::MANAGE));

        return match (self::part()) {
            'poll'    => self::status(self::status() && ManagePoll::init()),
            'results' => self::status(self::status() && ManageResults::init()),
            default   => self::status(),
        };
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if (self::part() === 'poll') {
            return ManagePoll::process();
        }
        if (self::part() === 'results') {
            return ManageResults::process();
        }

        // Actions
        self::$actions = new ActionsPolls(My::manageUrl([], '&'), ['part' => 'polls']);
        if (self::$actions->process()) {
            self::$action_done = true;

            return true;
        }

        // Filters
        self::$filter = new FilterPolls();
        $params       = self::$filter->params();

        $params['no_content'] = true;

        // Polls list
        try {
            self::$list = new ListingPolls(
                Polls::getPolls($params),
                Polls::getPolls($params, true)->intField(0)
            );
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        return true;
    }

    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        if (self::part() === 'poll') {
            ManagePoll::render();

            return;
        }
        if (self::part() === 'results') {
            ManageResults::render();

            return;
        }

        // Actions page
        if (self::$action_done && self::$actions instanceof ActionsPolls) {
            self::$actions->render();

            return;
        }

        Page::openModule(
            My::name(),
            (self::$filter instanceof FilterPolls ? self::$filter->js(My::manageUrl([], '&')) : '') .
            Page::jsLoad('js/_posts_list.js')
        );

        echo
        Page::breadcrumb([
            Html::escapeHTML(App::blog()->name()) => '',
            My::name()                            => '',
        ]) .
        Notices::getNotices();

        if (!My::isActive()) {
            echo (new Note())
                ->class('warning')
                ->text(__('Polls are disabled on this blog. Enable them from plugin configuration.'))
                ->render();
        }

        echo (new Para())
            ->class('top-add')
            ->items([
                (new Link())
                    ->class(['button', 'add'])
                    ->href(My::manageUrl(['part' => 'poll']))
                    ->text(__('New poll')),
            ])
            ->render();

        if (self::$filter instanceof FilterPolls && self::$list instanceof ListingPolls && self::$actions instanceof ActionsPolls) {
            self::$filter->display('admin.plugin.' . My::id(), My::parsedHiddenFields(['part' => 'polls']));

            $page = is_numeric(self::$filter->value('page')) ? (int) self::$filter->value('page') : 1;
            $nb   = is_numeric(self::$filter->value('nb')) ? (int) self::$filter->value('nb') : 30;

            self::$list->display(
                $page,
                $nb,
                str_replace(['%', '__POLLS_LIST__'], ['%%', '%s'], (new Form('form-entries'))
                    ->method('post')
                    ->action(My::manageUrl())
                    ->fields([
                        (new \Dotclear\Helper\Html\Form\Text(null, '__POLLS_LIST__')),
                        (new Div())
                            ->class('two-cols')
                            ->items([
                                (new Para())
                                    ->class(['col', 'checkboxes-helpers']),
                                (new Para())
                                    ->class(['col', 'right', 'form-buttons'])
                                    ->items([
                                        (new Select('action'))
                                            ->items(self::$actions->getCombo())
                                            ->label(new Label(__('Selected polls action:'), Label::OL_TF)),
                                        (new Submit('do-action', __('ok'))),
                                        App::nonce()->formNonce(),
                                        ... My::hiddenFields(array_filter(self::$filter->values(), is_string(...))),
                                        (new Hidden(['part'], 'polls')),
                                    ]),
                            ]),
                    ])
                    ->render()),
                self::$filter->show()
            );
        }

        Page::helpBlock(My::id());
        Page::closeModule();
    }
}
